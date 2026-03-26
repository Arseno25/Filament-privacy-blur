<?php

namespace Arseno25\FilamentPrivacyBlur\Resolvers;

use Arseno25\FilamentPrivacyBlur\DataTransferObjects\PrivacyDecision;
use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuthorizationService;
use Illuminate\Database\Eloquent\Model;

class PrivacyDecisionResolver
{
    /**
     * Resolve privacy decision for a column based on all factors.
     *
     * @return array<string, mixed> Legacy format for backward compatibility, includes full decision object
     */
    public static function resolveForColumn(
        string $columnName,
        ?PrivacyMode $overrideMode,
        bool $isAuthorized,
        ?int $columnBlur = null,
        ?Model $record = null,
        ?array $hiddenRoles = null,
        ?string $resourceClass = null,
        bool $neverReveal = false
    ): array {
        $decision = self::createDecision(
            $columnName,
            $overrideMode,
            $isAuthorized,
            $columnBlur,
            $record,
            $hiddenRoles,
            $resourceClass,
            $neverReveal
        );

        // Return legacy array format for backward compatibility
        return $decision->toLegacyArray();
    }

    /**
     * Create the explicit PrivacyDecision value object.
     *
     * The authorization flow:
     * 1. If globally disabled, excepted column/resource/panel → no privacy
     * 2. If user is in hiddenRoles → forced blur, never reveal
     * 3. If user passes auth checks and neverReveal is false → depends on mode:
     *    - blur/mask/blur_auth → show plain (authorized = bypass blur entirely)
     *    - blur_click/blur_hover → blur WITH reveal enabled (authorized = can interact to see)
     *    - hybrid → show masked (even authorized users see masked)
     * 4. If user FAILS auth checks → blur/mask WITHOUT reveal
     *
     * @param  string  $columnName  The column name
     * @param  PrivacyMode|null  $overrideMode  The specifically requested privacy mode on the column
     * @param  bool  $isAuthorized  Whether the current user passes the auth checks
     * @param  int|null  $columnBlur  Column-specific blur amount override
     * @param  Model|null  $record  The data record
     * @param  array<string>|null  $hiddenRoles  Roles that should be forced to see blur
     * @param  string|null  $resourceClass  The Filament resource class for except checking
     * @param  bool  $neverReveal  If true, never allow reveal regardless of authorization
     */
    public static function createDecision(
        string $columnName,
        ?PrivacyMode $overrideMode,
        bool $isAuthorized,
        ?int $columnBlur = null,
        ?Model $record = null,
        ?array $hiddenRoles = null,
        ?string $resourceClass = null,
        bool $neverReveal = false
    ): PrivacyDecision {
        // Check global/pANEL exemptions
        if (! PrivacyConfigResolver::isEnabledGlobally()) {
            return PrivacyDecision::noPrivacy();
        }

        if (PrivacyConfigResolver::isColumnExcepted($columnName)) {
            return PrivacyDecision::noPrivacy();
        }

        if ($resourceClass && PrivacyConfigResolver::isResourceExcepted($resourceClass)) {
            return PrivacyDecision::noPrivacy();
        }

        if (PrivacyConfigResolver::isPanelExcepted()) {
            return PrivacyDecision::noPrivacy();
        }

        $mode = PrivacyConfigResolver::resolveMode($overrideMode);
        $blurAmount = PrivacyConfigResolver::resolveBlurAmount($columnBlur);

        if ($mode === PrivacyMode::Disabled) {
            return PrivacyDecision::noPrivacy();
        }

        // Check hidden roles (forced blur, highest priority override)
        $isHidden = PrivacyAuthorizationService::isHidden($hiddenRoles);
        if ($isHidden) {
            $decision = PrivacyDecision::unauthorized($mode, $blurAmount, neverReveal: false)
                ->forceBlur();

            return $neverReveal ? $decision->withNeverReveal() : $decision;
        }

        // Apply neverReveal flag if set
        if ($neverReveal) {
            return PrivacyDecision::unauthorized($mode, $blurAmount, neverReveal: true);
        }

        // Build decision based on mode and authorization
        if ($isAuthorized) {
            return PrivacyDecision::fullyAuthorized($mode, $blurAmount);
        }

        return PrivacyDecision::unauthorized($mode, $blurAmount);
    }
}
