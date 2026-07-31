<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Providers;

use Illuminate\Support\ServiceProvider;
use SaaSPlatform\CoreUtils\Contracts\Services\SeoOverrideProviderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Adapters\SeoOverrideProviderAdapter;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\MetaBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\RobotsBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SchemaBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SeoAuditorInterface;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SeoHeadRendererInterface;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SitemapBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Http\Middleware\SeoAdvancedRedirectMiddleware;
use SaaSPlatform\Modules\SeoAdvanced\Services\MetaBuilder;
use SaaSPlatform\Modules\SeoAdvanced\Services\RobotsBuilder;
use SaaSPlatform\Modules\SeoAdvanced\Services\SchemaBuilder;
use SaaSPlatform\Modules\SeoAdvanced\Services\SeoAuditor;
use SaaSPlatform\Modules\SeoAdvanced\Services\SeoHeadRenderer;
use SaaSPlatform\Modules\SeoAdvanced\Services\SitemapBuilder;

/**
 * Phase 18 — SEO Advanced is a fully self-contained module: every binding
 * below lives inside this provider, resolved only by this module's own
 * Contracts.
 *
 * Phase 18-B — Part 4: the one exception, by design, is
 * SeoOverrideProviderInterface — a GENERIC (not SEO-specific) extension
 * point the host runtime already defines and defaults to a Null Object
 * for (see customer-site's App\Services\Seo\NullSeoOverrideProvider).
 * Rebinding it here to this module's own adapter is what actually
 * connects SEO Advanced's settings to the live theme output — Core still
 * never references this module by name anywhere.
 */
class SeoAdvancedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/seo-advanced.php', 'seo-advanced');

        $this->app->bind(MetaBuilderInterface::class, MetaBuilder::class);
        $this->app->bind(RobotsBuilderInterface::class, RobotsBuilder::class);
        $this->app->bind(SchemaBuilderInterface::class, SchemaBuilder::class);
        $this->app->bind(SitemapBuilderInterface::class, SitemapBuilder::class);
        $this->app->bind(SeoAuditorInterface::class, SeoAuditor::class);
        $this->app->bind(SeoHeadRendererInterface::class, SeoHeadRenderer::class);
        $this->app->bind(SeoOverrideProviderInterface::class, SeoOverrideProviderAdapter::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/site.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'seo-advanced');

        $this->publishes([
            __DIR__ . '/../../config/seo-advanced.php' => config_path('seo-advanced.php'),
        ], 'seo-advanced-config');
        $this->publishes([
            __DIR__ . '/../../resources/views' => resource_path('views/vendor/seo-advanced'),
        ], 'seo-advanced-views');
        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'seo-advanced-migrations');

        $this->registerRedirectMiddleware();
    }

    /**
     * Phase 18-C — Part 2: adds SeoAdvancedRedirectMiddleware to the
     * global HTTP middleware stack via the standard Laravel package API
     * (Kernel::pushMiddleware()) — no edit to bootstrap/app.php or
     * app/Http/Kernel.php. Only ever runs when this module's own
     * ServiceProvider boots — i.e. only when the package is actually
     * installed (Part 6: Runtime Safety). pushMiddleware() already
     * no-ops if the middleware is already present on that specific
     * Kernel instance, so no extra de-duplication is needed here.
     */
    private function registerRedirectMiddleware(): void
    {
        if (!$this->app->bound(\Illuminate\Contracts\Http\Kernel::class)) {
            return;
        }

        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        if (!method_exists($kernel, 'pushMiddleware')) {
            return;
        }

        $kernel->pushMiddleware(SeoAdvancedRedirectMiddleware::class);
    }
}
