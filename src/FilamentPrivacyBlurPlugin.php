<?php

namespace Arseno25\FilamentPrivacyBlur;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

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

    protected ?bool $auditEnabled = null;

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        // Register Global Reveal Toggle into topbar
        FilamentView::registerRenderHook(
            PanelsRenderHook::GLOBAL_SEARCH_AFTER,
            fn (): string => view('filament-privacy-blur::toggle-button')->render()
        );

        // Register Alpine.js interaction script in footer
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => view('filament-privacy-blur::alpine-script')->render()
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

    public function enableAudit(bool $condition = true): static
    {
        $this->auditEnabled = $condition;

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

    public function getAuditEnabled(): ?bool
    {
        return $this->auditEnabled;
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
