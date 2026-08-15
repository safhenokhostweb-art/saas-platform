<?php

namespace App\Providers;

use App\Adapters\ActivityLoggerAdapter;
use App\Adapters\AuthUserResolverAdapter;
use App\Adapters\CaptchaServiceAdapter;
use App\Adapters\JalaliDateFormatterAdapter;
use App\Adapters\ModuleLicenseAdapter;
use App\Adapters\ModuleReleaseProducerAdapter;
use App\Adapters\SiteRepositoryAdapter;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;
use SaaSPlatform\CoreUtils\Contracts\Platform\UserModelInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\AuthUserResolverInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\CaptchaServiceInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\DateFormatterInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleReleaseProducerInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;
use App\Services\ModuleEcosystem\Contracts\ModuleEntitlementResolverContract;
use App\Services\ModuleEcosystem\EntitlementResolver;

/**
 * Binds all package contracts to their host-app adapter implementations.
 * Packages ONLY depend on contracts. This provider is the single place
 * where the host app wires concrete implementations.
 *
 * Load order: this provider MUST be registered before package providers
 * so contracts are already bound when package service providers boot.
 */
class InfrastructureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Platform model interfaces
        $this->app->bind(SiteModelInterface::class, Site::class);
        $this->app->bind(UserModelInterface::class, User::class);

        // TenantContext — bind the contract to the concrete singleton
        $this->app->singleton(TenantContextContract::class, function ($app) {
            return $app->make(\App\Services\TenantContext::class);
        });

        // SiteContext is deliberately distinct from the legacy reseller-aware TenantContext.
        $this->app->singleton(SiteContextContract::class, \App\Services\SiteContext::class);
        $this->app->singleton(ModuleEntitlementResolverContract::class, EntitlementResolver::class);

        // Repository adapters
        $this->app->singleton(SiteRepositoryInterface::class, SiteRepositoryAdapter::class);

        // Service adapters
        $this->app->singleton(CaptchaServiceInterface::class, CaptchaServiceAdapter::class);
        $this->app->singleton(ActivityLoggerInterface::class, ActivityLoggerAdapter::class);
        $this->app->singleton(ModuleLicenseServiceInterface::class, ModuleLicenseAdapter::class);
        $this->app->singleton(ModuleReleaseProducerInterface::class, ModuleReleaseProducerAdapter::class);
        $this->app->singleton(DateFormatterInterface::class, JalaliDateFormatterAdapter::class);
        $this->app->singleton(AuthUserResolverInterface::class, AuthUserResolverAdapter::class);
    }
}
