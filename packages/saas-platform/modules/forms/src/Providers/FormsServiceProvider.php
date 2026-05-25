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

        // Register production hardening services (singletons for consistent state)
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'forms');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \SaaSPlatform\Modules\Forms\Console\Commands\InstallFormsModule::class,
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

