<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

/**
 * FoodMart — Phase 3 authoring seeder.
 *
 * Phase 1 established this seeder as an identity-only skeleton. Phase 3
 * (user-approved LOCAL lifecycle gate) completes the authoring data the real
 * pipeline needs, from the ONE canonical visual representation already proven
 * in Phase 2A/2D:
 *
 *   - blocks: the canonical foodmart_* types rendered by
 *     SaaSPlatform\ThemeRuntime\FoodMartBlockRendererTrait (the shared
 *     implementation used by BOTH the saas Builder preview and the real
 *     customer-site tenant runtime). No second DOM representation exists.
 *   - global_styles: the generic Phase 2C/2D runtime capabilities
 *     (direction=ltr, chrome_owner=theme, presentation_css_owner=theme).
 *   - settings.theme_assets_manifest: the ordered, structured, SHA-256-pinned
 *     asset manifest (Phase 2D contract verbatim — kind/source/path/sha256).
 *     Source URLs point at THIS platform's own configured origin and the
 *     packaged byte-copies under public/theme-assets/foodmart/1.0.0/ — never
 *     at the Golden Reference directory, QA baselines, or an external CDN.
 *     Hashes are computed from the packaged bytes at seed time; a missing or
 *     unreadable packaged file fails the seed loudly (fail-closed) instead of
 *     seeding a theme that cannot localize.
 *
 * This seeder still does NOT publish, assign, install, or create marketplace
 * records. Those remain the job of the EXISTING pipeline (ThemeBuilderService
 * -> ThemeCommercialQualityGate -> ThemeReleaseService ->
 * PublishedThemeAssignmentService -> ThemeInstallGateway) invoked separately
 * by the approved Phase 3 lifecycle, exactly as the Phase 1 test guards
 * require.
 */
class FoodMartThemeSeeder extends Seeder
{
    public const SLUG = 'foodmart';
    public const NAME = 'FoodMart';
    public const CATEGORY = Theme::CATEGORY_STORE;

    /**
     * Pure identity metadata — no database access, no side effects.
     *
     * @return array{name:string,slug:string,category:string,site_id:null,type:string,source:string}
     */
    public static function identity(): array
    {
        return [
            'name' => self::NAME,
            'slug' => self::SLUG,
            'category' => self::CATEGORY,
            // Shared platform template, never a site-scoped clone (see
            // Theme::scopeTemplates() / the Theme model's own site_id
            // docblock: "null = platform template; set = tenant override").
            'site_id' => null,
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
        ];
    }

    public function run(): void
    {
        $identity = self::identity();

        $theme = Theme::firstOrNew(['slug' => self::SLUG, 'site_id' => null]);
        $wasNew = !$theme->exists;

        $theme->fill([
            'name' => $identity['name'],
            'description' => 'FoodMart grocery/e-commerce theme (Phase 3 authoring: canonical Phase 2A/2D blocks, theme-owned chrome/CSS, pinned assets).',
            'type' => $identity['type'],
            'category' => $identity['category'],
            'source' => $identity['source'],
            // ONE canonical block representation (shared trait above). The
            // header/footer blocks are also stored flat because every existing
            // consumer (ThemeInstallGateway payload, canvas chrome rendering)
            // reads header_blocks/footer_blocks directly.
            'pages' => self::pages(),
            'header_blocks' => [['id' => 'foodmart_header', 'type' => 'foodmart_header', 'props' => []]],
            'footer_blocks' => [['id' => 'foodmart_footer', 'type' => 'foodmart_footer', 'props' => []]],
            // Generic runtime capabilities (Phase 2C/2D contract, unchanged):
            // LTR document, theme owns its chrome, theme owns presentation
            // CSS (platform app.css omitted; platform app.js still loaded).
            'global_styles' => [
                'direction' => 'ltr',
                'chrome_owner' => 'theme',
                'presentation_css_owner' => 'theme',
            ],
            'settings' => array_merge(is_array($theme->settings ?? null) ? $theme->settings : [], [
                'theme_assets_manifest' => self::themeAssetsManifest(),
            ]),
        ]);

        // Same idempotency guard used by UniversalMultipurposeQaThemeSeeder:
        // only set status on first creation, so a reseed never silently
        // un-publishes an already-published theme.
        if ($wasNew) {
            $theme->status = Theme::STATUS_DRAFT;
        }

        $theme->save();
    }

    /**
     * Golden Reference DOM order (proven in Phase 2A, re-verified through
     * Phase 2D). The header/footer blocks live in their own chrome slots; the
     * single "home" page body carries every other block in the exact accepted
     * order.
     *
     * @return array<string,array{title:string,slug:string,blocks:list<array{id:string,type:string,props:array<string,mixed>}>}>
     */
    public static function pages(): array
    {
        $types = [
            'foodmart_svg_sprite', 'foodmart_preloader', 'foodmart_offcanvas_cart', 'foodmart_offcanvas_search',
            'foodmart_header', 'foodmart_hero', 'foodmart_categories', 'foodmart_brands',
            'foodmart_trending_products', 'foodmart_promo_banners', 'foodmart_product_carousel_best_selling',
            'foodmart_newsletter', 'foodmart_product_carousel_most_popular', 'foodmart_product_carousel_just_arrived',
            'foodmart_blog', 'foodmart_app_promo', 'foodmart_keywords', 'foodmart_trust_badges', 'foodmart_footer',
        ];
        $body = [];
        foreach ($types as $type) {
            if (in_array($type, ['foodmart_header', 'foodmart_footer'], true)) {
                continue;
            }
            $body[] = ['id' => $type, 'type' => $type, 'props' => []];
        }

        return ['home' => ['title' => 'FoodMart Home', 'slug' => '', 'blocks' => $body]];
    }

    /**
     * The ordered theme_assets manifest (version 1) — the exact asset set and
     * load order accepted at Phase 2D, plus every file asset the canonical
     * markup/CSS references (HTML src/inline-url images, CSS url() plugin
     * imagery, localized Google fonts). Each entry is pinned to the sha256 of
     * the packaged byte-copy; the packaged files are the single authority.
     *
     * @return array{version:int,assets:list<array{kind:string,source:string,path:string,sha256:string}>}
     */
    private static function themeAssetsManifest(): array
    {
        $base = rtrim((string) config('app.url'), '/').'/theme-assets/foodmart/1.0.0/';
        $ordered = [
            ['css', 'theme-assets/swiper-bundle.min.css'],
            ['css', 'theme-assets/bootstrap-5.3.0-alpha3.min.css'],
            ['css', 'css/vendor.css'],
            ['css', 'style.css'],
            ['css', 'theme-assets/google-fonts-local.css'],
            ['js', 'js/jquery-1.11.0.min.js'],
            ['js', 'theme-assets/swiper-bundle.min.js'],
            ['js', 'theme-assets/bootstrap.bundle.min.js'],
            ['js', 'js/plugins.js'],
            ['js', 'js/script.js'],
        ];
        $files = array_merge(
            self::referencedImagePaths(),
            [
                'images/chocolat/close.png',
                'images/chocolat/fullscreen.png',
                'images/chocolat/left.png',
                'images/chocolat/loader.gif',
                'images/chocolat/right.png',
            ],
            array_map(fn ($f) => 'theme-assets/fonts/'.$f, self::packagedFontNames()),
        );
        $files = array_values(array_unique($files));
        sort($files);

        $assets = [];
        foreach ($ordered as [$kind, $path]) {
            $assets[] = ['kind' => $kind, 'source' => $base.$path, 'path' => $path, 'sha256' => self::packagedSha256($path)];
        }
        foreach ($files as $path) {
            $assets[] = ['kind' => 'file', 'source' => $base.$path, 'path' => $path, 'sha256' => self::packagedSha256($path)];
        }

        return ['version' => 1, 'assets' => $assets];
    }

    /**
     * Every images/... path the canonical trait references via src= or inline
     * url('...'). Reading them from the canonical implementation (instead of
     * maintaining a parallel list) keeps markup and manifest from drifting.
     *
     * @return list<string>
     */
    private static function referencedImagePaths(): array
    {
        $trait = file_get_contents(self::canonicalTraitPath());
        if ($trait === false) {
            throw new \RuntimeException('FoodMart canonical renderer trait not readable while seeding.');
        }
        preg_match_all('/src="(images\/[^"]+)"/', $trait, $src);
        preg_match_all("/url\('(images\/[^']+)'\)/", $trait, $url);

        return array_merge($src[1], $url[1]);
    }

    private static function canonicalTraitPath(): string
    {
        $candidates = [
            base_path('packages/saas-platform/modules/theme-runtime/src/FoodMartBlockRendererTrait.php'),
            base_path('vendor/saas-platform/modules-theme-runtime/src/FoodMartBlockRendererTrait.php'),
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('FoodMart canonical renderer trait not found while seeding.');
    }

    /** @return list<string> font filenames packaged alongside the theme assets. */
    private static function packagedFontNames(): array
    {
        $dir = public_path('theme-assets/foodmart/1.0.0/theme-assets/fonts');
        if (! is_dir($dir)) {
            throw new \RuntimeException('Packaged FoodMart fonts directory missing: '.$dir);
        }
        $fonts = array_values(array_filter(scandir($dir) ?: [], fn ($f) => str_ends_with($f, '.ttf')));
        if ($fonts === []) {
            throw new \RuntimeException('Packaged FoodMart fonts directory is empty.');
        }

        return $fonts;
    }

    /** Fail-closed: a packaged asset that is missing cannot be declared. */
    private static function packagedSha256(string $path): string
    {
        $file = public_path('theme-assets/foodmart/1.0.0/'.$path);
        if (! is_file($file)) {
            throw new \RuntimeException('Packaged FoodMart asset missing: '.$path);
        }
        $hash = hash_file('sha256', $file);
        if ($hash === false) {
            throw new \RuntimeException('Unable to hash packaged FoodMart asset: '.$path);
        }

        return $hash;
    }
}
