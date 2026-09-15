<?php

namespace SaaSPlatform\Modules\MotionGraphics\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SaaSPlatform\Modules\MotionGraphics\Contracts\ClockContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAiProviderContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAssetPipelineContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAssetStorageContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAuditLoggerContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionCompilerContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionDeploymentGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionOptimizerContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPermissionGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPreviewGeneratorContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionVersionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\SiteAssetPackageReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\TemplateAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\SiteResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Http\View\Components\MotionGraphicsComponent;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Policies\MotionGraphicPolicy;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionAiEditingService;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionAssetPipeline;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionDeploymentService;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionLibraryService;
use SaaSPlatform\Modules\MotionGraphics\Support\DenyAllMotionPermissionGateway;
use SaaSPlatform\Modules\MotionGraphics\Support\DenyAllPlatformAdminResolver;
use SaaSPlatform\Modules\MotionGraphics\Support\InMemoryMotionAssetStorage;
use SaaSPlatform\Modules\MotionGraphics\Support\NullMotionAiProvider;
use SaaSPlatform\Modules\MotionGraphics\Support\NullMotionAuditLogger;
use SaaSPlatform\Modules\MotionGraphics\Support\NullMotionDeploymentGateway;
use SaaSPlatform\Modules\MotionGraphics\Support\NullMotionPreviewGenerator;
use SaaSPlatform\Modules\MotionGraphics\Support\NullSiteResolver;
use SaaSPlatform\Modules\MotionGraphics\Support\PassThroughMotionCompiler;
use SaaSPlatform\Modules\MotionGraphics\Support\PassThroughMotionOptimizer;
use SaaSPlatform\Modules\MotionGraphics\Support\Read\DatabaseMotionAssetReader;
use SaaSPlatform\Modules\MotionGraphics\Support\Read\DatabaseMotionVersionReader;
use SaaSPlatform\Modules\MotionGraphics\Support\Read\PublishedRuntimeProjectionProvider;
use SaaSPlatform\Modules\MotionGraphics\Support\Read\SitePackageReader;
use SaaSPlatform\Modules\MotionGraphics\Support\Read\TemplateAssetReader;
use SaaSPlatform\Modules\MotionGraphics\Support\SystemClock;

final class MotionGraphicsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/motion-graphics.php', 'motion-graphics');

        $this->bindDefault(PlatformAdminResolverContract::class, DenyAllPlatformAdminResolver::class);
        $this->bindDefault(SiteResolverContract::class, NullSiteResolver::class);
        $this->bindDefault(MotionAiProviderContract::class, NullMotionAiProvider::class);
        $this->bindDefault(MotionAssetStorageContract::class, InMemoryMotionAssetStorage::class);
        $this->bindDefault(MotionCompilerContract::class, PassThroughMotionCompiler::class);
        $this->bindDefault(MotionOptimizerContract::class, PassThroughMotionOptimizer::class);
        $this->bindDefault(MotionPreviewGeneratorContract::class, NullMotionPreviewGenerator::class);
        $this->bindDefault(MotionDeploymentGatewayContract::class, NullMotionDeploymentGateway::class);
        $this->bindDefault(MotionAuditLoggerContract::class, NullMotionAuditLogger::class);
        $this->bindDefault(MotionPermissionGatewayContract::class, DenyAllMotionPermissionGateway::class);
        $this->bindDefault(ClockContract::class, SystemClock::class);

        // Phase 2 — provider-neutral read contracts (docs/motion-read-contracts/).
        $this->bindDefault(MotionAssetReadContract::class, DatabaseMotionAssetReader::class);
        $this->bindDefault(MotionVersionReadContract::class, DatabaseMotionVersionReader::class);
        $this->bindDefault(MotionRuntimeProjectionReadContract::class, PublishedRuntimeProjectionProvider::class);
        $this->bindDefault(TemplateAssetReadContract::class, TemplateAssetReader::class);
        $this->bindDefault(SiteAssetPackageReadContract::class, SitePackageReader::class);

        $this->app->singleton(MotionAssetPipelineContract::class, MotionAssetPipeline::class);
        $this->app->singleton(MotionLibraryService::class);
        $this->app->singleton(MotionDeploymentService::class);
        $this->app->singleton(MotionAiEditingService::class);
    }

    public function boot(): void
    {
        // Migrations register unconditionally, matching every sibling
        // module (TicketsServiceProvider, FormsServiceProvider — neither
        // gates loadMigrationsFrom behind its own feature flag). Schema
        // presence is harmless; only routes/views/policy/publishing below
        // stay behind motion-graphics.enabled, which the host app
        // deliberately keeps false in this phase (see
        // MotionGraphicsAdapterServiceProvider's docblock). Without this,
        // the module's own tables (motion_graphics, motion_groups, ...)
        // never exist anywhere the host app runs, even though
        // MotionLibraryService — reachable via the real adapter bindings —
        // depends on them.
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        if (! config('motion-graphics.enabled', false)) {
            return;
        }

        Gate::policy(MotionGraphic::class, MotionGraphicPolicy::class);

        if (config('motion-graphics.routes_enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        }

        if (config('motion-graphics.admin_ui_enabled', false)
            || config('motion-graphics.customer_runtime_enabled', false)) {
            $this->loadViewsFrom(__DIR__.'/../../resources/views', 'motion-graphics');
        }

        if (config('motion-graphics.customer_runtime_enabled', false)) {
            Blade::component('motion.graphics', MotionGraphicsComponent::class);
        }
    }

    private function bindDefault(string $contract, string $implementation): void
    {
        if (! $this->app->bound($contract)) {
            $this->app->singleton($contract, $implementation);
        }
    }
}
