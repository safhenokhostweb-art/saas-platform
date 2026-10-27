<?php

namespace Tests\Support\FoodMart;

use App\Services\ThemeBuilder\FoodMart\FoodMartBlockRenderer;
use App\Services\ThemeBuilder\FoodMart\FoodMartBlocks;
use SaaSPlatform\ThemeRuntime\ThemeTokenNormalizer;

/**
 * Phase 2B — assembles a static HTML page that reproduces the REAL
 * customer-site public render (customer-site/resources/views/layouts/
 * canvas-theme.blade.php), as faithfully as achievable without modifying
 * customer-site (which is read-only in this phase).
 *
 * customer-site's own files were only ever READ to build this class —
 * never modified, never executed. Every literal CSS/HTML fragment below
 * that originates from customer-site was captured once, by hand-tracing
 * the real Blade file (see the Phase 2B report for the exact source
 * lines), not by re-parsing/re-including it at runtime.
 *
 * Known, disclosed simplifications (see Phase 2B report for full reasoning):
 *  - partials/theme-engine-tokens.blade.php (TenantThemeResolver-driven
 *    "ui" and "ds" prefixed CSS custom properties) is omitted — it requires a live
 *    customer-site Site/Setting record this harness has no access to, and
 *    it only ever emits CSS custom properties FoodMart's own CSS never
 *    references, so its omission has zero expected effect on FoodMart's
 *    rendered output.
 *  - <x-seo-meta/> and the favicon <link> block are omitted — meta-only,
 *    zero visual effect.
 *  - The Builder Edit Bar (@auth tenant-admin only) is omitted — this
 *    harness never authenticates a user.
 */
final class FoodMartRealShellHarness
{
    public const PLACEMENT_WRAPPED = 'wrapped';
    public const PLACEMENT_CONTENT_ONLY = 'content-only';

    /**
     * @param string $placement self::PLACEMENT_WRAPPED (FoodMart header/footer
     *   go through header_blocks/footer_blocks, exactly like a real installed
     *   theme would today) or self::PLACEMENT_CONTENT_ONLY (FoodMart owns its
     *   whole page including header/footer, placed in $contentHtml, with
     *   header_blocks/footer_blocks left empty)
     * @param bool $isolateDirection wrap FoodMart's own content in a
     *   dir="ltr" root (a plain, valid HTML attribute on any element —
     *   requires no customer-site change) instead of inheriting the page's
     *   real dir="rtl"
     * @param bool $applyMinimalSelectOverride add ONE small, FoodMart-scoped
     *   CSS rule neutralizing the platform's global `select{...!important}`
     *   rule (module-components.css) — additive, opt-in, lives only in this
     *   harness's own generated CSS, never in any platform file
     */
    public static function build(
        string $placement,
        bool $isolateDirection,
        bool $applyMinimalSelectOverride,
        string $assetBaseUrl,
        string $documentDirection = 'rtl',
        bool $themeOwnsChrome = false,
        bool $loadPlatformPresentationCss = true,
        ?string $localizedThemeAssetBaseUrl = null,
    ): string {
        FoodMartBlocks::reset();
        $renderer = new FoodMartBlockRenderer();

        $order = array_keys(FoodMartBlocks::TYPES);
        try {
            FoodMartBlocks::register();
            $rendered = [];
            foreach ($order as $type) {
                $rendered[$type] = $renderer->renderBlock(['id' => $type, 'type' => $type, 'props' => []]);
            }
        } finally {
            FoodMartBlocks::reset();
        }

        $headerType = 'foodmart_header';
        $footerType = 'foodmart_footer';
        $bodyOrder = array_values(array_filter($order, fn ($t) => $t !== $headerType && $t !== $footerType));

        if ($placement === self::PLACEMENT_WRAPPED) {
            $headerHtml = $rendered[$headerType];
            $footerHtml = $rendered[$footerType];
            $contentHtml = implode("\n\n", array_map(fn ($t) => $rendered[$t], $bodyOrder));
        } else {
            $headerHtml = '';
            $footerHtml = '';
            $contentHtml = implode("\n\n", array_map(fn ($t) => $rendered[$t], $order));
        }

        if ($isolateDirection) {
            $contentHtml = '<div dir="ltr" class="foodmart-root" style="text-align:left;">' . "\n" . $contentHtml . "\n" . '</div>';
        }

        $gs = []; // default global_styles, same as a freshly created Theme
        $tokenCss = (new ThemeTokenNormalizer())->renderCssBlock($gs);
        $runtimeContractCss = file_get_contents(
            'C:/laragon/www/saas-platform/packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-contract.css'
        );

        // Phase 0.3 (ADR-063): the block rules the partial used to inline.
        $runtimeBlocksCss = file_get_contents(
            'C:/laragon/www/saas-platform/packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css'
        );

        $styleContract = str_replace(
            ['/*__THEME_TOKEN_CSS__*/', '/*__THEME_RUNTIME_CONTRACT_CSS__*/', '/*__THEME_RUNTIME_BLOCKS_CSS__*/'],
            [$tokenCss, $runtimeContractCss, $runtimeBlocksCss],
            FoodMartPhase2bStyleContract::CSS
        );

        // Two real, measured Tailwind Preflight collisions, each fixed by
        // exactly one small FoodMart-scoped counter-rule (see Phase 2B
        // report for the empirical root-cause trace of each):
        //  1. module-components.css's global `select{...!important}` rule
        //     (a real, pre-existing, unrelated platform fix for a
        //     cross-browser dark-mode <select> rendering bug) forcibly
        //     overrides every <select>'s colors; countered here with higher
        //     specificity, still !important, scoped to FoodMart only.
        //  2. Tailwind Preflight's `svg,video,canvas,... { display:block }`
        //     rule has NO competing declaration anywhere in FoodMart's own
        //     CSS or Bootstrap (both simply rely on the browser's own
        //     `inline` default for <svg>, never declaring it explicitly) —
        //     so cascade-layer precedence never even enters into it; a
        //     block-level icon breaks the inline "★ 4.5" rating text onto
        //     its own line, silently taller product cards. Restoring the
        //     literal browser default for FoodMart's own svg icons is the
        //     minimal fix — matches original behavior exactly, changes
        //     nothing else.
        $selectOverrideCss = $applyMinimalSelectOverride
            ? "\n.foodmart-root select, body select { color-scheme: initial !important; background-color: initial !important; color: initial !important; }\n"
                . ".foodmart-root svg, body svg { display: inline; }\n"
                . "header .cart > button { padding: 2px 6px; }\n"
            : '';

        // ── Real <head> asset order, matching the real layout's own order ──
        $head = '';
        $head .= "<meta charset=\"utf-8\">\n";
        $head .= self::darkModeIife();
        $head .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
        $head .= "<title>FoodMart Phase 2B Real-Shell Harness</title>\n";
        $head .= "<link rel=\"preload\" as=\"font\" href=\"{$assetBaseUrl}/fonts/vazirmatn/Vazirmatn-Regular.woff2\" type=\"font/woff2\" crossorigin>\n";
        $head .= "<link rel=\"preload\" as=\"font\" href=\"{$assetBaseUrl}/fonts/vazirmatn/Vazirmatn-Bold.woff2\" type=\"font/woff2\" crossorigin>\n";
        $head .= "<style>" . FoodMartPhase2bFontFaces::CSS . "</style>\n";
        if ($loadPlatformPresentationCss) {
            $head .= "<link rel=\"stylesheet\" href=\"{$assetBaseUrl}/app.css\">\n";
        }
        $head .= "<script type=\"module\" src=\"{$assetBaseUrl}/app.js\"></script>\n";
        // partials/theme-engine-tokens.blade.php deliberately omitted — see class docblock.
        $head .= "<style>{$styleContract}</style>\n";
        $head .= self::skillBarScript();
        // @stack('head') — nothing pushes into it today; this is where a
        // future minimal theme-specific-asset mechanism would inject
        // FoodMart's own <link> tags. Simulated here for faithful testing.
        $head .= "<!-- @stack('head') equivalent -->\n";
        // Bare, non-prefixed paths — deliberately, not a mistake: FoodMart's
        // own rendered block markup (baked into FoodMartBlockRenderer,
        // byte-exact from the Golden Reference, never rewritten) references
        // images/css via bare relative paths (e.g. <img src="images/logo.png">).
        // Those bytes cannot change without violating "preserve markup
        // exactly", so FoodMart's own css/js/images/style.css must be served
        // as direct siblings of index.html — never under a sub-path prefix.
        // Order matches the Golden Reference's own <head> exactly (Swiper
        // CSS, then Bootstrap CSS, THEN vendor.css/style.css) — not
        // alphabetical or "logical" convenience order. FoodMart's own
        // style.css intentionally overrides some Bootstrap defaults (e.g.
        // .container-fluid gutter width) via equal-specificity selectors;
        // getting this order backwards (as an earlier draft of this harness
        // did) silently lets Bootstrap's default win instead, which is a
        // real, measured bug this exact ordering fixes — see the Phase 2B
        // report's root-cause writeup.
        $swiperCss = $localizedThemeAssetBaseUrl !== null ? "{$localizedThemeAssetBaseUrl}/swiper-bundle.min.css" : 'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css';
        $bootstrapCss = $localizedThemeAssetBaseUrl !== null ? "{$localizedThemeAssetBaseUrl}/bootstrap-5.3.0-alpha3.min.css" : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css';
        $head .= "<link rel=\"stylesheet\" href=\"{$swiperCss}\">\n";
        $head .= "<link href=\"{$bootstrapCss}\" rel=\"stylesheet\">\n";
        $head .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"css/vendor.css\">\n";
        $head .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"style.css\">\n";
        if ($localizedThemeAssetBaseUrl !== null) {
            $head .= "<link href=\"{$localizedThemeAssetBaseUrl}/google-fonts-local.css\" rel=\"stylesheet\">\n";
        } else {
            $head .= "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
            $head .= "<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
            $head .= "<link href=\"https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Open+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap\" rel=\"stylesheet\">\n";
        }
        if ($selectOverrideCss !== '') {
            $head .= "<style>{$selectOverrideCss}</style>\n";
        }

        // ── Real <body> structure ──
        $body = '';
        $body .= "<a href=\"#canvas-main\" class=\"canvas-skip-link\">پرش به محتوا</a>\n";

        if ($themeOwnsChrome) {
            $body .= $headerHtml . "\n";
        } elseif (trim($headerHtml) !== '') {
            $body .= "<header class=\"canvas-site-header tb-site-header\" role=\"banner\" id=\"site-header\">\n";
            $body .= "<div class=\"tb-header-inner\">\n<div class=\"tb-header-brand\">FoodMart</div>\n";
            $body .= "<button type=\"button\" id=\"tb-mobile-menu-btn\" class=\"tb-menu-btn\" aria-label=\"منو\" aria-expanded=\"false\">☰</button>\n";
            $body .= "<div class=\"tb-header-nav-wrap\">\n{$headerHtml}\n</div>\n</div>\n";
            $body .= "<div id=\"tb-mobile-menu\" class=\"tb-mobile-menu\" aria-hidden=\"true\"><div class=\"tb-mobile-menu-inner\"></div></div>\n";
            $body .= "</header>\n";
        } else {
            $body .= "<header class=\"canvas-site-header\" style=\"background:var(--tb-primary,#4f46e5);color:#fff;padding:0 1rem;\" role=\"banner\">\n";
            $body .= "<div style=\"display:flex;align-items:center;justify-content:space-between;max-width:var(--tb-container,80rem);margin:0 auto;min-height:4rem;gap:1rem;flex-wrap:wrap;\">FoodMart</div>\n";
            $body .= "</header>\n";
        }

        $body .= "<main id=\"canvas-main\" class=\"canvas-main\" tabindex=\"-1\">\n{$contentHtml}\n</main>\n";

        if ($themeOwnsChrome) {
            $body .= $footerHtml . "\n";
        } elseif (trim($footerHtml) !== '') {
            $body .= "<footer class=\"canvas-site-footer tb-site-footer\" role=\"contentinfo\" id=\"site-footer\">\n{$footerHtml}\n</footer>\n";
        } else {
            $body .= "<footer class=\"canvas-site-footer\" style=\"background:#1f2937;color:#9ca3af;padding:1.5rem 1rem;text-align:center;\" role=\"contentinfo\">\n";
            $body .= "<div style=\"max-width:var(--tb-container,80rem);margin:0 auto;\"><p style=\"margin:0;font-size:0.9rem;\">© Phase 2B Harness</p></div>\n</footer>\n";
        }

        $body .= self::mobileMenuScript();
        $body .= "<!-- @stack('scripts') equivalent -->\n";
        $body .= "<script src=\"js/jquery-1.11.0.min.js\"></script>\n";
        $swiperJs = $localizedThemeAssetBaseUrl !== null ? "{$localizedThemeAssetBaseUrl}/swiper-bundle.min.js" : 'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js';
        $bootstrapJs = $localizedThemeAssetBaseUrl !== null ? "{$localizedThemeAssetBaseUrl}/bootstrap.bundle.min.js" : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js';
        $body .= "<script src=\"{$swiperJs}\"></script>\n";
        $body .= "<script src=\"{$bootstrapJs}\"></script>\n";
        $body .= "<script src=\"js/plugins.js\"></script>\n";
        $body .= "<script src=\"js/script.js\"></script>\n";

        $dirAttr = in_array($documentDirection, ['ltr', 'rtl'], true) ? $documentDirection : 'rtl';
        $langAttr = $dirAttr === 'ltr' ? 'en' : 'fa';

        return "<!DOCTYPE html>\n<html lang=\"{$langAttr}\" dir=\"{$dirAttr}\">\n<head>\n{$head}</head>\n<body>\n{$body}</body>\n</html>\n";
    }

    private static function darkModeIife(): string
    {
        // Byte-irrelevant to FoodMart (targets window.__tbTheme /
        // .tb-theme-toggle, neither of which FoodMart's markup has) but
        // included for faithful console/script-collision testing.
        return "<script>window.__tbTheme = { get: function(){return 'system';}, set: function(){}, cycle: function(){} };</script>\n";
    }

    private static function skillBarScript(): string
    {
        return <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    var fills = document.querySelectorAll('.tb-skill-bar-fill[data-width]');
    if (!fills.length) return;
});
</script>
HTML;
    }

    private static function mobileMenuScript(): string
    {
        return <<<'HTML'
<script>
(function () {
    var btn = document.getElementById('tb-mobile-menu-btn');
    var menu = document.getElementById('tb-mobile-menu');
    if (btn && menu) {
        btn.addEventListener('click', function () {
            var isOpen = menu.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            menu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        });
    }
})();
</script>
HTML;
    }
}
