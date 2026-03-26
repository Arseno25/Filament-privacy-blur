<?php

namespace Arseno25\FilamentPrivacyBlur;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\View;

class FilamentPrivacyBlurPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-privacy-blur';
    }

    protected bool $isEnabled = true;

    protected ?string $defaultMode = null;

    protected ?int $blurAmount = null;

    protected array $exceptColumns = [];

    protected array $exceptResources = [];

    protected array $exceptPanels = [];

    protected ?bool $auditEnabled = null;

    protected bool $showGlobalRevealToggle = true;

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        // If the plugin is disabled, skip all render hooks
        if (! $this->isEnabled) {
            return;
        }

        // Register Global Reveal Toggle into topbar (respects icon_trigger_enabled config)
        $iconTriggerEnabled = config('filament-privacy-blur.icon_trigger_enabled', true);
        if ($iconTriggerEnabled && $this->showGlobalRevealToggle) {
            FilamentView::registerRenderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                function (): string {
                    // The global reveal toggle button should always be rendered.
                    // Whether it functions is controlled by:
                    // 1. The presence of privacy-enabled fields on the page (determined at render time)
                    // 2. The `data-privacy-can-globally-reveal` attributes on each field
                    // 3. User authorization for each individual field
                    //
                    // The JavaScript will only reveal fields that have `data-privacy-can-globally-reveal="true"`,
                    // which is set server-side based on actual field authorization.
                    // This ensures security: no global reveal bypass is possible.
                    return view('filament-privacy-blur::toggle-button')->render();
                }
            );
        }

        // Share plugin configuration with all views
        View::composer('filament-privacy-blur::*', function ($view) {
            $view->with('privacyBlurPlugin', $this);
        });

        // Register Alpine.js interaction script in footer
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            function (): string {
                return view('filament-privacy-blur::alpine-script')->render();
            }
        );
    }

    public function enabled(bool $condition = true): static
    {
        $this->isEnabled = $condition;

        return $this;
    }

    public function defaultMode(string $mode): static
    {
        $this->defaultMode = $mode;

        return $this;
    }

    public function blurAmount(int $amount): static
    {
        $this->blurAmount = $amount;

        return $this;
    }

    public function exceptColumns(array $columns): static
    {
        $this->exceptColumns = $columns;

        return $this;
    }

    public function exceptResources(array $resources): static
    {
        $this->exceptResources = $resources;

        return $this;
    }

    public function exceptPanels(array $panels): static
    {
        $this->exceptPanels = $panels;

        return $this;
    }

    public function enableAudit(bool $condition = true): static
    {
        $this->auditEnabled = $condition;

        return $this;
    }

    public function showGlobalRevealToggle(bool $condition = true): static
    {
        $this->showGlobalRevealToggle = $condition;

        return $this;
    }

    public function hideGlobalRevealToggle(): static
    {
        $this->showGlobalRevealToggle = false;

        return $this;
    }

    public function getIsEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function getDefaultMode(): ?string
    {
        return $this->defaultMode;
    }

    public function getBlurAmount(): ?int
    {
        return $this->blurAmount;
    }

    public function getExceptColumns(): array
    {
        return $this->exceptColumns;
    }

    public function getExceptResources(): array
    {
        return $this->exceptResources;
    }

    public function getExceptPanels(): array
    {
        return $this->exceptPanels;
    }

    public function getAuditEnabled(): ?bool
    {
        return $this->auditEnabled;
    }

    public function getShowGlobalRevealToggle(): bool
    {
        return $this->showGlobalRevealToggle;
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(self::make()->getId());

        return $plugin;
    }
}
