<?php

namespace SaaSPlatform\CoreUtils\Providers;

use Illuminate\Support\ServiceProvider;
use SaaSPlatform\CoreUtils\Blade\AssetModuleDirective;
use SaaSPlatform\CoreUtils\Console\Commands\ModuleAssetsCheckCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftScanCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftBladesCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftControllersCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftContractsCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftOwnershipCommand;
use SaaSPlatform\CoreUtils\Services\AssetSymlinkService;
use SaaSPlatform\CoreUtils\Services\AssetVersionService;
use SaaSPlatform\CoreUtils\Services\SupportSlaService;

class CoreUtilsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register SupportSlaService
        $this->app->singleton(SupportSlaService::class);

        // Register AssetSymlinkService
        $this->app->singleton(AssetSymlinkService::class, function () {
            return new AssetSymlinkService();
        });

        // Register AssetVersionService
        $this->app->singleton(AssetVersionService::class, function () {
            return new AssetVersionService();
        });

        // Merge configs
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/feature-flags.php',
            'feature-flags'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/module-assets.php',
            'module-assets'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Blade directive @asset_module
        AssetModuleDirective::register();

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleAssetsCheckCommand::class,
                DriftScanCommand::class,
                DriftBladesCommand::class,
                DriftControllersCommand::class,
                DriftContractsCommand::class,
                DriftOwnershipCommand::class,
            ]);
        }

        // Publish configs
        $this->publishes([
            __DIR__ . '/../../config/feature-flags.php' => config_path('feature-flags.php'),
        ], 'core-utils-config');

        $this->publishes([
            __DIR__ . '/../../config/module-assets.php' => config_path('module-assets.php'),
        ], 'core-utils-config');
    }
}
