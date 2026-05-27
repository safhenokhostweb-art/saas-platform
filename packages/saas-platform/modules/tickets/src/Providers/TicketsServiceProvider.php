<?php

namespace SaaSPlatform\Modules\Tickets\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;

// Existing contracts
use SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketReplyRepositoryInterface;
// New repository contracts (PHASE 7.1)
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketStatusRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketSlaRepositoryInterface;
// Lifecycle contract
use SaaSPlatform\Modules\Tickets\Contracts\ModuleLifecycleInterface;

// Existing repository implementations
use SaaSPlatform\Modules\Tickets\Repositories\TicketRepository;
use SaaSPlatform\Modules\Tickets\Repositories\TicketReplyRepository;
// New repository implementations (PHASE 7.1)
use SaaSPlatform\Modules\Tickets\Repositories\TicketStatusRepository;
use SaaSPlatform\Modules\Tickets\Repositories\TicketSlaRepository;

// Existing service implementation
use SaaSPlatform\Modules\Tickets\Services\TicketService;

// 14 canonical services (PHASE 7.1)
use SaaSPlatform\Modules\Tickets\Services\TicketLifecycleService;
use SaaSPlatform\Modules\Tickets\Services\TicketAssignmentService;
use SaaSPlatform\Modules\Tickets\Services\TicketStatusService;
use SaaSPlatform\Modules\Tickets\Services\TicketPriorityService;
use SaaSPlatform\Modules\Tickets\Services\TicketSlaService;
use SaaSPlatform\Modules\Tickets\Services\TicketNotificationService;
use SaaSPlatform\Modules\Tickets\Services\TicketPortalService;
use SaaSPlatform\Modules\Tickets\Services\TicketActivityService;
use SaaSPlatform\Modules\Tickets\Services\TicketGateService;
use SaaSPlatform\Modules\Tickets\Services\TicketMonitoringService;
use SaaSPlatform\Modules\Tickets\Services\TicketUpdateValidationService;
use SaaSPlatform\Modules\Tickets\Services\TicketRuntimeSafetyService;
use SaaSPlatform\Modules\Tickets\Services\TicketOverrideSafetyService;
use SaaSPlatform\Modules\Tickets\Services\TicketCanonicalValidationService;
// Lifecycle implementation (PHASE 7.1)
use SaaSPlatform\Modules\Tickets\Services\TicketModuleLifecycle;
// PHASE 7.2 — Data migration services
use SaaSPlatform\Modules\Tickets\Services\TicketDataMigrationService;
use SaaSPlatform\Modules\Tickets\Services\TicketIntegrityValidationService;
use SaaSPlatform\Modules\Tickets\Services\TicketRollbackService;
use SaaSPlatform\Modules\Tickets\Services\TicketCanonicalRuntimeValidationService;
// PHASE 7.2 — Artisan commands
use SaaSPlatform\Modules\Tickets\Console\Commands\ValidateRuntimeAuthorityCommand;
use SaaSPlatform\Modules\Tickets\Console\Commands\MigrateTicketDataCommand;
// PHASE 9.1 — Frontend governance
use SaaSPlatform\Modules\Tickets\Governance\TicketsFrontendGovernanceValidationService;
use SaaSPlatform\Modules\Tickets\Console\Commands\ValidateFrontendGovernanceCommand;
// PHASE 9.3 — Timeline + Internal Notes services
use SaaSPlatform\Modules\Tickets\Services\TicketTimelineService;
use SaaSPlatform\Modules\Tickets\Services\TicketInternalNotesService;

// Models and Policies (PHASE 7.1)
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;
use SaaSPlatform\Modules\Tickets\Policies\TicketPolicy;
use SaaSPlatform\Modules\Tickets\Policies\TicketReplyPolicy;
use SaaSPlatform\Modules\Tickets\Policies\TicketAdminPolicy;

// CoreUtils context service for view composer
use SaaSPlatform\CoreUtils\Services\PlatformContext;

class TicketsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Config must be merged first (views + routes need it at boot time)
        $this->mergeConfigFrom(__DIR__ . '/../../config/tickets.php', 'tickets');
        $this->mergeConfigFrom(__DIR__ . '/../../config/module-manifest.php', 'module-manifest-tickets');

        // --- Repository contracts ---
        $this->app->bind(TicketRepositoryInterface::class, TicketRepository::class);
        $this->app->bind(TicketReplyRepositoryInterface::class, TicketReplyRepository::class);
        $this->app->bind(TicketStatusRepositoryInterface::class, TicketStatusRepository::class);
        $this->app->bind(TicketSlaRepositoryInterface::class, TicketSlaRepository::class);

        // --- Service contracts ---
        $this->app->bind(TicketServiceInterface::class, TicketService::class);

        // --- Lifecycle contract ---
        $this->app->bind(ModuleLifecycleInterface::class, TicketModuleLifecycle::class);

        // SMS service singleton (used by TicketSmsController + TicketNotificationService)
        $this->app->singleton(\SaaSPlatform\Modules\Tickets\Services\TicketSmsService::class);

        // --- 14 canonical services as singletons ---
        $this->app->singleton(TicketLifecycleService::class);
        $this->app->singleton(TicketAssignmentService::class);
        $this->app->singleton(TicketStatusService::class);
        $this->app->singleton(TicketPriorityService::class);
        $this->app->singleton(TicketSlaService::class);
        $this->app->singleton(TicketNotificationService::class, function ($app) {
            return new TicketNotificationService(
                $app->make(\SaaSPlatform\Modules\Tickets\Services\TicketSmsService::class)
            );
        });
        $this->app->singleton(TicketPortalService::class);
        $this->app->singleton(TicketActivityService::class);
        $this->app->singleton(TicketGateService::class);
        $this->app->singleton(TicketMonitoringService::class);
        $this->app->singleton(TicketUpdateValidationService::class);
        $this->app->singleton(TicketRuntimeSafetyService::class);
        $this->app->singleton(TicketOverrideSafetyService::class);
        $this->app->singleton(TicketCanonicalValidationService::class);

        // PHASE 7.2 — Data migration services
        $this->app->singleton(TicketDataMigrationService::class);
        $this->app->singleton(TicketIntegrityValidationService::class);
        $this->app->singleton(TicketRollbackService::class);
        $this->app->singleton(TicketCanonicalRuntimeValidationService::class);

        // PHASE 9.1 — Frontend governance
        $this->app->singleton(TicketsFrontendGovernanceValidationService::class);

        // PHASE 9.3 — Timeline + Internal Notes
        $this->app->singleton(TicketTimelineService::class);
        $this->app->singleton(TicketInternalNotesService::class);

        // PHASE 9.4 — Analytics dashboard
        $this->app->bind(
            \SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketAnalyticsController::class,
            function ($app) {
                return new \SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketAnalyticsController(
                    $app->make(\SaaSPlatform\CoreUtils\Analytics\OperationalDashboardService::class)
                );
            }
        );
    }

    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerViews();
        $this->registerBladeDirectives();
        $this->registerMigrations();
        $this->registerPolicies();
        $this->registerPublishables();
        $this->registerModuleAssets();
        $this->registerViewComposers();
        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ValidateRuntimeAuthorityCommand::class,
                MigrateTicketDataCommand::class,
                \SaaSPlatform\Modules\Tickets\Console\Commands\ValidateTicketsIsolationCommand::class,
                ValidateFrontendGovernanceCommand::class,
            ]);
        }
    }

    protected function registerRoutes(): void
    {
        // Three canonical route files replace the legacy Routes/web.php
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/admin.php');
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/site.php');
        $this->loadRoutesFrom(__DIR__ . '/../../Routes/api.php');
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../Resources/views', 'tickets');

        // Share the Forms package core-ui:: component namespace — registered once
        // by FormsServiceProvider when forms module is loaded. If forms is absent,
        // fall back to tickets own resources. This prevents "component not found" in
        // tickets-only deployments while keeping forms as the single authority.
        if (! $this->app->bound('core-ui.namespace.registered')) {
            Blade::anonymousComponentNamespace('tickets::core-ui.components', 'core-ui');
            $this->app->instance('core-ui.namespace.registered', true);
        }
    }

    protected function registerBladeDirectives(): void
    {
        // Mirror the @platform / @tenant / @capability directives from FormsServiceProvider.
        // Only register if not already registered (Forms module may register first).
        if (! Blade::getCustomDirectives()['platform'] ?? true) {
            return;
        }

        $context = function () {
            if ($this->app->bound(\SaaSPlatform\CoreUtils\Services\PlatformContext::class)) {
                return $this->app->make(\SaaSPlatform\CoreUtils\Services\PlatformContext::class);
            }
            return null;
        };

        Blade::if('platform', function () use ($context) {
            return $context()?->isCentralAdmin() ?? false;
        });

        Blade::if('tenant', function () use ($context) {
            return $context()?->isTenantAdmin() ?? true;
        });

        Blade::if('capability', function (string $cap) use ($context) {
            return $context()?->hasCapability($cap) ?? false;
        });
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../Database/Migrations');
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(TicketReply::class, TicketReplyPolicy::class);
    }

    protected function registerPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/tickets.php' => config_path('tickets.php'),
        ], 'tickets-config');

        $this->publishes([
            __DIR__ . '/../../config/module-manifest.php' => config_path('module-manifest-tickets.php'),
        ], 'tickets-manifest');

        $this->publishes([
            __DIR__ . '/../../Resources/views' => resource_path('views/vendor/tickets'),
        ], 'tickets-views');

        $this->publishes([
            __DIR__ . '/../../Database/Migrations' => database_path('migrations'),
        ], 'tickets-migrations');

        $this->publishes([
            __DIR__ . '/../../Resources/assets' => public_path('vendor/tickets'),
        ], 'tickets-assets');
    }

    protected function registerModuleAssets(): void
    {
        // PHASE 8.0 FIX: production guard removed — assets must be registered in all environments.
        // The guard was incorrectly skipping asset registration in production.
        $existingModules = config('module-assets.modules', []);

        foreach ($existingModules as $module) {
            if (($module['name'] ?? '') === 'tickets') {
                return;
            }
        }

        $existingModules[] = [
            'name'   => 'tickets',
            'source' => __DIR__ . '/../../Resources/assets',
        ];

        config(['module-assets.modules' => $existingModules]);
    }

    protected function registerViewComposers(): void
    {
        // Dynamically adjust the admin layout when running in tenant-admin context.
        // Matches the FormsServiceProvider pattern (PHASE 6.95).
        view()->composer('tickets::admin.*', function ($view) {
            if (! $this->app->bound(PlatformContext::class)) {
                return;
            }

            $context = $this->app->make(PlatformContext::class);

            if ($context->isTenantAdmin()) {
                $tenantLayout = config('tickets.layouts.admin_tenant', 'admin.layout');
                config(['tickets.layouts.admin' => $tenantLayout]);
            }
        });
    }
}
