<?php

namespace SaaSPlatform\ThemeRuntime\Providers;

use Illuminate\Support\ServiceProvider;
use SaaSPlatform\ThemeRuntime\Browser\PlaywrightExtractionService;
use SaaSPlatform\ThemeRuntime\DynamicBlockTypes;
use SaaSPlatform\ThemeRuntime\FoodMartBlockTypes;
use SaaSPlatform\ThemeRuntime\InteractionBlockTypes;
use SaaSPlatform\ThemeRuntime\SleepShimanBlockTypes;

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

    public static function canvasIdentityContractPath(): string
    {
        return __DIR__ . '/../../resources/js/canvas-identity.js';
    }

    /**
     * Phase 9 / Step 1 — the standardized Block SDK, same distributable-JS
     * seam as the identity contract above, so the Node tests and the Blade
     * include consume one source and can never drift apart.
     */
    public static function blockSdkContractPath(): string
    {
        return __DIR__ . '/../../resources/js/block-sdk.js';
    }

    /**
     * Phase 9 / Step 1 — the SDK's worked example block, inlined by
     * BlockRenderer::renderReferenceCounter() so the file a developer copies
     * and the file a rendered page ships are literally the same bytes.
     */
    public static function referenceBlockScriptPath(): string
    {
        return __DIR__ . '/../../resources/js/blocks/reference-counter.js';
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

        // Phase 3 — register the canonical foodmart_* host block types once,
        // here in the shared package, so BOTH hosts (saas-platform Builder
        // preview and customer-site tenant runtime) can dispatch/render them
        // through the same generic registerHostType() seam. The block type
        // itself is the dispatch identity: no host's generic code needs (or
        // has) any FoodMart-specific detection. Registration is idempotent;
        // canonical definitions are never overridden.
        FoodMartBlockTypes::register();
        SleepShimanBlockTypes::register();
        DynamicBlockTypes::register();

        // Phase 3C / Milestone 3 — the interactive_form/action_button widgets
        // are shipped in this same shared package and follow the identical
        // "register once, in boot(), through registerHostType()" seam. Without
        // this call they exist only behind the explicit register() that the
        // contract test performs, i.e. dead code in every real runtime.
        InteractionBlockTypes::register();
    }
}
