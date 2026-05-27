<?php

namespace SaaSPlatform\Modules\Forms\Providers;

use Illuminate\Support\ServiceProvider;
use SaaSPlatform\Modules\Forms\Contracts\FormRepositoryInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormFieldRepositoryInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormSubmissionRepositoryInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormServiceInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormSiteServiceInterface;
use SaaSPlatform\Modules\Forms\Contracts\ModuleLifecycleInterface;
use SaaSPlatform\Modules\Forms\Repositories\FormRepository;
use SaaSPlatform\Modules\Forms\Repositories\FormFieldRepository;
use SaaSPlatform\Modules\Forms\Repositories\FormSubmissionRepository;
use SaaSPlatform\Modules\Forms\Services\FormService;
use SaaSPlatform\Modules\Forms\Services\FormSiteService;
use SaaSPlatform\Modules\Forms\Services\ModuleLifecycleService;
use SaaSPlatform\Modules\Forms\Services\FormsLicensingService;
use SaaSPlatform\Modules\Forms\Services\FormsGateService;
use SaaSPlatform\Modules\Forms\Services\FormsUpdateValidationService;
use SaaSPlatform\Modules\Forms\Services\FormsOverrideSafetyService;
use SaaSPlatform\Modules\Forms\Services\FormsRuntimeSafetyService;
use SaaSPlatform\Modules\Forms\Services\FormsMonitoringService;
use SaaSPlatform\Modules\Forms\Services\FormsCanonicalValidationService;
use SaaSPlatform\Modules\Forms\Services\FormsFrontendRuntimeService;
use SaaSPlatform\Modules\Forms\Services\FormsRuntimeUnificationService;
use SaaSPlatform\Modules\Forms\Services\PlatformContextService;
// Phase 9.0 services
use SaaSPlatform\Modules\Forms\Services\FormBuilderService;
use SaaSPlatform\Modules\Forms\Services\FormConditionalLogicService;
use SaaSPlatform\Modules\Forms\Services\FormAutomationService;
use SaaSPlatform\Modules\Forms\Services\FormAnalyticsService;
use SaaSPlatform\Modules\Forms\Services\FormTemplateService;
use SaaSPlatform\Modules\Forms\Services\FormsFrontendGovernanceValidationService;
use SaaSPlatform\Modules\Forms\Services\FormsFrontendParityValidationService;
use SaaSPlatform\Modules\Forms\Services\FormsAssetGovernanceService;
use SaaSPlatform\Modules\Forms\Services\FrontendAccessibilityValidationService;
use SaaSPlatform\Modules\Forms\Services\FormsRuntimeSelfHealingService;
use SaaSPlatform\Modules\Forms\Services\FormsVersionHistoryService;
use SaaSPlatform\Modules\Forms\Services\FormsVisualConsistencyService;
use SaaSPlatform\Modules\Forms\Services\FormsRuntimeStabilizationService;
// Phase 9.3 — Cross-Context Runtime Parity
use SaaSPlatform\Modules\Forms\Services\FrontendVisualParityEngine;
use SaaSPlatform\Modules\Forms\Services\FrontendRuntimeRecoveryService;
use SaaSPlatform\Modules\Forms\Services\CrossContextRuntimeValidationService;
use SaaSPlatform\Modules\Forms\Services\NullSiteRepository;
use SaaSPlatform\Modules\Forms\Services\NullTenantContext;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use Illuminate\Support\Facades\Blade;

class FormsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/forms.php',
            'forms'
        );

        // PHASE 6.95: Merge module manifest config (canonical metadata)
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/module-manifest.php',
            'module-manifest-forms'
        );

        // Bind repositories
        $this->app->bind(FormRepositoryInterface::class, FormRepository::class);
        $this->app->bind(FormFieldRepositoryInterface::class, FormFieldRepository::class);
        $this->app->bind(FormSubmissionRepositoryInterface::class, FormSubmissionRepository::class);

        // Bind services
        $this->app->bind(FormServiceInterface::class, FormService::class);
        $this->app->bind(FormSiteServiceInterface::class, FormSiteService::class);

        // Bind lifecycle contract
        $this->app->bind(ModuleLifecycleInterface::class, ModuleLifecycleService::class);

        // Bind FormsLicensingService: requires ModuleLicenseServiceInterface from host app
        $this->app->singleton(FormsLicensingService::class);
        $this->app->singleton(FormsGateService::class);
        $this->app->singleton(FormsUpdateValidationService::class);
        $this->app->singleton(FormsOverrideSafetyService::class);
        $this->app->singleton(FormsRuntimeSafetyService::class);
        $this->app->singleton(FormsMonitoringService::class);
        $this->app->singleton(FormsCanonicalValidationService::class);
        $this->app->singleton(FormsFrontendRuntimeService::class);

        // PHASE 6.95: Register PlatformContextService as singleton
        $this->app->singleton(PlatformContextService::class);

        // Phase 9.0 — Enterprise Form Builder services
        $this->app->singleton(FormBuilderService::class);
        $this->app->singleton(FormConditionalLogicService::class);
        $this->app->singleton(FormAutomationService::class);
        $this->app->singleton(FormAnalyticsService::class);
        $this->app->singleton(FormTemplateService::class);
        $this->app->singleton(FormsFrontendGovernanceValidationService::class);

        // Phase 9.1 — Frontend Parity Validation
        $this->app->singleton(FormsFrontendParityValidationService::class);

        // Phase 9.2 — Runtime Stabilization, Asset Governance, Accessibility, Self-Healing
        $this->app->singleton(FormsAssetGovernanceService::class);
        $this->app->singleton(FrontendAccessibilityValidationService::class);
        $this->app->singleton(FormsRuntimeSelfHealingService::class);
        $this->app->singleton(FormsVersionHistoryService::class);
        $this->app->singleton(FormsVisualConsistencyService::class);
        $this->app->singleton(FormsRuntimeStabilizationService::class);

        // Phase 9.3 — Cross-Context Runtime Parity
        $this->app->singleton(FrontendVisualParityEngine::class);
        $this->app->singleton(FrontendRuntimeRecoveryService::class);
        $this->app->singleton(CrossContextRuntimeValidationService::class);

        // Provide a no-op fallback when the host app doesn't bind SiteRepositoryInterface
        // (e.g. customer-site has no multi-site concept). Saas-platform overrides via
        // InfrastructureServiceProvider, which registers before this provider boots.
        if (! $this->app->bound(SiteRepositoryInterface::class)) {
            $this->app->bind(SiteRepositoryInterface::class, NullSiteRepository::class);
        }

        // Provide a no-op fallback when the host app doesn't bind TenantContextContract.
        // customer-site has its own TenantContext that does not implement this interface.
        if (! $this->app->bound(TenantContextContract::class)) {
            $this->app->bind(TenantContextContract::class, NullTenantContext::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'forms');

        // Load core-ui shared component namespace (Phase 9.1)
        // anonymousComponentNamespace mangles filesystem paths (converts / to dots);
        // anonymousComponentPath registers a real directory and hashes the prefix as a view namespace.
        Blade::anonymousComponentPath(__DIR__ . '/../../resources/views/core-ui/components', 'core-ui');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \SaaSPlatform\Modules\Forms\Console\Commands\InstallFormsModule::class,
                \SaaSPlatform\Modules\Forms\Console\Commands\ValidateFormsIsolationCommand::class,
                \SaaSPlatform\Modules\Forms\Console\Commands\FormsFrontendGovernanceCommand::class,
                \SaaSPlatform\Modules\Forms\Console\Commands\ValidateFormsFrontendParityCommand::class,
                // Phase 9.2 — Stability, Consistency, UX, Asset validation commands
                \SaaSPlatform\Modules\Forms\Console\Commands\ValidateFormsRuntimeStabilityCommand::class,
                \SaaSPlatform\Modules\Forms\Console\Commands\ValidateFormsFrontendConsistencyCommand::class,
                \SaaSPlatform\Modules\Forms\Console\Commands\ValidateFormsEnterpriseUxCommand::class,
                \SaaSPlatform\Modules\Forms\Console\Commands\ValidateFormsAssetsCommand::class,
                // Phase 9.3 — Cross-Context Parity + Runtime Recovery commands
                \SaaSPlatform\Modules\Forms\Console\Commands\ValidateFrontendParityCommand::class,
                \SaaSPlatform\Modules\Forms\Console\Commands\FrontendRuntimeDiagnosticsCommand::class,
            ]);
        }

        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/forms.php' => config_path('forms.php'),
        ], 'forms-config');

        // Publish module manifest (canonical metadata, replaces module-features/*.json)
        $this->publishes([
            __DIR__ . '/../../config/module-manifest.php' => config_path('module-manifest-forms.php'),
        ], 'forms-manifest');

        // Publish views (for override)
        $this->publishes([
            __DIR__ . '/../../resources/views' => resource_path('views/vendor/forms'),
        ], 'forms-views');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'forms-migrations');

        // PHASE 6.95 — TASK 2: Register Blade Context-Aware Directives
        Blade::if('platform', function () {
            return app(PlatformContextService::class)->isCentralAdmin();
        });

        Blade::if('tenant', function () {
            return app(PlatformContextService::class)->isTenantAdmin();
        });

        Blade::if('capability', function (string $capability) {
            return app(PlatformContextService::class)->hasCapability($capability);
        });

        // PHASE 6.95 — TASK 6: Register a view composer that resolves the
        // correct admin layout based on runtime context.
        // This allows all package views to use @extends(config('forms.layouts.admin'))
        // while the actual layout is resolved dynamically.
        view()->composer('forms::admin.*', function ($view) {
            $contextService = app(PlatformContextService::class);
            if ($contextService->isTenantAdmin()) {
                $tenantLayout = config('forms.layouts.admin_tenant', 'admin.layout');
                config(['forms.layouts.admin' => $tenantLayout]);
            }
        });
    }
}

