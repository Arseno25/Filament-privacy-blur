<?php

namespace Arseno25\FilamentPrivacyBlur\Filament;

use Arseno25\FilamentPrivacyBlur\DataTransferObjects\PrivacyDecision;
use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\Helpers\PrivacyMetadataHelper;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyConfigResolver;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyDecisionResolver;
use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuthorizationService;
use Arseno25\FilamentPrivacyBlur\Services\PrivacyMaskingService;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class ColumnPrivacyMacros
{
    public static function boot(): void
    {
        // Initialize metadata helper
        PrivacyMetadataHelper::init();

        $macroMethods = [
            'private' => function (?bool $condition = true) {
                /** @var Column|Entry|Field $this */
                if (! $condition) {
                    return $this;
                }

                // If this is a Form Field, check for extraInputAttributes support
                if ($this instanceof Field) {
                    $callback = function () {
                        /** @var Field $this */
                        $meta = PrivacyMetadataHelper::get($this);

                        // Attempt to resolve record from form context
                        $record = $this->getRecord();

                        $decision = ColumnPrivacyMacros::resolveDecisionForField($this, $record, $meta);

                        if (! $decision->hasPrivacyEffect()) {
                            return [];
                        }

                        $blurAmount = $decision->blurAmount;

                        return [
                            'class' => "fi-privacy-blur fi-pb-{$blurAmount}",
                            'data-privacy-input' => 'true',
                        ];
                    };

                    // Use extraInputAttributes if available (TextInput, Textarea, etc.)
                    // This adds classes directly to the input element
                    if (method_exists($this, 'extraInputAttributes')) {
                        $this->extraInputAttributes($callback);
                    } else {
                        // Fallback to extraAttributes for wrapper
                        $this->extraAttributes($callback);
                    }

                    return $this;
                }

                // For Table Columns and Infolist Entries:
                // Store data attributes via extraAttributes for JavaScript interaction
                $this->extraAttributes(function (?Model $record = null) {
                    /** @var Column|Entry $this */
                    $columnName = $this->getName();
                    $meta = PrivacyMetadataHelper::get($this);

                    $decision = ColumnPrivacyMacros::resolveDecisionForField($this, $record, $meta);

                    // Store decision in metadata for formatStateUsing to access
                    PrivacyMetadataHelper::set($this, ['_last_decision' => $decision->toLegacyArray()]);

                    if (! $decision->hasPrivacyEffect()) {
                        return [];
                    }

                    return ColumnPrivacyMacros::buildPrivacyAttributes($decision, $columnName, $record, $meta, $this);
                });

                // Hook into format state for Table Columns and Infolist Entries
                // This is where we wrap the content in a span with blur classes
                if (method_exists($this, 'formatStateUsing')) {
                    $this->formatStateUsing(function ($state, ?Model $record = null) {
                        /** @var Column|Entry $this */
                        $meta = PrivacyMetadataHelper::get($this);

                        $decision = ColumnPrivacyMacros::resolveDecisionForField($this, $record, $meta);

                        // Handle masking first
                        if ($decision->shouldRenderMasked) {
                            return ColumnPrivacyMacros::applyMasking($state, $record, $meta['mask_strategy'] ?? null);
                        }

                        // Export context — apply masking instead of blur since blur is visual-only
                        if ($decision->shouldBlur && ColumnPrivacyMacros::isExportContext()) {
                            return ColumnPrivacyMacros::applyMasking($state, $record, $meta['mask_strategy'] ?? null);
                        }

                        // Handle blur by wrapping in span with CSS classes
                        if ($decision->shouldBlur) {
                            $mode = $decision->mode;
                            $blurAmount = $decision->blurAmount;
                            $blurClass = "fi-privacy-blur fi-pb-{$blurAmount}";

                            if ($decision->canRevealInteractively) {
                                if ($mode === PrivacyMode::BlurClick) {
                                    // Click to reveal — data attributes are on the outer wrapper via extraAttributes
                                    return new HtmlString(
                                        "<span class=\"{$blurClass} fi-text-transparent fi-cursor-pointer transition-all duration-300 select-none\">" .
                                        e((string) $state) .
                                        '</span>'
                                    );
                                } elseif ($mode === PrivacyMode::BlurHover) {
                                    // Hover to reveal
                                    return new HtmlString(
                                        "<span class=\"{$blurClass} fi-hover fi-text-transparent transition-all duration-300 select-none\">" .
                                        e((string) $state) .
                                        '</span>'
                                    );
                                }
                            }

                            // No reveal - always blurred
                            return new HtmlString(
                                "<span class=\"{$blurClass} fi-text-transparent transition-all duration-300 select-none\">" .
                                e((string) $state) .
                                '</span>'
                            );
                        }

                        return $state;
                    });
                }

                return $this;
            },

            // Fluent API methods using PrivacyMetadataHelper
            'privacyMode' => function (PrivacyMode | string $mode) {
                /** @var Column|Entry|Field $this */
                if (is_string($mode)) {
                    $mode = PrivacyMode::from($mode);
                }

                return PrivacyMetadataHelper::set($this, ['privacy_mode' => $mode]);
            },

            'maskUsing' => function (Closure | string $strategy) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['mask_strategy' => $strategy]);
            },

            'visibleToRoles' => function (array $roles) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_roles' => $roles]);
            },

            'visibleToPermissions' => function (array $permissions) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_permissions' => $permissions]);
            },

            'privacyPolicy' => function (string $policy) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_policy' => $policy]);
            },

            'policy' => function (string $policy) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_policy' => $policy]);
            },

            'permission' => function (string $permission) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_permission' => $permission]);
            },

            'authorizeUsing' => function (Closure $closure) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_auth_closure' => $closure]);
            },

            'authorizeRevealUsing' => function (Closure $closure) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_auth_closure' => $closure]);
            },

            // NEW: Ability-first authorization methods (primary API)

            'authorizeRevealWith' => function (string $ability, ?Model $record = null) {
                /** @var Column|Entry|Field $this */
                // Store the ability for Gate/Policy checks
                return PrivacyMetadataHelper::set($this, [
                    'privacy_ability' => $ability,
                    'privacy_auth_record' => $record,
                    'privacy_auth_method' => 'gate',
                ]);
            },

            'revealIfCan' => function (string $ability, ?Model $record = null) {
                /** @var Column|Entry|Field $this */
                // Alias for authorizeRevealWith with clearer semantics
                return PrivacyMetadataHelper::set($this, [
                    'privacy_ability' => $ability,
                    'privacy_auth_record' => $record,
                    'privacy_auth_method' => 'gate',
                ]);
            },

            'hiddenFromRoles' => function (array $roles) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_hidden_roles' => $roles]);
            },

            'blurAmount' => function (int $amount) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_blur_amount' => $amount]);
            },

            'revealOnHover' => function () {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_mode' => PrivacyMode::BlurHover]);
            },

            'revealOnClick' => function () {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_mode' => PrivacyMode::BlurClick]);
            },

            'revealNever' => function () {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, [
                    'privacy_mode' => PrivacyMode::Blur,
                    'privacy_never_reveal' => true, // Flag to prevent any reveal
                ]);
            },

            'auditReveal' => function (bool $condition = true) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_audit_reveal' => $condition]);
            },

            'withoutAuditReveal' => function () {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_audit_reveal' => false]);
            },
        ];

        foreach ($macroMethods as $name => $closure) {
            Column::macro($name, $closure);
            Entry::macro($name, $closure);
            Field::macro($name, $closure);
        }
    }

    /**
     * Resolve the privacy decision for a field (Column, Entry, or Field).
     */
    public static function resolveDecisionForField(
        Column | Entry | Field $field,
        ?Model $record,
        array $meta
    ): PrivacyDecision {
        $overrideMode = isset($meta['privacy_mode']) && $meta['privacy_mode'] instanceof PrivacyMode
            ? $meta['privacy_mode']
            : null;

        // Use new checkAuthorization method that respects priority order
        $isAuthorized = PrivacyAuthorizationService::checkAuthorization($meta, $record);

        $columnBlur = $meta['privacy_blur_amount'] ?? null;
        $hiddenRoles = $meta['privacy_hidden_roles'] ?? null;
        $neverReveal = $meta['privacy_never_reveal'] ?? false;
        $resourceClass = self::resolveResourceClass($field);
        $columnName = $field->getName();

        return PrivacyDecisionResolver::createDecision(
            $columnName,
            $overrideMode,
            $isAuthorized,
            $columnBlur,
            $record,
            $hiddenRoles,
            $resourceClass,
            $neverReveal
        );
    }

    /**
     * Build the data attributes for privacy elements.
     *
     * @return array<string, string>
     */
    public static function buildPrivacyAttributes(
        PrivacyDecision $decision,
        string $columnName,
        ?Model $record,
        array $meta,
        Column | Entry | Field $field
    ): array {
        $attributes = [
            'data-privacy-enabled' => 'true',
            'data-privacy-mode' => $decision->mode->value,
            'data-privacy-can-reveal-interactively' => $decision->canRevealInteractively ? 'true' : 'false',
            'data-privacy-can-globally-reveal' => $decision->canBeGloballyRevealed ? 'true' : 'false',
            'data-privacy-never-reveal' => $decision->neverReveal ? 'true' : 'false',
        ];

        // Mode-specific attributes
        if ($decision->shouldBlur && $decision->canRevealInteractively) {
            if ($decision->mode === PrivacyMode::BlurClick) {
                $attributes['data-privacy-click'] = 'true';
                $attributes['title'] = 'Click to reveal';
            } elseif ($decision->mode === PrivacyMode::BlurHover) {
                $attributes['data-privacy-hover'] = 'true';
            }
        }

        // Audit attributes
        if (($meta['privacy_audit_reveal'] ?? false) && $decision->canRevealInteractively) {
            $attributes['data-privacy-audit'] = 'true';
            $attributes['data-privacy-column'] = $columnName;
            $attributes['data-privacy-mode'] = $decision->mode->value;

            $recordId = $record ? $record->getKey() : '';
            if ($recordId) {
                $attributes['data-privacy-record-id'] = $recordId;
            }

            // Add panel context for audit
            $panel = Filament::getCurrentPanel();
            if ($panel) {
                $attributes['data-privacy-panel'] = $panel->getId();
            }

            // Add resource for audit
            $resourceClass = self::resolveResourceClass($field);
            if ($resourceClass) {
                $attributes['data-privacy-resource'] = $resourceClass;
            }

            // Add tenant context if available (for multi-tenant apps)
            if (function_exists('tenancy') && tenancy()->initialized) {
                $tenant = tenancy()->getTenant();
                if ($tenant) {
                    $attributes['data-privacy-tenant-id'] = $tenant->getKey();
                }
            }
        }

        return $attributes;
    }

    public static function applyMasking(mixed $state, ?Model $record, mixed $maskStrategy): mixed
    {
        if ($maskStrategy instanceof Closure) {
            return app()->call($maskStrategy, ['state' => (string) $state, 'record' => $record]);
        }

        $strategyStr = PrivacyConfigResolver::resolveMaskStrategy(
            is_string($maskStrategy) ? $maskStrategy : null
        );

        return app(PrivacyMaskingService::class)->mask($strategyStr, (string) $state);
    }

    public static function resolveResourceClass(object $component): ?string
    {
        try {
            if (method_exists($component, 'getLivewire')) {
                $livewire = $component->getLivewire();
                if ($livewire && method_exists($livewire, 'getResource')) {
                    return $livewire::getResource();
                }
            }
        } catch (\Throwable $e) {
            // Component is not mounted in test
        }

        return null;
    }

    /**
     * Detect if the current request is an export context.
     * Uses multiple strategies for reliability instead of just routeIs('*.export*').
     */
    public static function isExportContext(): bool
    {
        $route = request()->route();
        if ($route) {
            $routeName = $route->getName() ?? '';
            if (preg_match('/\bexport\b/', $routeName)) {
                return true;
            }
        }

        // Check for Filament export header
        if (request()->hasHeader('X-Filament-Export')) {
            return true;
        }

        return false;
    }
}
