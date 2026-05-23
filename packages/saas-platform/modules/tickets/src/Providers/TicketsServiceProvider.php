<?php

namespace SaaSPlatform\Modules\Tickets\Providers;

use Illuminate\Support\ServiceProvider;
use SaaSPlatform\CoreUtils\Services\AssetSymlinkService;

class TicketsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerViews();
        $this->registerMigrations();
        $this->registerConfig();
        $this->registerAssets();

        // Register this module's assets for auto-symlink in dev/staging
        $this->registerModuleAssets();
    }

    /**
     * Register the module routes.
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/web.php');
    }

    /**
     * Register the module views.
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../Resources/views', 'tickets');
    }

    /**
     * Register the module migrations.
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../Database/Migrations');
    }

    /**
     * Register the module config.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/tickets.php',
            'tickets'
        );
    }

    /**
     * Register publishable assets.
     *
     * Run `php artisan vendor:publish --tag=tickets-assets` to publish
     * the module's CSS and other assets to the consuming project.
     */
    protected function registerAssets(): void
    {
        $this->publishes([
            __DIR__ . '/../../Resources/assets' => public_path('vendor/tickets'),
        ], 'tickets-assets');
    }

    /**
     * Register this module's assets for the auto-symlink pipeline.
     *
     * In non-production environments, the EnsureModuleAssetsLinked middleware
     * will automatically create symlinks so assets are available without
     * running vendor:publish manually.
     */
    protected function registerModuleAssets(): void
    {
        // Only register in non-production environments
        if (app()->environment('production')) {
            return;
        }

        $moduleName = 'tickets';
        $sourcePath = __DIR__ . '/../../Resources/assets';

        // Register with the AssetSymlinkService on the next request
        // via the module-assets config merge
        $existingModules = config('module-assets.modules', []);

        // Check if already registered to avoid duplicates
        $alreadyRegistered = false;
        foreach ($existingModules as $module) {
            if (($module['name'] ?? '') === $moduleName) {
                $alreadyRegistered = true;
                break;
            }
        }

        if (! $alreadyRegistered) {
            $existingModules[] = [
                'name' => $moduleName,
                'source' => $sourcePath,
            ];

            config(['module-assets.modules' => $existingModules]);
        }
    }
}
