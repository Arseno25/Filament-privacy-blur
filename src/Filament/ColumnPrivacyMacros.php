<?php

namespace Arseno25\FilamentPrivacyBlur\Filament;

use Arseno25\FilamentPrivacyBlur\DataTransferObjects\PrivacyDecision;
use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\Filament\Concerns\HandlesColumnMasking;
use Arseno25\FilamentPrivacyBlur\Filament\Concerns\HandlesEntryMasking;
use Arseno25\FilamentPrivacyBlur\Filament\Concerns\HandlesFieldMasking;
use Arseno25\FilamentPrivacyBlur\Helpers\PrivacyMetadataHelper;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyDecisionResolver;
use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuthorizationService;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;

class ColumnPrivacyMacros
{
    use HandlesColumnMasking;
    use HandlesEntryMasking;
    use HandlesFieldMasking;

    public static function boot(): void
    {
        PrivacyMetadataHelper::init();

        $macroMethods = self::buildMacroMethods();

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

        $isAuthorized = PrivacyAuthorizationService::checkAuthorization($meta, $record);

        return PrivacyDecisionResolver::createDecision(
            $field->getName(),
            $overrideMode,
            $isAuthorized,
            $meta['privacy_blur_amount'] ?? null,
            $record,
            $meta['privacy_hidden_roles'] ?? null,
            self::resolveResourceClass($field),
            $meta['privacy_never_reveal'] ?? false
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

        if ($decision->shouldBlur && $decision->canRevealInteractively) {
            if ($decision->mode === PrivacyMode::BlurClick) {
                $attributes['data-privacy-click'] = 'true';
                $attributes['title'] = 'Click to reveal';
            } elseif ($decision->mode === PrivacyMode::BlurHover) {
                $attributes['data-privacy-hover'] = 'true';
            }
        }

        if (($meta['privacy_audit_reveal'] ?? false) && $decision->canRevealInteractively) {
            $attributes = array_merge($attributes, self::buildAuditAttributes(
                $decision,
                $columnName,
                $record,
                $field
            ));
        }

        return $attributes;
    }

    /** @return array<string, string> */
    private static function buildAuditAttributes(
        PrivacyDecision $decision,
        string $columnName,
        ?Model $record,
        Column | Entry | Field $field
    ): array {
        $attributes = [
            'data-privacy-audit' => 'true',
            'data-privacy-column' => $columnName,
            'data-privacy-mode' => $decision->mode->value,
        ];

        if ($record && $recordId = $record->getKey()) {
            $attributes['data-privacy-record-id'] = (string) $recordId;
        }

        if ($panel = Filament::getCurrentPanel()) {
            $attributes['data-privacy-panel'] = $panel->getId();
        }

        if ($resourceClass = self::resolveResourceClass($field)) {
            $attributes['data-privacy-resource'] = $resourceClass;
        }

        if (function_exists('tenancy') && tenancy()->initialized) {
            $tenant = tenancy()->getTenant();
            if ($tenant) {
                $attributes['data-privacy-tenant-id'] = (string) $tenant->getKey();
            }
        }

        return $attributes;
    }

    public static function applyMasking(mixed $state, ?Model $record, mixed $maskStrategy): mixed
    {
        return PrivacyBlurRenderer::applyMasking($state, $record, $maskStrategy);
    }

    public static function resolveResourceClass(object $component): ?string
    {
        return PrivacyContextDetector::resolveResourceClass($component);
    }

    public static function isExportContext(): bool
    {
        return PrivacyContextDetector::isExportContext();
    }

    /**
     * @return array<string, Closure>
     */
    private static function buildMacroMethods(): array
    {
        return [
            'private' => self::privateMacro(),
            ...self::fluentApiMacros(),
        ];
    }

    private static function privateMacro(): Closure
    {
        return function (?bool $condition = true) {
            /** @var Column|Entry|Field $this */
            if (! $condition) {
                return $this;
            }

            if ($this instanceof Field) {
                ColumnPrivacyMacros::applyToField($this);
            } elseif ($this instanceof Column) {
                ColumnPrivacyMacros::applyToColumn($this);
            } elseif ($this instanceof Entry) {
                ColumnPrivacyMacros::applyToEntry($this);
            }

            return $this;
        };
    }

    /**
     * @return array<string, Closure>
     */
    private static function fluentApiMacros(): array
    {
        return [
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

            'authorizeRevealWith' => function (string $ability, ?Model $record = null) {
                /** @var Column|Entry|Field $this */
                return PrivacyMetadataHelper::set($this, [
                    'privacy_ability' => $ability,
                    'privacy_auth_record' => $record,
                    'privacy_auth_method' => 'gate',
                ]);
            },

            'revealIfCan' => function (string $ability, ?Model $record = null) {
                /** @var Column|Entry|Field $this */
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
                    'privacy_never_reveal' => true,
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
    }
}
