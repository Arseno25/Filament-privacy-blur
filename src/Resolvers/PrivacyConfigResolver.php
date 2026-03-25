<?php

namespace Arseno25\FilamentPrivacyBlur\Resolvers;

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlurPlugin;
use Filament\Facades\Filament;

class PrivacyConfigResolver
{
    /**
     * Determine if privacy features are globally enabled for the current panel.
     */
    public static function isEnabledGlobally(): bool
    {
        $panel = Filament::getCurrentPanel();

        if (! $panel || ! $panel->hasPlugin('filament-privacy-blur')) {
            return false;
        }

        try {
            $plugin = FilamentPrivacyBlurPlugin::get();

            return $plugin->getIsEnabled();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Resolve the active privacy mode for a specific column context.
     */
    public static function resolveMode(?PrivacyMode $columnMode = null): PrivacyMode
    {
        if ($columnMode !== null) {
            return $columnMode;
        }

        try {
            $plugin = FilamentPrivacyBlurPlugin::get();
            $panelMode = $plugin->getDefaultMode();
        } catch (\Throwable $e) {
            $panelMode = null;
        }

        $defaultMode = $panelMode ?? config('filament-privacy-blur.default_mode', 'blur_click');

        return PrivacyMode::tryFrom($defaultMode) ?? PrivacyMode::BlurClick;
    }

    public static function resolveBlurAmount(?int $columnBlur = null): int
    {
        if ($columnBlur !== null) {
            return $columnBlur;
        }

        try {
            $plugin = FilamentPrivacyBlurPlugin::get();
            $panelBlur = $plugin->getBlurAmount();
        } catch (\Throwable $e) {
            $panelBlur = null;
        }

        return $panelBlur ?? config('filament-privacy-blur.default_blur_amount', 4);
    }

    public static function resolveMaskStrategy(?string $columnStrategy = null): string
    {
        if ($columnStrategy !== null) {
            return $columnStrategy;
        }

        return config('filament-privacy-blur.default_mask_strategy', 'generic');
    }

    /**
     * Check if a column name is in the globally excepted list.
     */
    public static function isColumnExcepted(string $columnName): bool
    {
        try {
            $plugin = FilamentPrivacyBlurPlugin::get();
            $excepted = $plugin->getExceptColumns();
            if (empty($excepted)) {
                $excepted = config('filament-privacy-blur.except_columns', []);
            }
        } catch (\Throwable $e) {
            $excepted = config('filament-privacy-blur.except_columns', []);
        }

        return in_array($columnName, $excepted);
    }
}
