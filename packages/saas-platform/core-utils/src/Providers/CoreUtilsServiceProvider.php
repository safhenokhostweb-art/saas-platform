<?php

namespace SaaSPlatform\CoreUtils\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use SaaSPlatform\CoreUtils\Blade\AssetModuleDirective;
use SaaSPlatform\CoreUtils\Console\Commands\ModuleAssetsCheckCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftScanCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftBladesCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftContractsCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftControllersCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftOwnershipCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ValidateAuthorityCommand;
use SaaSPlatform\CoreUtils\Console\Commands\DriftAuthorityCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ModuleVersionCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ModuleBuildCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ModuleReleaseCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ModuleListCommand;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;
use SaaSPlatform\CoreUtils\Support\ReleaseManifest;
use SaaSPlatform\CoreUtils\Services\ModuleUpdateOrchestrator;
use SaaSPlatform\CoreUtils\Services\PlatformCompatibilityService;
use SaaSPlatform\CoreUtils\Services\AssetSymlinkService;
use SaaSPlatform\CoreUtils\Services\AssetVersionService;
use SaaSPlatform\CoreUtils\Services\SupportSlaService;
use SaaSPlatform\CoreUtils\Services\PlatformContext;
use SaaSPlatform\CoreUtils\Services\CapabilityResolver;
use SaaSPlatform\CoreUtils\Services\LayoutResolver;
use SaaSPlatform\CoreUtils\Services\MenuResolver;
use SaaSPlatform\CoreUtils\Services\ModuleRegistryService;

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

        // PHASE 6.95 — TASK 1: Register PlatformContext as singleton
        $this->app->singleton(PlatformContext::class, function ($app) {
            $tenantContext = null;
            if ($app->bound(\SaaSPlatform\CoreUtils\Contracts\TenantContextContract::class)) {
                $tenantContext = $app->make(\SaaSPlatform\CoreUtils\Contracts\TenantContextContract::class);
            }
            return new PlatformContext($tenantContext);
        });

        // PHASE 6.95 — TASK 3: Register CapabilityResolver
        $this->app->singleton(CapabilityResolver::class);

        // PHASE 6.95 — TASK 7: Register LayoutResolver
        $this->app->singleton(LayoutResolver::class);

        // PHASE 6.95 — TASK 8: Register MenuResolver
        $this->app->singleton(MenuResolver::class);

        // PHASE 6.95 — TASK 9: Register ModuleRegistryService
        $this->app->singleton(ModuleRegistryService::class);

        // PHASE 6.98 — TASK 1: Register ModuleVersionRegistry
        $this->app->singleton(ModuleVersionRegistry::class);

        // PHASE 6.98 — TASK 3: Register ReleaseManifest
        $this->app->singleton(ReleaseManifest::class);

        // PHASE 6.98 — TASK 8: Register PlatformCompatibilityService
        $this->app->singleton(PlatformCompatibilityService::class);

        // PHASE 6.98 — TASK 6: Register ModuleUpdateOrchestrator
        $this->app->singleton(ModuleUpdateOrchestrator::class);

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

        // PHASE 6.95 — TASK 2: Register Blade Authority Directives
        $this->registerBladeDirectives();

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleAssetsCheckCommand::class,
                DriftScanCommand::class,
                DriftBladesCommand::class,
                DriftControllersCommand::class,
                DriftContractsCommand::class,
                DriftOwnershipCommand::class,
                // PHASE 6.95 — TASK 11: Authority validation command
                ValidateAuthorityCommand::class,
                // PHASE 6.95 — TASK 12: Drift authority command
                DriftAuthorityCommand::class,
                // PHASE 6.98 — TASK 2: Module version management commands
                ModuleVersionCommand::class,
                ModuleBuildCommand::class,
                ModuleReleaseCommand::class,
                ModuleListCommand::class,
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

    /**
     * Register Blade authority directives.
     *
     * PHASE 6.95 — TASK 2:
     * - @platform / @endplatform: platform-only content
     * - @tenant / @endtenant: tenant-only content
     * - @central / @endcentral: central admin only
     * - @tenantadmin / @endtenantadmin: tenant admin only
     * - @platformfeature('key') / @endplatformfeature: feature-gated content
     */
    protected function registerBladeDirectives(): void
    {
        // @platform / @endplatform
        Blade::if('platform', function () {
            return app(PlatformContext::class)->isCentralAdmin();
        });

        // @tenant / @endtenant
        Blade::if('tenant', function () {
            return app(PlatformContext::class)->isTenant();
        });

        // @central / @endcentral
        Blade::if('central', function () {
            return app(PlatformContext::class)->isCentralAdmin();
        });

        // @tenantadmin / @endtenantadmin
        Blade::if('tenantadmin', function () {
            return app(PlatformContext::class)->isTenantAdmin();
        });

        // @platformfeature('feature-key') / @endplatformfeature
        Blade::if('platformfeature', function (string $featureKey) {
            return app(CapabilityResolver::class)->canAccess($featureKey);
        });
    }
}
