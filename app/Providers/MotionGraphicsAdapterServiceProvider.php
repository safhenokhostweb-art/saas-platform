<?php

namespace App\Providers;

use App\Modules\MotionGraphics\Adapters\MotionAssetResolverAdapter;
use App\Modules\MotionGraphics\Adapters\MotionAuthorizationAdapter;
use App\Modules\MotionGraphics\Adapters\MotionRuntimeProjectionAdapter;
use App\Modules\MotionGraphics\Adapters\SiteAssetPackageResolverAdapter;
use App\Modules\MotionGraphics\Adapters\TemplateAssetResolverAdapter;
use App\Modules\MotionGraphics\Bindings\AuditLoggerAdapter;
use App\Modules\MotionGraphics\Bindings\PermissionGatewayAdapter;
use App\Modules\MotionGraphics\Bindings\PlatformAdminResolverAdapter;
use App\Modules\MotionGraphics\Bindings\SiteResolverAdapter;
use App\Modules\MotionGraphics\Contracts\MotionAssetResolverInterface;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\Contracts\MotionRuntimeProjectionInterface;
use App\Modules\MotionGraphics\Contracts\SiteAssetPackageResolverInterface;
use App\Modules\MotionGraphics\Contracts\TemplateAssetResolverInterface;
use Illuminate\Support\ServiceProvider;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAuditLoggerContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPermissionGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionVersionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\SiteAssetPackageReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\TemplateAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\SiteResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Support\Read\DatabaseMotionAssetReader;
use SaaSPlatform\Modules\MotionGraphics\Support\Read\DatabaseMotionVersionReader;
use SaaSPlatform\Modules\MotionGraphics\Support\Read\PublishedRuntimeProjectionProvider;
use SaaSPlatform\Modules\MotionGraphics\Support\Read\SitePackageReader;
use SaaSPlatform\Modules\MotionGraphics\Support\Read\TemplateAssetReader;

/**
 * Phase 1 — Motion Graphics adapter foundation. Phase 2 — read contracts
 * & runtime projection bridge.
 *
 * This is the single place the host app wires the standalone Motion
 * Graphics module's low-level contracts to real platform implementations,
 * binds the module's provider-neutral read contracts
 * (docs/motion-read-contracts/) to its own database-backed
 * implementations, and binds the Laravel-side adapter interfaces
 * described in docs/motion-saas-integration/LARAVEL_ADAPTER_CONTRACT.md.
 *
 * Registered before SaaSPlatform\Modules\MotionGraphics\Providers\
 * MotionGraphicsServiceProvider in config/app.php so the module's own
 * bindDefault() calls see these bindings already present and never
 * overwrite them. Every adapter-facing binding here fails closed
 * (deny/no-op) whenever the `motion_graphics_integration` feature flag is
 * disabled — the module's runtime behavior is unchanged either way,
 * because its own `motion-graphics.enabled` config (routes, views,
 * migrations, Gate policy, publishing) remains false in this phase. The
 * read-contract bindings query the module's own tables directly (that is
 * the module reading its own data, not the adapter reaching into module
 * internals) and require the module's migrations to have been run before
 * they can return real data — activating those migrations on the host is
 * a later phase's concern.
 */
class MotionGraphicsAdapterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Real implementations of the standalone module's own low-level contracts.
        $this->app->singleton(SiteResolverContract::class, SiteResolverAdapter::class);
        $this->app->singleton(PlatformAdminResolverContract::class, PlatformAdminResolverAdapter::class);
        $this->app->singleton(MotionPermissionGatewayContract::class, PermissionGatewayAdapter::class);
        $this->app->singleton(MotionAuditLoggerContract::class, AuditLoggerAdapter::class);

        // Phase 2 — provider-neutral read contracts (docs/motion-read-contracts/).
        $this->app->singleton(MotionAssetReadContract::class, DatabaseMotionAssetReader::class);
        $this->app->singleton(MotionVersionReadContract::class, DatabaseMotionVersionReader::class);
        $this->app->singleton(MotionRuntimeProjectionReadContract::class, PublishedRuntimeProjectionProvider::class);
        $this->app->singleton(TemplateAssetReadContract::class, TemplateAssetReader::class);
        $this->app->singleton(SiteAssetPackageReadContract::class, SitePackageReader::class);

        // New Phase 1 adapter interfaces (docs/motion-saas-integration/LARAVEL_ADAPTER_CONTRACT.md).
        $this->app->singleton(MotionAuthorizationInterface::class, MotionAuthorizationAdapter::class);
        $this->app->singleton(MotionAssetResolverInterface::class, MotionAssetResolverAdapter::class);
        $this->app->singleton(TemplateAssetResolverInterface::class, TemplateAssetResolverAdapter::class);
        $this->app->singleton(SiteAssetPackageResolverInterface::class, SiteAssetPackageResolverAdapter::class);
        $this->app->singleton(MotionRuntimeProjectionInterface::class, MotionRuntimeProjectionAdapter::class);
    }
}
