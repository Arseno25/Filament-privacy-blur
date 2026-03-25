<?php

namespace Arseno25\FilamentPrivacyBlur\Filament;

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyConfigResolver;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyDecisionResolver;
use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuthorizationService;
use Arseno25\FilamentPrivacyBlur\Services\PrivacyMaskingService;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;

class ColumnPrivacyMacros
{
    public static function boot(): void
    {
        $macroMethods = [
            'private' => function (?bool $condition = true) {
                /** @var Column|Entry|Field $this */
                if (! $condition) {
                    return $this;
                }

                // If this is a Form Field, we use extraInputAttributes
                // Note: Field class has extraInputAttributes but Column/Entry has extraAttributes
                if ($this instanceof Field) {
                    $this->extraInputAttributes(function () {
                        /** @var Field $this */
                        $meta = clone $this; // not perfectly clean, but Forms don't easily give getCustomProperties

                        // We will rely purely on Alpine to blur unless focused
                        return [
                            'x-data' => '{ isFocused: false, isGlobalRevealed: false }',
                            'x-on:focus' => 'isFocused = true',
                            'x-on:blur' => 'isFocused = false',
                            'x-on:toggle-privacy-blur.window' => 'isGlobalRevealed = !isGlobalRevealed',
                            'x-bind:class' => "{ 'blur-sm select-none': !isFocused && !isGlobalRevealed }",
                        ];
                    });

                    return $this;
                }

                // For Table Columns and Infolist Entries:
                $this->extraAttributes(function (?Model $record = null) {
                    /** @var Column|Entry $this */
                    $columnName = $this->getName();
                    $meta = $this->getCustomProperties() ?? [];

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

                    if (! $decision['should_blur'] && ! $decision['should_mask']) {
                        return [];
                    }

                    $attributes = [
                        'data-privacy-blur' => 'true',
                    ];

                    if ($decision['should_blur']) {
                        $mode = $decision['mode'];
                        $blurClass = 'o-privacy-blur pb-' . $decision['blur_amount'];

                        if ($decision['reveal_enabled']) {
                            if ($mode === PrivacyMode::BlurClick) {
                                $recordId = $record ? $record->getKey() : '';
                                $auditEnabled = ($meta['privacy_audit_reveal'] ?? false) ? 'true' : 'false';
                                $auditRoute = '/filament-privacy-blur/audit'; // Safe relative endpoint

                                $attributes['x-data'] = "{ 
                                    isRevealed: false, 
                                    timeout: null,
                                    toggle() {
                                        this.isRevealed = !this.isRevealed;
                                        if (this.isRevealed) {
                                            clearTimeout(this.timeout);
                                            this.timeout = setTimeout(() => { this.isRevealed = false; }, 5000);
                                            
                                            if ({$auditEnabled}) {
                                                fetch('{$auditRoute}', {
                                                    method: 'POST',
                                                    headers: {
                                                        'Content-Type': 'application/json',
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content')
                                                    },
                                                    body: JSON.stringify({ column: '{$columnName}', record_id: '{$recordId}', mode: '{$mode->value}' })
                                                });
                                            }
                                        }
                                    }
                                }";
                                $attributes['x-on:click.stop'] = 'toggle()';
                                $attributes['x-on:toggle-privacy-blur.window'] = 'isRevealed = !isRevealed';
                                $attributes['x-bind:class'] = "{ '{$blurClass} text-transparent': !isRevealed }";
                                $attributes['class'] = 'cursor-pointer transition-all duration-300 select-none';
                                $attributes['x-bind:aria-hidden'] = '(!isRevealed).toString()';
                                $attributes['title'] = 'Click to reveal';
                            } elseif ($mode === PrivacyMode::BlurHover) {
                                $attributes['x-data'] = '{ isGlobalRevealed: false }';
                                $attributes['x-on:toggle-privacy-blur.window'] = 'isGlobalRevealed = !isGlobalRevealed';
                                $attributes['x-bind:class'] = "{ '{$blurClass} pb-hover text-transparent': !isGlobalRevealed }";
                                $attributes['class'] = 'transition-all duration-300 select-none';
                            }
                        } else {
                            $attributes['x-data'] = '{ isGlobalRevealed: false }';
                            $attributes['x-on:toggle-privacy-blur.window'] = 'isGlobalRevealed = !isGlobalRevealed';
                            $attributes['x-bind:class'] = "{ '{$blurClass} text-transparent': !isGlobalRevealed }";
                            $attributes['class'] = 'transition-all duration-300 select-none';
                            $attributes['x-bind:aria-hidden'] = '(!isGlobalRevealed).toString()';
                        }
                    }

                    return $attributes;
                });

                // Hook into format state for Table Columns and Infolist Entries
                if (method_exists($this, 'formatStateUsing')) {
                    $this->formatStateUsing(function ($state, ?Model $record = null) {
                        /** @var Column|Entry $this */
                        $columnName = $this->getName();
                        $meta = method_exists($this, 'getCustomProperties') ? $this->getCustomProperties() : [];

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

                        if ($decision['should_mask']) {
                            $strategy = PrivacyConfigResolver::resolveMaskStrategy($meta['mask_strategy'] ?? null);

                            return app(PrivacyMaskingService::class)->mask($strategy, (string) $state);
                        }

                        // Export Fallback Logic - if it's an export action, fallback to masking for privacy
                        if ($decision['should_blur'] && request()->routeIs('*.export*')) {
                            return '********'; // Fallback mask when attempting to export blurred data
                        }

                        return $state;
                    });
                }

                return $this;
            },

            // Fluent setters
            'getCustomProperties' => function () {
                /** @var Column|Entry|Field $this */
                return property_exists($this, 'customProperties') ? $this->customProperties : [];
            },

            'setCustomProperty' => function (string $key, $value) {
                /** @var Column|Entry|Field $this */
                if (! property_exists($this, 'customProperties')) {
                    $this->customProperties = [];
                }
                $this->customProperties[$key] = $value;

                return $this;
            },

            // Fluent API method duplications for Field support
            'privacyMode' => function (PrivacyMode | string $mode) {
                /** @var Column|Entry|Field $this */
                if (is_string($mode)) {
                    $mode = PrivacyMode::from($mode);
                }

                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_mode', $mode) : $this;
            },

            'maskUsing' => function (string $strategy) {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('mask_strategy', $strategy) : $this;
            },

            'visibleToRoles' => function (array $roles) {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_roles', $roles) : $this;
            },

            'visibleToPermissions' => function (array $permissions) {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_permissions', $permissions) : $this;
            },

            'privacyPolicy' => function (string $policy) {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_policy', $policy) : $this;
            },

            'policy' => function (string $policy) {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_policy', $policy) : $this;
            },

            'permission' => function (string $permission) {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_permissions', [$permission]) : $this;
            },

            'authorizeUsing' => function (\Closure $closure) {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_auth_closure', $closure) : $this;
            },

            'authorizeRevealUsing' => function (\Closure $closure) {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_auth_closure', $closure) : $this;
            },

            'hiddenFromRoles' => function (array $roles) {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_hidden_roles', $roles) : $this;
            },

            'blurAmount' => function (int $amount) {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_blur_amount', $amount) : $this;
            },

            'revealOnHover' => function () {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_mode', PrivacyMode::BlurHover) : $this;
            },

            'revealOnClick' => function () {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_mode', PrivacyMode::BlurClick) : $this;
            },

            'revealNever' => function () {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_mode', PrivacyMode::Blur) : $this;
            },

            'auditReveal' => function (bool $condition = true) {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_audit_reveal', $condition) : $this;
            },

            'withoutAuditReveal' => function () {
                /** @var Column|Entry|Field $this */
                return method_exists($this, 'setCustomProperty') ? $this->setCustomProperty('privacy_audit_reveal', false) : $this;
            },
        ];

        foreach ($macroMethods as $name => $closure) {
            Column::macro($name, $closure);
            Entry::macro($name, $closure);
            Field::macro($name, $closure);
        }
    }
}
