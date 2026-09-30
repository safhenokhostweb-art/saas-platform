<?php

namespace Tests\Feature\ThemeBuilder;

use App\Services\ThemeBuilder\FoodMart\FoodMartBlockRenderer;
use App\Services\ThemeBuilder\FoodMart\FoodMartBlocks;
use Tests\Support\FoodMart\FoodMartPageShell;
use Tests\TestCase;

/**
 * FoodMart Phase 2A — static visual runtime prototype.
 *
 * Renders the Golden Reference's real DOM sections through the EXISTING,
 * unmodified shared BlockRenderer dispatch mechanism (FoodMartBlockRenderer
 * only ADDS render*() methods via normal class inheritance — see that
 * class's own docblock), assembles them into a full static HTML page, and
 * copies byte-identical asset copies into an isolated QA-only prototype
 * directory so the page can be served and visually compared against the
 * Phase 0 baseline screenshots.
 *
 * Deliberately makes ZERO database writes: no RefreshDatabase, no Theme row,
 * no seeding. Pure PHP rendering + filesystem copy of static assets.
 */
class FoodMartPrototypeRenderTest extends TestCase
{
    private const GOLDEN_REFERENCE = 'C:/laragon/www/saas-platform/_reference-themes/FoodMart-main/FoodMart-main';
    private const MANIFEST_TSV = 'C:/laragon/www/saas-platform/storage/app/theme-baselines/foodmart/integrity-manifest.tsv';
    private const PROTOTYPE_DIR = 'C:/laragon/www/saas-platform/storage/app/theme-baselines/foodmart/prototype';

    /** @return array<string,array{sha256:string,size:int}> keyed by relative path */
    private function loadManifest(): array
    {
        $lines = file(self::MANIFEST_TSV, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $out = [];
        foreach ($lines as $line) {
            [$path, $sha, $size] = explode("\t", $line);
            $out[$path] = ['sha256' => $sha, 'size' => (int) $size];
        }
        return $out;
    }

    public function test_golden_reference_integrity_is_intact_before_prototype_render(): void
    {
        $manifest = $this->loadManifest();
        self::assertCount(70, $manifest, 'Phase 0 manifest must describe exactly 70 files');

        $totalBytes = 0;
        foreach ($manifest as $relativePath => $expected) {
            $fullPath = self::GOLDEN_REFERENCE . '/' . $relativePath;
            self::assertFileExists($fullPath, "golden reference file missing: {$relativePath}");
            $actualHash = hash_file('sha256', $fullPath);
            self::assertSame($expected['sha256'], $actualHash, "checksum drift detected in golden reference file: {$relativePath}");
            $totalBytes += filesize($fullPath);
        }
        self::assertSame(5246762, $totalBytes, 'golden reference total byte count must match the Phase 0 baseline');
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    /** Byte-identical copy, verified by checksum immediately after copying — never a re-export/optimize. */
    private function copyVerified(string $relativePath, array $manifest): void
    {
        $src = self::GOLDEN_REFERENCE . '/' . $relativePath;
        $dest = self::PROTOTYPE_DIR . '/' . $relativePath;
        @mkdir(dirname($dest), 0777, true);

        copy($src, $dest);

        $expected = $manifest[$relativePath]['sha256'];
        $actual = hash_file('sha256', $dest);
        self::assertSame($expected, $actual, "QA prototype asset copy is not byte-identical to Golden Reference: {$relativePath}");
    }

    public function test_prototype_renders_via_the_shared_block_renderer_and_matches_golden_assets(): void
    {
        $manifest = $this->loadManifest();

        // Only css/, js/, images/, style.css are actually referenced by the
        // page (see Phase 0's asset-dependency map) — readme.md is not an
        // asset the rendered page needs, so it is not copied.
        $assetPaths = array_values(array_filter(
            array_keys($manifest),
            fn (string $p) => $p !== 'readme.md' && $p !== 'index.html'
        ));
        self::assertCount(68, $assetPaths, 'expected exactly style.css + css/* + js/* + images/** (70 total files minus readme.md minus index.html)');

        $this->rmrf(self::PROTOTYPE_DIR);
        foreach ($assetPaths as $relativePath) {
            $this->copyVerified($relativePath, $manifest);
        }

        FoodMartBlocks::reset();
        try {
            FoodMartBlocks::register();

            self::assertSame(
                array_keys(FoodMartBlocks::TYPES),
                array_values(array_filter(array_keys(FoodMartBlocks::TYPES), fn ($t) => \App\Services\ThemeBuilder\BlockRegistry::isHostRegisteredType($t))),
                'every FoodMart type must be registered as a host type, never a canonical one'
            );
            foreach (array_keys(FoodMartBlocks::TYPES) as $type) {
                self::assertFalse(\App\Services\ThemeBuilder\BlockRegistry::isCanonicalType($type), "{$type} must not be a canonical type");
            }

            $renderer = new FoodMartBlockRenderer();

            // Real DOM order, matching the Golden Reference exactly (proven
            // exhaustive/non-overlapping in Phase 2A authoring — see report).
            $order = [
                'foodmart_svg_sprite', 'foodmart_preloader', 'foodmart_offcanvas_cart', 'foodmart_offcanvas_search',
                'foodmart_header', 'foodmart_hero', 'foodmart_categories', 'foodmart_brands',
                'foodmart_trending_products', 'foodmart_promo_banners', 'foodmart_product_carousel_best_selling',
                'foodmart_newsletter', 'foodmart_product_carousel_most_popular', 'foodmart_product_carousel_just_arrived',
                'foodmart_blog', 'foodmart_app_promo', 'foodmart_keywords', 'foodmart_trust_badges', 'foodmart_footer',
            ];
            self::assertSame($order, array_keys(FoodMartBlocks::TYPES), 'block render order must match FoodMartBlocks::TYPES declaration order');

            $bodyHtml = '';
            foreach ($order as $type) {
                $html = $renderer->renderBlock(['id' => $type, 'type' => $type, 'props' => []]);
                self::assertNotSame('', trim($html), "{$type} rendered empty output");
                $bodyHtml .= $html . "\n\n";
            }

            $page = FoodMartPageShell::DOCTYPE_OPEN
                . FoodMartPageShell::HEAD . "\n"
                . FoodMartPageShell::BODY_OPEN
                . $bodyHtml
                . FoodMartPageShell::SCRIPTS
                . FoodMartPageShell::BODY_CLOSE;

            file_put_contents(self::PROTOTYPE_DIR . '/index.html', $page);
        } finally {
            FoodMartBlocks::reset();
        }

        self::assertFileExists(self::PROTOTYPE_DIR . '/index.html');
        $written = file_get_contents(self::PROTOTYPE_DIR . '/index.html');

        // Structural fidelity spot-checks — real Golden Reference markers
        // that must survive untouched (exact classes/ids/attrs, not a
        // paraphrase).
        foreach ([
            '<header>',
            'class="banner-blocks"',
            'class="category-carousel swiper"',
            'id="offcanvasCart"',
            'id="offcanvasNavbar"',
            'data-bs-toggle="offcanvas"',
            'class="swiper main-swiper"',
            'id="latest-blog"',
            'id="footer-bottom"',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css',
            'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css',
            'js/jquery-1.11.0.min.js',
            'css/vendor.css',
            'href="style.css"',
        ] as $marker) {
            self::assertStringContainsString($marker, $written, "prototype output is missing expected Golden Reference marker: {$marker}");
        }

        // No redesign markers: the prototype must never introduce Tailwind,
        // RTL, or Builder wrapper chrome into the FoodMart markup itself.
        foreach (['dir="rtl"', 'tailwind', 'tb-canvas-block', '@import \'tailwindcss\''] as $forbidden) {
            self::assertStringNotContainsStringIgnoringCase($forbidden, $written, "prototype output must never contain: {$forbidden}");
        }
    }

    public function test_no_foodmart_block_type_is_left_registered_after_the_suite_runs(): void
    {
        // Phase 2A guarded that this suite's register/reset cycle leaked NO
        // foodmart host type into the shared process. Since Phase 3 (user
        // approved), the canonical foodmart_* definitions are ALSO registered
        // boot-time by the shared package's own service provider — so after
        // this suite's reset(), Laravel's next test rebuild legitimately
        // re-registers them (self-healing by design, see
        // ThemeRuntimeServiceProvider::boot()). The invariant this guard must
        // now protect is unchanged in spirit: no state leaks BEYOND the
        // canonical set. Whatever is registered must be exactly the canonical
        // shared-package set, each host-registered (never canonical) and
        // matching the package definition verbatim.
        foreach (array_keys(FoodMartBlocks::TYPES) as $type) {
            self::assertSame(
                \SaaSPlatform\ThemeRuntime\FoodMartBlockTypes::TYPES[$type],
                \App\Services\ThemeBuilder\BlockRegistry::all()[$type] ?? null,
                "{$type} must be registered exactly as the canonical shared-package definition (no drifted/polluted registration)"
            );
            self::assertTrue(\App\Services\ThemeBuilder\BlockRegistry::isHostRegisteredType($type), "{$type} must remain a host type, never canonical");
        }
    }
}
