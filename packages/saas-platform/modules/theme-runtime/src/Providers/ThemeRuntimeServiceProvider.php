<?php

namespace SaaSPlatform\ThemeRuntime\Providers;

use Illuminate\Support\ServiceProvider;
use SaaSPlatform\ThemeRuntime\Browser\PlaywrightExtractionService;

/**
 * Phase 2h — ADR-029: this package is the one shared authority for canonical
 * Theme block rendering/registry/CSS/token semantics, consumed by both the
 * saas-platform Builder preview and customer-site's tenant runtime. It owns
 * NO host-specific concerns (no tenant DB access, no auth, no site context)
 * — see Contracts/ for the seams hosts implement to supply real data.
 */
class ThemeRuntimeServiceProvider extends ServiceProvider
{
    /**
     * __DIR__ inside a .blade.php file resolves to Laravel's compiled-view
     * cache directory (storage/framework/views/...), NOT the original
     * source location — Blade compiles every view (package ones included)
     * into one shared flat cache directory. A real PHP class file's own
     * __DIR__ has no such problem, so the CSS contract's Blade partial
     * calls this instead of resolving its own path.
     */
    public static function cssContractPath(): string
    {
        return __DIR__ . '/../../resources/css/theme-runtime-contract.css';
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/theme-runtime.php', 'theme-runtime');

        $this->app->singleton(\SaaSPlatform\ThemeRuntime\BlockRegistry::class);

        $this->app->singleton(PlaywrightExtractionService::class, fn ($app) => new PlaywrightExtractionService(
            (string) $app['config']->get('theme-runtime.script_root')
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'theme-runtime');

        $this->publishes([
            __DIR__ . '/../../config/theme-runtime.php' => config_path('theme-runtime.php'),
        ], 'theme-runtime-config');
    }
}
