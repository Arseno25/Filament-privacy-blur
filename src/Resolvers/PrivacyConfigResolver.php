<?php

namespace Arseno25\FilamentPrivacyBlur\Resolvers;

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlurPlugin;
use Filament\Facades\Filament;

class PrivacyConfigResolver
{
    /**
     * Request-scoped memoization cache keyed by panel id.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $cache = [];

    /**
     * Reset the memoization cache. Intended for tests and between requests.
     */
    public static function flushCache(): void
    {
        self::$cache = [];
    }

    /**
     * Get the plugin instance from the current panel.
     */
    protected static function getPlugin(): ?FilamentPrivacyBlurPlugin
    {
        $panel = Filament::getCurrentPanel();

        if (! $panel) {
            return null;
        }

        $panelId = $panel->getId();

        if (array_key_exists('plugin', self::$cache[$panelId] ?? [])) {
            return self::$cache[$panelId]['plugin'];
        }

        try {
            /** @var FilamentPrivacyBlurPlugin $plugin */
            $plugin = $panel->getPlugin('filament-privacy-blur');
        } catch (\Throwable) {
            $plugin = null;
        }

        self::$cache[$panelId]['plugin'] = $plugin;

        return $plugin;
    }

    /**
     * Remember a computed value under a namespaced cache slot for the current panel.
     *
     * @template T
     *
     * @param  callable(): T  $resolver
     * @return T
     */
    private static function remember(string $key, callable $resolver): mixed
    {
        $panel = Filament::getCurrentPanel();
        $panelId = $panel?->getId() ?? '__no_panel__';

        if (array_key_exists($key, self::$cache[$panelId] ?? [])) {
            return self::$cache[$panelId][$key];
        }

        return self::$cache[$panelId][$key] = $resolver();
    }

    public static function isEnabledGlobally(): bool
    {
        return self::remember('enabled_globally', function () {
            $plugin = self::getPlugin();

            if ($plugin) {
                return $plugin->getIsEnabled();
            }

            return config('filament-privacy-blur.enabled', true);
        });
    }

    /**
     * Resolve the active privacy mode for a specific column context.
     */
    public static function resolveMode(?PrivacyMode $columnMode = null): PrivacyMode
    {
        if ($columnMode !== null) {
            return $columnMode;
        }

        return self::remember('default_mode', function () {
            $plugin = self::getPlugin();
            $panelMode = $plugin?->getDefaultMode();
            $defaultMode = $panelMode ?? config('filament-privacy-blur.default_mode', 'blur_click');

            return PrivacyMode::tryFrom($defaultMode) ?? PrivacyMode::BlurClick;
        });
    }

    public static function resolveBlurAmount(?int $columnBlur = null): int
    {
        if ($columnBlur !== null) {
            return $columnBlur;
        }

        return self::remember('default_blur_amount', function () {
            $plugin = self::getPlugin();
            $panelBlur = $plugin?->getBlurAmount();

            return $panelBlur ?? config('filament-privacy-blur.default_blur_amount', 4);
        });
    }

    public static function resolveMaskStrategy(?string $columnStrategy = null): string
    {
        if ($columnStrategy !== null) {
            return $columnStrategy;
        }

        return self::remember(
            'default_mask_strategy',
            fn () => config('filament-privacy-blur.default_mask_strategy', 'generic')
        );
    }

    public static function isColumnExcepted(string $columnName): bool
    {
        $excepted = self::remember('except_columns', function () {
            $plugin = self::getPlugin();
            $excepted = $plugin ? $plugin->getExceptColumns() : [];

            return empty($excepted)
                ? config('filament-privacy-blur.except_columns', [])
                : $excepted;
        });

        return in_array($columnName, $excepted, true);
    }

    public static function isResourceExcepted(string $resourceClass): bool
    {
        $excepted = self::remember('except_resources', function () {
            $plugin = self::getPlugin();
            $excepted = $plugin ? $plugin->getExceptResources() : [];

            return empty($excepted)
                ? config('filament-privacy-blur.except_resources', [])
                : $excepted;
        });

        return in_array($resourceClass, $excepted, true);
    }

    public static function isPanelExcepted(): bool
    {
        $panel = Filament::getCurrentPanel();
        if (! $panel) {
            return false;
        }

        return self::remember('panel_excepted', function () use ($panel) {
            $plugin = self::getPlugin();
            $excepted = $plugin ? $plugin->getExceptPanels() : [];

            if (empty($excepted)) {
                $excepted = config('filament-privacy-blur.except_panels', []);
            }

            return in_array($panel->getId(), $excepted, true);
        });
    }

    public static function isAuditEnabled(): bool
    {
        // Not cached: called at most once per audit request, and tests
        // flip the flag between requests in the same process.
        $plugin = self::getPlugin();
        $pluginAudit = $plugin?->getAuditEnabled();

        return $pluginAudit ?? config('filament-privacy-blur.audit_enabled', false);
    }
}
