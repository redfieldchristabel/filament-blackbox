<?php

namespace Blackbox\FilamentBlackbox;

use Blackbox\FilamentBlackbox\Commands\FilamentBlackboxCommand;
use Blackbox\FilamentBlackbox\Testing\TestsFilamentBlackbox;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Filesystem\Filesystem;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentBlackboxServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-blackbox';

    public static string $viewNamespace = 'filament-blackbox';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('redfieldchristabel/filament-blackbox');
            });

        $configFileName = $package->shortName();

        $package->hasConfigFile('blackbox');
        // if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
        //     $package->hasConfigFile();
        // }

        // if (file_exists($package->basePath('/../database/migrations'))) {
        //     $package->hasMigrations($this->getMigrations());
        // }

        // if (file_exists($package->basePath('/../resources/lang'))) {
        //     $package->hasTranslations();
        // }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }

        $helpers = $this->packagePath('src/helpers.php');

        if (file_exists($helpers)) {
            require_once $helpers;
        }
    }

    public function packageRegistered(): void {}

    protected function packagePath(string $path): string
    {
        return __DIR__ . '/../' . $path;
    }

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
                    $file->getRealPath() => base_path("stubs/filament-blackbox/{$file->getFilename()}"),
                ], 'filament-blackbox-stubs');
            }
        }

        // // Register Livewire Components
        // Livewire::addComponent(
        //     name: 'audit-item',
        //     viewPath: resource_path(__DIR__ . '/../resources/views/components/⚡audit-item.blade.php')
        // );

        Livewire::addNamespace(
            namespace: 'blackbox',
            viewPath: $this->packagePath('resources/views/components')
        );

        // Testing
        Testable::mixin(new TestsFilamentBlackbox);

    }

    protected function getAssetPackageName(): ?string
    {
        return 'redfieldchristabel/filament-blackbox';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            // AlpineComponent::make('filament-blackbox', __DIR__ . '/../resources/dist/components/filament-blackbox.js'),
            Css::make('filament-blackbox-styles', __DIR__ . '/../resources/dist/filament-blackbox.css'),
            Js::make('filament-blackbox-scripts', __DIR__ . '/../resources/dist/filament-blackbox.js'),

        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            FilamentBlackboxCommand::class,
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
            'create_filament-blackbox_table',
        ];
    }
}
