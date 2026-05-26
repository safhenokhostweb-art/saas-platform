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
use SaaSPlatform\CoreUtils\Services\SharedLayoutResolver;
use SaaSPlatform\CoreUtils\Services\ModuleNavigationService;
use SaaSPlatform\CoreUtils\Services\OverrideAuthorityValidator;
use SaaSPlatform\CoreUtils\Services\CanonicalFrontendValidationService;
use SaaSPlatform\CoreUtils\Services\CustomerPackageRuntimeValidator;
use SaaSPlatform\CoreUtils\Services\CanonicalUiParityValidator;
use SaaSPlatform\CoreUtils\Console\Commands\ValidateUiAuthorityCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ValidateTenantRuntimeCommand;
use SaaSPlatform\CoreUtils\Console\Commands\ValidateDistributionCommand;
use SaaSPlatform\CoreUtils\Services\PackageDistributionValidationService;
// Phase 8.2 — Deployment services
use SaaSPlatform\CoreUtils\Services\Deployment\DeploymentOrchestrator;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseLockManager;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestManager;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestValidator;
use SaaSPlatform\CoreUtils\Services\Deployment\DeploymentTransactionLogger;
use SaaSPlatform\CoreUtils\Services\Deployment\DeploymentHealthVerifier;
use SaaSPlatform\CoreUtils\Services\Migration\MigrationSafetyService;
use SaaSPlatform\CoreUtils\Services\Migration\SchemaDriftDetector;
use SaaSPlatform\CoreUtils\Services\Migration\TenantMigrationCoordinator;
use SaaSPlatform\CoreUtils\Services\Maintenance\TenantMaintenanceManager;
use SaaSPlatform\CoreUtils\Services\Queue\QueueRuntimeCompatibilityService;
use SaaSPlatform\CoreUtils\Services\Assets\AssetVersionValidator;
use SaaSPlatform\CoreUtils\Services\Health\RuntimeHealthService;
use SaaSPlatform\CoreUtils\Services\Rollback\RollbackCoordinator;
use SaaSPlatform\CoreUtils\Services\Rollback\ReleaseRollbackValidator;
use SaaSPlatform\CoreUtils\Services\Rollback\FailedDeploymentRecoveryService;
use SaaSPlatform\CoreUtils\Services\DeploymentGovernanceValidationService;
// Phase 8.2 — Commands
use SaaSPlatform\CoreUtils\Console\Commands\Deployment\DeploymentLockCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Deployment\DeploymentUnlockCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Deployment\DeploymentStatusCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Deployment\DeploymentRollbackCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Deployment\ValidateGovernanceCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Migration\MigrationValidateSafetyCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Migration\MigrationDetectDriftCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Maintenance\TenantDownCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Maintenance\TenantUpCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Maintenance\TenantMaintenanceStatusCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Queue\QueueValidateRuntimeCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Queue\QueueDrainSafeCommand;
use SaaSPlatform\CoreUtils\Console\Commands\Health\PlatformHealthCommand;

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

        // PHASE 7.15 — TASK 2: SharedLayoutResolver (module-agnostic, replaces LayoutResolver)
        $this->app->singleton(SharedLayoutResolver::class);

        // PHASE 7.15 — TASK 5: ModuleNavigationService
        $this->app->singleton(ModuleNavigationService::class);

        // PHASE 7.15 — TASK 7: OverrideAuthorityValidator
        $this->app->singleton(OverrideAuthorityValidator::class);

        // PHASE 7.15 — TASK 8: CanonicalFrontendValidationService
        $this->app->singleton(CanonicalFrontendValidationService::class);

        // PHASE 7.25 — TASK 3+6: Tenant runtime validators
        $this->app->singleton(CustomerPackageRuntimeValidator::class);
        $this->app->singleton(CanonicalUiParityValidator::class);

        // PHASE 6.98 — TASK 1: Register ModuleVersionRegistry
        $this->app->singleton(ModuleVersionRegistry::class);

        // PHASE 6.98 — TASK 3: Register ReleaseManifest
        $this->app->singleton(ReleaseManifest::class);

        // PHASE 6.98 — TASK 8: Register PlatformCompatibilityService
        $this->app->singleton(PlatformCompatibilityService::class);

        // PHASE 8.1 — TASK 9: Distribution validation service
        $this->app->singleton(PackageDistributionValidationService::class);

        // PHASE 8.2 — Deployment subsystem singletons
        $this->app->singleton(ReleaseLockManager::class);
        $this->app->singleton(ReleaseManifestManager::class);
        $this->app->singleton(ReleaseManifestValidator::class);
        $this->app->singleton(DeploymentTransactionLogger::class);
        $this->app->singleton(DeploymentHealthVerifier::class);
        $this->app->singleton(DeploymentOrchestrator::class);
        $this->app->singleton(MigrationSafetyService::class);
        $this->app->singleton(SchemaDriftDetector::class);
        $this->app->singleton(TenantMigrationCoordinator::class);
        $this->app->singleton(TenantMaintenanceManager::class);
        $this->app->singleton(QueueRuntimeCompatibilityService::class);
        $this->app->singleton(AssetVersionValidator::class);
        $this->app->singleton(RuntimeHealthService::class);
        $this->app->singleton(ReleaseRollbackValidator::class);
        $this->app->singleton(RollbackCoordinator::class);
        $this->app->singleton(FailedDeploymentRecoveryService::class);
        $this->app->singleton(DeploymentGovernanceValidationService::class);

        // PHASE 6.98 — TASK 6: Register ModuleUpdateOrchestrator
        $this->app->singleton(ModuleUpdateOrchestrator::class);

        // Merge configs
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/saas-platform.php',
            'saas-platform'
        );

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
        // PHASE 7.15 — TASK 4: Register core-ui shared Blade components
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'core-ui');

        // Publish shared components for consuming projects
        $this->publishes([
            __DIR__ . '/../../resources/views' => resource_path('views/vendor/core-ui'),
        ], 'core-ui-views');

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
                // PHASE 7.15 — TASK 8: UI authority validation command
                ValidateUiAuthorityCommand::class,
                // PHASE 7.25 — TASK 9: Tenant runtime authority command
                ValidateTenantRuntimeCommand::class,
                // PHASE 8.1 — TASK 9: Package distribution validation command
                ValidateDistributionCommand::class,
                // PHASE 8.2 — Deployment commands
                DeploymentLockCommand::class,
                DeploymentUnlockCommand::class,
                DeploymentStatusCommand::class,
                DeploymentRollbackCommand::class,
                ValidateGovernanceCommand::class,
                MigrationValidateSafetyCommand::class,
                MigrationDetectDriftCommand::class,
                TenantDownCommand::class,
                TenantUpCommand::class,
                TenantMaintenanceStatusCommand::class,
                QueueValidateRuntimeCommand::class,
                QueueDrainSafeCommand::class,
                PlatformHealthCommand::class,
            ]);
        }

        // Publish configs
        $this->publishes([
            __DIR__ . '/../../config/saas-platform.php' => config_path('saas-platform.php'),
        ], 'core-utils-config');

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
