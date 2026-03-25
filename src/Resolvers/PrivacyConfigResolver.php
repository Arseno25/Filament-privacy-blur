<?php

namespace Arseno25\FilamentPrivacyBlur\Resolvers;

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlurPlugin;
use Filament\Facades\Filament;

class PrivacyConfigResolver
{
    /**
     * Get the plugin instance from the current panel.
     */
    protected static function getPlugin(): ?FilamentPrivacyBlurPlugin
    {
        $panel = Filament::getCurrentPanel();

        if (! $panel) {
            return null;
        }

        try {
            /** @var FilamentPrivacyBlurPlugin $plugin */
            $plugin = $panel->getPlugin('filament-privacy-blur');

            return $plugin;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Determine if privacy features are globally enabled for the current panel.
     * Returns true if enabled in plugin OR if config doesn't explicitly disable it.
     */
    public static function isEnabledGlobally(): bool
    {
        $plugin = self::getPlugin();

        if ($plugin) {
            return $plugin->getIsEnabled();
        }

        // Fall back to config if no panel context (e.g., during testing or CLI)
        // Default to enabled if not explicitly disabled
        return config('filament-privacy-blur.enabled', true);
    }

    /**
     * Resolve the active privacy mode for a specific column context.
     */
    public static function resolveMode(?PrivacyMode $columnMode = null): PrivacyMode
    {
        if ($columnMode !== null) {
            return $columnMode;
        }

        $plugin = self::getPlugin();
        $panelMode = $plugin ? $plugin->getDefaultMode() : null;
        $defaultMode = $panelMode ?? config('filament-privacy-blur.default_mode', 'blur_click');

        return PrivacyMode::tryFrom($defaultMode) ?? PrivacyMode::BlurClick;
    }

    public static function resolveBlurAmount(?int $columnBlur = null): int
    {
        if ($columnBlur !== null) {
            return $columnBlur;
        }

        $plugin = self::getPlugin();
        $panelBlur = $plugin ? $plugin->getBlurAmount() : null;

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
        $plugin = self::getPlugin();
        $excepted = $plugin ? $plugin->getExceptColumns() : [];

        if (empty($excepted)) {
            $excepted = config('filament-privacy-blur.except_columns', []);
        }

        return in_array($columnName, $excepted);
    }
}
