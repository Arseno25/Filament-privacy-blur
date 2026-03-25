<?php

namespace Arseno25\FilamentPrivacyBlur;

use Arseno25\FilamentPrivacyBlur\Commands\FilamentPrivacyBlurCommand;
use Arseno25\FilamentPrivacyBlur\Filament\ColumnPrivacyMacros;
use Arseno25\FilamentPrivacyBlur\Testing\TestsFilamentPrivacyBlur;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Filesystem\Filesystem;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentPrivacyBlurServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-privacy-blur';

    public static string $viewNamespace = 'filament-privacy-blur';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasRoute('web') // Register routes/web.php
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('arseno25/filament-privacy-blur');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void {}

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/filament-privacy-blur/{$file->getFilename()}"),
                ], 'filament-privacy-blur-stubs');
            }
        }

        // Register Global Reveal Toggle into Filament Panels
        FilamentView::registerRenderHook(
            PanelsRenderHook::GLOBAL_SEARCH_AFTER,
            fn (): string => view('filament-privacy-blur::toggle-button')->render()
        );

        // Register Alpine.js component script
        FilamentView::registerRenderHook(
            PanelsRenderHook::FOOTER,
            fn (): string => view('filament-privacy-blur::alpine-script')->render()
        );

        ColumnPrivacyMacros::boot();

        // Testing
        Testable::mixin(new TestsFilamentPrivacyBlur);
    }

    protected function getAssetPackageName(): ?string
    {
        return 'arseno25/filament-privacy-blur';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            Css::make('filament-privacy-blur-styles', __DIR__ . '/../resources/css/filament-privacy-blur.css'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            FilamentPrivacyBlurCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_privacy_reveal_logs_table',
        ];
    }
}
