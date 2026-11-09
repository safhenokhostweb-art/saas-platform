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

    /** Phase 0.3 (ADR-063) — the shared block stylesheet, same seam as cssContractPath(). */
    public static function cssBlocksPath(): string
    {
        return __DIR__ . '/../../resources/css/theme-runtime-blocks.css';
    }

    /** Phase 0.4 (ADR-063) — the local Vazirmatn @font-face rules, same seam. */
    public static function fontFacesPath(): string
    {
        return __DIR__ . '/../../resources/css/vazirmatn-font-faces.css';
    }

    /** Phase 0.5 (ADR-064) — the Canvas colour control's shared value model. */
    public static function canvasColorPath(): string
    {
        return __DIR__ . '/../../resources/js/canvas-color.js';
    }

    /** Phase 0.6 (ADR-065) — the Canvas icon picker's shared model. */
    public static function canvasIconsPath(): string
    {
        return __DIR__ . '/../../resources/js/canvas-icons.js';
    }

    /** ADR-076 — the Canvas page-tab strip (horizontal scroll + chevrons). */
    public static function canvasTabStripPath(): string
    {
        return __DIR__ . '/../../resources/js/canvas-tab-strip.js';
    }

    /** Inspect Mode (WU-1) — widget outlines, labels and the plugin action bar. */
    public static function canvasInspectPath(): string
    {
        return __DIR__ . '/../../resources/js/canvas-inspect.js';
    }

    /** Design HUD (WU-2) — live per-widget checks, Sentinel finding codes. */
    public static function canvasAuditPath(): string
    {
        return __DIR__ . '/../../resources/js/canvas-audit.js';
    }

    /** «سلامت ویجت» sidebar tab (WU-3) — the Design HUD as a per-widget list. */
    public static function canvasHealthPath(): string
    {
        return __DIR__ . '/../../resources/js/canvas-health.js';
    }

    /** Phase 0.7 (ADR-066) — live newsletter + search-dialog behaviour. */
    public static function siteFormsScriptPath(): string
    {
        return __DIR__ . '/../../resources/js/blocks/site-forms.js';
    }

    /** Phase 1 (ADR-067) — header scroll behaviour + delegated mega menu. */
    public static function siteHeaderScriptPath(): string
    {
        return __DIR__ . '/../../resources/js/blocks/site-header.js';
    }

    /** Phase 1/2 (ADR-067) — mini-cart drawer and instant add-to-cart. */
    public static function cartScriptPath(): string
    {
        return __DIR__ . '/../../resources/js/blocks/cart.js';
    }

    /** Phase 3b (ADR-069) — pricing billing toggle and feature tooltips. */
    public static function pricingScriptPath(): string
    {
        return __DIR__ . '/../../resources/js/blocks/pricing.js';
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

    public static function headerModernScriptPath(): string
    {
        return __DIR__ . '/../../resources/js/blocks/header-modern/header-modern.js';
    }

    public static function headerModernStylePath(): string
    {
        return __DIR__ . '/../../resources/js/blocks/header-modern/header-modern.css';
    }

    public static function headerModernDefinitionPath(): string
    {
        return __DIR__ . '/../../resources/js/blocks/header-modern/header-modern.json';
    }

    /** Footer widget (ADR-082 parity) — scoped CSS with --footer-* variables. */
    public static function navFooterStylePath(): string
    {
        return __DIR__ . '/../../resources/js/blocks/nav-footer/nav-footer.css';
    }

    /**
     * Phase 9 / Step 2 — the shared carousel controller, inlined once per
     * document by BlockRenderer::renderCarousel().
     *
     * Same seam as referenceBlockScriptPath() and for the same reason: the
     * file a developer reads and the bytes a rendered page ships must be the
     * same, or the two carriers silently diverge.
     */
    public static function carouselBlockScriptPath(): string
    {
        return __DIR__ . '/../../resources/js/blocks/carousel.js';
    }

    /**
     * Phase 9.2 — the legacy carousel adapter, emitted as part of the SAME
     * once-per-document carousel emission. It registers the four formerly
     * inline-carried types (slider, category_slider, testimonials_slider,
     * client_logos) against the SDK, giving them the destroy() the inline
     * controller never had; see resources/js/blocks/legacy_carousel.js.
     */
    public static function legacyCarouselScriptPath(): string
    {
        return __DIR__ . '/../../resources/js/blocks/legacy_carousel.js';
    }

    /**
     * Phase 9.3 — the countdown adapter, inlined once per document by
     * BlockRenderer::renderCountdown().
     *
     * Same provider-path seam as referenceBlockScriptPath() and
     * carouselBlockScriptPath(), so the file a developer reads and the bytes
     * a rendered page ships cannot drift.
     */
    public static function countdownAdapterScriptPath(): string
    {
        return __DIR__ . '/../../resources/js/blocks/countdown_adapter.js';
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

        // Canvas overhaul Unit 5 (ADR-075) — template library media, served
        // by the package on both hosts (see TemplateLibraryMediaController).
        $this->loadRoutesFrom(__DIR__ . '/../../routes/library.php');

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