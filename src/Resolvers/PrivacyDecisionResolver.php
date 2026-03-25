<?php

namespace Arseno25\FilamentPrivacyBlur\Resolvers;

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuthorizationService;
use Illuminate\Database\Eloquent\Model;

class PrivacyDecisionResolver
{
    /**
     * Resolve what should be displayed or applied for a column based on all configs.
     *
     * The authorization flow:
     * 1. If globally disabled, excepted column/resource/panel → show plain (no blur/mask)
     * 2. If user is in hiddenRoles → forced blur, never reveal
     * 3. If user passes auth checks (roles/permissions/policy/closure) → depends on mode:
     *    - blur/mask/blur_auth → show plain (authorized = bypass blur entirely)
     *    - blur_click/blur_hover → blur WITH reveal enabled (authorized = can interact to see)
     *    - hybrid → show masked (even authorized users see masked)
     * 4. If user FAILS auth checks → blur/mask WITHOUT reveal
     *
     * @param  string  $columnName  The column name.
     * @param  PrivacyMode|null  $overrideMode  The specifically requested privacy mode on the column.
     * @param  bool  $isAuthorized  Whether the current user passes the auth checks.
     * @param  int|null  $columnBlur  Column-specific blur amount override.
     * @param  Model|null  $record  The data record.
     * @param  array|null  $hiddenRoles  Roles that should be forced to see blur.
     * @param  string|null  $resourceClass  The Filament resource class for except checking.
     * @return array{mode: PrivacyMode|null, should_blur: bool, should_mask: bool, reveal_enabled: bool, blur_amount: int}
     */
    public static function resolveForColumn(
        string $columnName,
        ?PrivacyMode $overrideMode,
        bool $isAuthorized,
        ?int $columnBlur = null,
        ?Model $record = null,
        ?array $hiddenRoles = null,
        ?string $resourceClass = null
    ): array {
        // If globally disabled or panel not registered, do nothing
        if (! PrivacyConfigResolver::isEnabledGlobally()) {
            return self::emptyDecision();
        }

        // Check excepted columns
        if (PrivacyConfigResolver::isColumnExcepted($columnName)) {
            return self::emptyDecision();
        }

        // Check excepted resources
        if ($resourceClass && PrivacyConfigResolver::isResourceExcepted($resourceClass)) {
            return self::emptyDecision();
        }

        // Check excepted panels
        if (PrivacyConfigResolver::isPanelExcepted()) {
            return self::emptyDecision();
        }

        $mode = PrivacyConfigResolver::resolveMode($overrideMode);

        if ($mode === PrivacyMode::Disabled) {
            return self::emptyDecision();
        }

        $shouldBlur = false;
        $shouldMask = false;
        $revealEnabled = false;

        // Check if user is in hidden roles (forced blur regardless of authorization)
        $isHidden = PrivacyAuthorizationService::isHidden($hiddenRoles);
        if ($isHidden) {
            $isAuthorized = false;
        }

        if ($isAuthorized) {
            // Authorized users behavior depends on mode:
            switch ($mode) {
                case PrivacyMode::Hybrid:
                    // Even authorized users see masked data in hybrid mode
                    $shouldMask = true;

                    break;

                case PrivacyMode::BlurClick:
                case PrivacyMode::BlurHover:
                    // Authorized users see blur but CAN reveal via click/hover
                    $shouldBlur = true;
                    $revealEnabled = true;

                    break;

                default:
                    // blur, mask, blur_auth — authorized users bypass entirely
                    return self::emptyDecision();
            }
        } else {
            // Unauthorized users — blur/mask WITHOUT reveal
            switch ($mode) {
                case PrivacyMode::Blur:
                    $shouldBlur = true;
                    $revealEnabled = false;

                    break;
                case PrivacyMode::Mask:
                    $shouldMask = true;
                    $revealEnabled = false;

                    break;
                case PrivacyMode::BlurHover:
                case PrivacyMode::BlurClick:
                    // Unauthorized: blur WITHOUT reveal capability
                    $shouldBlur = true;
                    $revealEnabled = false;

                    break;
                case PrivacyMode::BlurAuth:
                    $shouldBlur = true;
                    $revealEnabled = false;

                    break;
                case PrivacyMode::Hybrid:
                    $shouldBlur = true;
                    $shouldMask = true;
                    $revealEnabled = false;

                    break;
            }
        }

        return [
            'mode' => $mode,
            'should_blur' => $shouldBlur,
            'should_mask' => $shouldMask,
            'reveal_enabled' => $revealEnabled,
            'blur_amount' => PrivacyConfigResolver::resolveBlurAmount($columnBlur),
        ];
    }

    protected static function emptyDecision(): array
    {
        return [
            'mode' => null,
            'should_blur' => false,
            'should_mask' => false,
            'reveal_enabled' => false,
            'blur_amount' => 0,
        ];
    }
}
