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
     * @param  string  $columnName  The column name.
     * @param  PrivacyMode|null  $overrideMode  The specifically requested privacy mode on the column.
     * @param  bool  $isAuthorized  Whether the current user is authorized to bypass blur.
     * @param  Model|null  $record  The data record.
     * @param  array|null  $hiddenRoles  Roles that should be forced to see blur.
     * @return array{mode: PrivacyMode|null, should_blur: bool, should_mask: bool, reveal_enabled: bool, blur_amount: int}
     */
    public static function resolveForColumn(
        string $columnName,
        ?PrivacyMode $overrideMode,
        bool $isAuthorized,
        ?int $columnBlur = null,
        ?Model $record = null,
        ?array $hiddenRoles = null
    ): array {
        // If globally disabled or panel not registered, do nothing
        if (! PrivacyConfigResolver::isEnabledGlobally()) {
            return self::emptyDecision();
        }

        // Check excepted columns
        if (PrivacyConfigResolver::isColumnExcepted($columnName)) {
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

        // If the user is authorized (e.g. role check passed), reveal it full unless it's configured to mask
        // Actually, if authorized, we usually skip blurring unless it's hybrid
        if ($isAuthorized) {
            if ($mode === PrivacyMode::Hybrid) {
                // E.g., maybe authorized users still see it masked
                $shouldMask = true;
            } else {
                return self::emptyDecision();
            }
        } else {
            // Unauthorized or public user
            switch ($mode) {
                case PrivacyMode::Blur:
                    $shouldBlur = true;
                    $revealEnabled = false;

                    break;
                case PrivacyMode::Mask:
                    $shouldMask = true;
                    $shouldBlur = false; // Usually mask doesn't need blur
                    $revealEnabled = false;

                    break;
                case PrivacyMode::BlurHover:
                case PrivacyMode::BlurClick:
                    $shouldBlur = true;
                    $revealEnabled = true; // anyone can reveal via UI action

                    break;
                case PrivacyMode::BlurAuth:
                    $shouldBlur = true;
                    $revealEnabled = false; // Since they are NOT authorized, they cannot reveal

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
