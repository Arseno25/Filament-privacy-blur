<?php

namespace Arseno25\FilamentPrivacyBlur;

use Arseno25\FilamentPrivacyBlur\Filament\ColumnPrivacyMacros;
use Arseno25\FilamentPrivacyBlur\Testing\TestsFilamentPrivacyBlur;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
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
        $package->name(static::$name)
            ->hasRoute('web')
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
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        ColumnPrivacyMacros::boot();

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
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_privacy_reveal_logs_table',
        ];
    }
}
