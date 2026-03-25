<?php

namespace Arseno25\FilamentPrivacyBlur\Filament;

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
                        $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;

                        $decision = PrivacyDecisionResolver::resolveForColumn(
                            $this->getName(),
                            $meta['privacy_mode'] ?? null,
                            PrivacyAuthorizationService::isAuthorized(
                                roles: $meta['privacy_roles'] ?? null,
                                permissions: $meta['privacy_permissions'] ?? null,
                                policy: $meta['privacy_policy'] ?? null,
                                customAuth: $meta['privacy_auth_closure'] ?? null,
                                record: $record
                            ),
                            $meta['privacy_blur_amount'] ?? null,
                            null,
                            $meta['privacy_hidden_roles'] ?? null
                        );

                        if (! $decision['should_blur'] && ! $decision['should_mask']) {
                            return [];
                        }

                        $blurAmount = $decision['blur_amount'];

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

                    $overrideMode = isset($meta['privacy_mode']) && $meta['privacy_mode'] instanceof PrivacyMode
                        ? $meta['privacy_mode']
                        : null;

                    $isAuthorized = PrivacyAuthorizationService::isAuthorized(
                        roles: $meta['privacy_roles'] ?? null,
                        permissions: $meta['privacy_permissions'] ?? null,
                        policy: $meta['privacy_policy'] ?? null,
                        customAuth: $meta['privacy_auth_closure'] ?? null,
                        record: $record
                    );

                    $columnBlur = $meta['privacy_blur_amount'] ?? null;
                    $hiddenRoles = $meta['privacy_hidden_roles'] ?? null;

                    $decision = PrivacyDecisionResolver::resolveForColumn(
                        $columnName,
                        $overrideMode,
                        $isAuthorized,
                        $columnBlur,
                        $record,
                        $hiddenRoles
                    );

                    // Store decision in metadata for formatStateUsing to access
                    PrivacyMetadataHelper::set($this, ['_last_decision' => $decision]);

                    if (! $decision['should_blur'] && ! $decision['should_mask']) {
                        return [];
                    }

                    $attributes = ['data-privacy-blur' => 'true'];

                    if ($decision['should_blur']) {
                        $mode = $decision['mode'];

                        if ($decision['reveal_enabled']) {
                            if ($mode === PrivacyMode::BlurClick) {
                                $attributes['data-privacy-click'] = 'true';
                                $attributes['title'] = 'Click to reveal';

                                // Store audit data
                                if ($meta['privacy_audit_reveal'] ?? false) {
                                    $attributes['data-privacy-audit'] = 'true';
                                    $attributes['data-privacy-column'] = $columnName;
                                    $attributes['data-privacy-mode'] = $mode->value;
                                    $recordId = $record ? $record->getKey() : '';
                                    if ($recordId) {
                                        $attributes['data-privacy-record-id'] = $recordId;
                                    }
                                    // Add panel context for audit
                                    $panel = Filament::getCurrentPanel();
                                    if ($panel) {
                                        $attributes['data-privacy-panel'] = $panel->getId();
                                    }
                                }
                            } elseif ($mode === PrivacyMode::BlurHover) {
                                $attributes['data-privacy-hover'] = 'true';
                            }
                        }
                    }

                    return $attributes;
                });

                // Hook into format state for Table Columns and Infolist Entries
                // This is where we wrap the content in a span with blur classes
                if (method_exists($this, 'formatStateUsing')) {
                    $this->formatStateUsing(function ($state, ?Model $record = null) {
                        /** @var Column|Entry $this */
                        $columnName = $this->getName();
                        $meta = PrivacyMetadataHelper::get($this);

                        $overrideMode = isset($meta['privacy_mode']) && $meta['privacy_mode'] instanceof PrivacyMode
                            ? $meta['privacy_mode']
                            : null;

                        $isAuthorized = PrivacyAuthorizationService::isAuthorized(
                            roles: $meta['privacy_roles'] ?? null,
                            permissions: $meta['privacy_permissions'] ?? null,
                            policy: $meta['privacy_policy'] ?? null,
                            customAuth: $meta['privacy_auth_closure'] ?? null,
                            record: $record
                        );

                        $columnBlur = $meta['privacy_blur_amount'] ?? null;
                        $hiddenRoles = $meta['privacy_hidden_roles'] ?? null;

                        $decision = PrivacyDecisionResolver::resolveForColumn(
                            $columnName,
                            $overrideMode,
                            $isAuthorized,
                            $columnBlur,
                            $record,
                            $hiddenRoles
                        );

                        // Handle masking first
                        if ($decision['should_mask']) {
                            $maskStrategy = $meta['mask_strategy'] ?? null;

                            if ($maskStrategy instanceof Closure) {
                                return app()->call($maskStrategy, ['state' => (string) $state, 'record' => $record]);
                            }

                            $strategyStr = PrivacyConfigResolver::resolveMaskStrategy(
                                is_string($maskStrategy) ? $maskStrategy : null
                            );

                            return app(PrivacyMaskingService::class)->mask($strategyStr, (string) $state);
                        }

                        // Export context — apply masking instead of blur since blur is visual-only
                        if ($decision['should_blur'] && ColumnPrivacyMacros::isExportContext()) {
                            $maskStrategy = $meta['mask_strategy'] ?? null;

                            if ($maskStrategy instanceof Closure) {
                                return app()->call($maskStrategy, ['state' => (string) $state, 'record' => $record]);
                            }

                            $strategyStr = PrivacyConfigResolver::resolveMaskStrategy(
                                is_string($maskStrategy) ? $maskStrategy : null
                            );

                            return app(PrivacyMaskingService::class)->mask($strategyStr, (string) $state);
                        }

                        // Handle blur by wrapping in span with CSS classes
                        if ($decision['should_blur']) {
                            $mode = $decision['mode'];
                            $blurAmount = $decision['blur_amount'];
                            $blurClass = "fi-privacy-blur fi-pb-{$blurAmount}";

                            if ($decision['reveal_enabled']) {
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
                return PrivacyMetadataHelper::set($this, ['privacy_permissions' => [$permission]]);
            },

            'authorizeUsing' => function (Closure $closure) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_auth_closure' => $closure]);
            },

            'authorizeRevealUsing' => function (Closure $closure) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, ['privacy_auth_closure' => $closure]);
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
                return PrivacyMetadataHelper::set($this, ['privacy_mode' => PrivacyMode::Blur]);
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
     * Detect if the current request is an export context.
     * Uses multiple strategies for reliability instead of just routeIs('*.export*').
     */
    public static function isExportContext(): bool
    {
        $route = request()->route();
        if ($route) {
            $routeName = $route->getName() ?? '';
            if (str_contains($routeName, 'export')) {
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
