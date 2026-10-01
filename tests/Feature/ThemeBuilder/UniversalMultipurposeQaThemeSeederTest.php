<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\QualityGate\Checks\StructuralIntegrityCheck;
use Database\Seeders\UniversalMultipurposeQaThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\ThemeRuntime\ThemeTokenNormalizer;
use Tests\TestCase;

class UniversalMultipurposeQaThemeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_complete_portable_structure_resolved_by_slug(): void
    {
        $this->seed(UniversalMultipurposeQaThemeSeeder::class);

        $theme = Theme::query()->where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();
        self::assertSame('قالب جامع چندمنظوره', $theme->name);
        // Builder integrity audit (2026-09-11): grew from 11 structural-only
        // pages to 16 real marketing/utility pages (dedicated pricing,
        // team, testimonials, faq, forms pages added — see §D).
        // TASK 3/3 Phase 2: +5 alternate homepage compositions (service,
        // ecommerce, personal, magazine, education — see §D-2) — 16 -> 21.
        // Commercial Productization phase: +2 real standalone pages ('saas'
        // platform intro, 'product_categories' Category Slider/Grid
        // showcase) — 21 -> 23.
        // Commercial Productization fase-4: +1 real 'service_detail' page
        // — the architecture decision this phase required (no real
        // Service model exists anywhere in customer-site, confirmed by
        // direct search) resolved to a real, standard, extensible
        // Theme-Canvas page using only canonical block types — 23 -> 24.
        self::assertCount(24, $theme->pages);
        self::assertNotEmpty($theme->header_blocks);
        self::assertNotEmpty($theme->footer_blocks);
        self::assertNotEmpty($theme->global_styles);

        $included = [];
        foreach ([$theme->header_blocks, $theme->footer_blocks] as $blocks) {
            foreach ($blocks as $block) $included[$block['type']] = true;
        }
        foreach ($theme->pages as $page) {
            foreach ($page['blocks'] ?? [] as $block) $included[$block['type']] = true;
        }

        // MOD-01 (Full Product Commercial Audit, TASK 2A): module:shop_
        // wishlist_icon registered (a real Builder integration for the
        // existing ShopWishlistService/session-compare backends) — 97 -> 98.
        // Auto-covered by the seeder's own builder-coverage page (below),
        // no seeder change needed.
        //
        // MOD-02/MOD-03 (TASK 2A-CONTINUATION): module:installment_badge
        // (real InstallmentService/InstallmentPlan integration) and
        // module:loyalty_points_badge (real MembershipPointsService
        // integration) registered — 98 -> 100. Auto-covered by the same
        // builder-coverage page, no seeder change needed.
        //
        // TASK 2A (tabs container authoring fix): tab_panel registered as
        // tabs's real child container type — 100 -> 101. tab_panel is only
        // ever meaningful as a tabs child, so (like every other type with
        // no natural business-page home) it is left uncovered by the
        // explicit pages and auto-covered by the seeder's own
        // builder-coverage page below, no seeder change needed.
        //
        // Universal Multipurpose Slider Overhaul: 'category_slider' is a
        // genuinely new canonical type (previously absent from both
        // registry sources) — 101 -> 102. Placed for real in the
        // e-commerce homepage alongside product_slider (see homeBlocks()),
        // not left to the builder-coverage fallback.
        //
        // Commercial Productization fase-2 (22-element audit): 'timeline'
        // was a confirmed real gap — completely absent from both registry
        // and renderer — 102 -> 103. Placed for real on the About page
        // (company-history timeline replacing the generic process_steps
        // that previously sat there), not left to the builder-coverage
        // fallback.
        //
        // Phase 3A -> 3D widget families: the canonical registry gained the
        // 3A basic primitives (spacer, rich_text, icon_box, image_box, badge,
        // list), the 3B hero family, the 3C commercial/dynamic/interaction
        // widgets and the section-library compositions built from them —
        // 103 -> 163 (including Phase 11's header_modern_v1). All of them are auto-covered by the seeder's own
        // builder-coverage page, so no seeder change is needed; the
        // coverage invariant asserted immediately below (every registered
        // type except the four documented exclusions is actually
        // represented) is what proves that, and it is the assertion to
        // trust when this tripwire number needs its next bump.
        self::assertCount(163, BlockRegistry::all());
        // Coverage invariant (§F): every registered type except the four
        // deliberately-excluded types customer-site's own registry unsets
        // (no backend content model, or a duplicate authority a real
        // renderer already covers — see the seeder's own docblock; TASK
        // 3/3 Phase 5 added 'products'/'module:portfolio_grid'/
        // 'module:services_section' to the pre-existing
        // 'module:gallery_widget' exclusion after real browser
        // certification through customer-site's actual renderer caught
        // 'products' rendering a visible "[invalid]" marker) is
        // represented somewhere, either meaningfully placed or on the
        // explicit 'builder-coverage' regression page.
        $excluded = array_values(array_diff(array_keys(BlockRegistry::all()), array_keys($included)));
        sort($excluded);
        self::assertSame(['module:gallery_widget', 'module:portfolio_grid', 'module:services_section', 'products'], $excluded);
        self::assertCount(count(BlockRegistry::all()) - 4, $included);

        // §M/N: 6 structurally distinct Header/Footer presets, each a real
        // different block composition (not a color reskin of one block).
        //
        // THEME-03 (Full Product Commercial Audit, TASK 2A): these now
        // live as the real, wired-up `header_variants`/`footer_variants`
        // (Theme::getHeaderVariants()/getFooterVariants()) instead of
        // inert `settings['header_presets']` metadata — same 6 presets,
        // now genuinely consumed by customer-site's Page-level variant
        // selection instead of sitting unread.
        $headerVariants = $theme->getHeaderVariants();
        $footerVariants = $theme->getFooterVariants();
        self::assertCount(6, $headerVariants);
        self::assertCount(6, $footerVariants);
        foreach ($headerVariants as $variant) {
            self::assertNotEmpty($variant['blocks']);
        }
        foreach ($footerVariants as $variant) {
            self::assertNotEmpty($variant['blocks']);
        }
        self::assertCount(1, array_filter($headerVariants, fn ($v) => $v['is_default']), 'exactly one header variant must be the default');
        self::assertCount(1, array_filter($footerVariants, fn ($v) => $v['is_default']), 'exactly one footer variant must be the default');
        // The default variant's blocks must be the same real data as the
        // flat header_blocks/footer_blocks every pre-existing consumer
        // still reads directly — no divergence between the two.
        $defaultHeader = array_values(array_filter($headerVariants, fn ($v) => $v['is_default']))[0];
        self::assertSame($theme->header_blocks, $defaultHeader['blocks']);
        // Builder integrity final gate (2026-09-12): all 6 presets on each
        // side must have a genuinely distinct BLOCK-TYPE signature — the
        // exact ordered list of block types, not merely a different block
        // COUNT and not merely different props on the same single
        // nav_header/nav_footer. A signature collision here means two
        // presets are cosmetic reskins of the same structure, which does
        // not satisfy "6 structurally distinct presets".
        $headerSignatures = array_map(
            fn ($p) => implode(',', array_map(fn ($b) => $b['type'], $p['blocks'])),
            $headerVariants
        );
        $footerSignatures = array_map(
            fn ($p) => implode(',', array_map(fn ($b) => $b['type'], $p['blocks'])),
            $footerVariants
        );
        self::assertCount(6, array_unique($headerSignatures), 'Header presets are not all structurally distinct by block-type signature: ' . implode(' | ', $headerSignatures));
        self::assertCount(6, array_unique($footerSignatures), 'Footer presets are not all structurally distinct by block-type signature: ' . implode(' | ', $footerSignatures));

        $results = app(StructuralIntegrityCheck::class)->run($theme, '1.0.0', null);
        self::assertCount(1, $results);
        self::assertSame('passed', $results[0]->toArray()['status']);

        // §D-2 (TASK 3/3 Phase 2): the 5 alternate homepage compositions
        // exist, are all NOT the site's active home, and are structurally
        // distinct from each other and from the real 'home' page by
        // block-type signature — proving genuine per-category composition,
        // not six copies of one homepage with swapped text.
        $homeSlugs = ['home_service', 'home_ecommerce', 'home_personal', 'home_magazine', 'home_education'];
        $signatures = [];
        foreach ($homeSlugs as $key) {
            self::assertArrayHasKey($key, $theme->pages, "alternate homepage '{$key}' must exist");
            $page = $theme->pages[$key];
            self::assertFalse($page['is_home'], "'{$key}' must not be marked as the active homepage");
            self::assertNotEmpty($page['blocks'], "'{$key}' must have real authored content");
            $signatures[$key] = implode(',', array_map(fn ($b) => $b['type'], $page['blocks']));
        }
        $signatures['home'] = implode(',', array_map(fn ($b) => $b['type'], $theme->pages['home']['blocks']));
        self::assertCount(6, array_unique($signatures), 'Homepage compositions are not all structurally distinct by block-type signature: ' . implode(' | ', $signatures));

        // Commercial Productization fase-2: 'timeline' must be real,
        // Persian, Builder-editable demo content on the About page — not
        // just present in the registry.
        $aboutBlocks = $theme->pages['about']['blocks'];
        $timelineBlock = collect($aboutBlocks)->firstWhere('type', 'timeline');
        self::assertNotNull($timelineBlock, "'about' page must contain a real 'timeline' block");
        self::assertGreaterThanOrEqual(4, count($timelineBlock['props']['items']), 'the demo timeline must have real, multiple milestones, not a token single item');
        foreach ($timelineBlock['props']['items'] as $item) {
            self::assertNotEmpty($item['date']);
            self::assertNotEmpty($item['title']);
            self::assertNotEmpty($item['text']);
        }

        // Commercial Productization fase-2: the header's 'خدمات'/'فروشگاه'
        // mega menu is authored with the real `mega_columns` shape (not the
        // legacy flat mega_items grouped-by-title hack) and actually
        // renders a genuine multi-column dropdown, never a raw JSON dump.
        $servicesLink = collect($theme->header_blocks[0]['props']['nav_links'] ?? [])->firstWhere('label', 'خدمات');
        self::assertNotNull($servicesLink, "header must have a 'خدمات' nav link");
        self::assertArrayHasKey('mega_columns', $servicesLink);
        self::assertGreaterThanOrEqual(2, count($servicesLink['mega_columns']));
        $renderer = new \App\Services\ThemeBuilder\BlockRenderer();
        $headerHtml = $renderer->renderBlock($theme->header_blocks[0]);
        self::assertStringContainsString('tb-mega-menu-panel', $headerHtml);
        self::assertStringNotContainsString('mega_columns&quot;', $headerHtml, 'mega menu data must never leak as raw JSON text');
    }

    // Part 4/7 (7-part local audit task, 2026-09-19): re-running the seeder
    // must not create a duplicate Theme row — the seeder's own
    // Theme::firstOrNew(['slug' => ..., 'site_id' => null]) is the
    // idempotency guarantee; this proves it end to end via the real
    // artisan-seed entry point, twice in a row.
    public function test_reseeding_does_not_create_a_duplicate_theme_row(): void
    {
        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $this->seed(UniversalMultipurposeQaThemeSeeder::class);

        self::assertSame(
            1,
            Theme::query()->where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->whereNull('site_id')->count(),
            'reseeding must update the existing Theme row, never insert a second one'
        );
    }

    // Part 4 (7-part local audit task, 2026-09-19): a plain
    // Theme::updateOrCreate(..., ['status' => STATUS_DRAFT, ...]) would
    // silently reset an already-published theme back to draft on every
    // reseed. The seeder now only sets 'status' on first creation
    // (Theme::firstOrNew() + a $wasNew guard) — this proves a published
    // theme survives a reseed with its status intact.
    public function test_reseeding_does_not_reset_an_already_published_theme_back_to_draft(): void
    {
        $this->seed(UniversalMultipurposeQaThemeSeeder::class);

        $theme = Theme::query()->where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->whereNull('site_id')->sole();
        $theme->forceFill(['status' => Theme::STATUS_PUBLISHED])->save();

        $this->seed(UniversalMultipurposeQaThemeSeeder::class);

        $theme->refresh();
        self::assertSame(Theme::STATUS_PUBLISHED, $theme->status, 'reseeding must not un-publish an already-published theme');
    }

    // Part 3 (7-part local audit task, 2026-09-19): root-caused fix for
    // black/empty image blocks — UploadedFile::fake()->image() produced
    // structurally-valid-but-visually-blank JPEGs. seedImages() now
    // generates a real GD gradient+shapes image per asset key. This proves
    // every seeded image is a valid, correctly decodable, non-trivially-
    // sized JPEG — not the old blank fixture output.
    public function test_seeded_images_are_real_non_blank_jpegs(): void
    {
        $this->seed(UniversalMultipurposeQaThemeSeeder::class);

        $theme = Theme::query()->where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->whereNull('site_id')->sole();

        $expectedKeys = ['hero', 'about', 'gallery_1', 'gallery_2', 'gallery_3', 'gallery_4', 'portfolio_1', 'portfolio_2', 'team_1', 'team_2', 'team_3', 'team_4'];
        $paths = [];

        $disk = app(\App\Services\Infrastructure\MediaStorage::class)->diskNameFor('public');
        foreach ($expectedKeys as $key) {
            $relative = "theme-media/{$theme->id}/{$key}.jpg";
            self::assertTrue(\Illuminate\Support\Facades\Storage::disk($disk)->exists($relative), "missing generated image file for '{$key}'");

            $fullPath = \Illuminate\Support\Facades\Storage::disk($disk)->path($relative);
            $info = @getimagesize($fullPath);
            self::assertNotFalse($info, "'{$key}'.jpg must be a decodable image");
            self::assertSame('image/jpeg', $info['mime']);
            // A flat/blank single-color JPEG of these dimensions compresses
            // to a few hundred bytes; the real branded gradient+shapes
            // generator produces several KB minimum — a cheap, reliable
            // proxy for "not blank" without decoding pixels.
            self::assertGreaterThan(2000, filesize($fullPath), "'{$key}'.jpg is suspiciously small — looks blank, not a real generated image");
            $paths[$key] = $fullPath;
        }

        // Distinct content per key, not the same image copy-pasted under
        // different names.
        self::assertGreaterThan(1, count(array_unique(array_map('filesize', $paths))), 'seeded images must not all be byte-identical');
    }

    /**
     * Commercial Productization phase — real local demo video (Hero/SaaS
     * intro). Copied idempotently from a pre-built source asset (see
     * UniversalMultipurposeQaThemeSeeder::seedVideo()'s own doc comment
     * for why this is a copy, not a fresh Playwright recording, on every
     * seed run) — never an external/unstable URL, never a black box.
     */
    public function test_seeded_demo_video_and_poster_are_real_local_files(): void
    {
        $this->seed(UniversalMultipurposeQaThemeSeeder::class);

        $theme = Theme::query()->where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->whereNull('site_id')->sole();
        $disk = app(\App\Services\Infrastructure\MediaStorage::class)->diskNameFor('public');

        $videoPath = "theme-media/{$theme->id}/hero-intro.webm";
        self::assertTrue(\Illuminate\Support\Facades\Storage::disk($disk)->exists($videoPath), 'the demo video file must exist locally');
        self::assertGreaterThan(10000, \Illuminate\Support\Facades\Storage::disk($disk)->size($videoPath), 'the demo video must be a real, non-trivial file, not an empty/stub file');

        $posterPath = "theme-media/{$theme->id}/hero-intro-poster.jpg";
        self::assertTrue(\Illuminate\Support\Facades\Storage::disk($disk)->exists($posterPath), 'the poster image must exist locally');
        $posterFull = \Illuminate\Support\Facades\Storage::disk($disk)->path($posterPath);
        $info = @getimagesize($posterFull);
        self::assertNotFalse($info, 'the poster must be a real, decodable image');
        self::assertSame('image/jpeg', $info['mime']);

        // The 'saas' page must actually reference these real local files,
        // never a bare/absent video block.
        $saasPage = $theme->pages['saas'] ?? null;
        self::assertNotNull($saasPage, "the 'saas' page must exist");
        $videoBlocks = array_values(array_filter($saasPage['blocks'], fn ($b) => $b['type'] === 'video'));
        self::assertCount(1, $videoBlocks, "the 'saas' page must contain exactly one real video block");
        self::assertStringContainsString('hero-intro.webm', $videoBlocks[0]['props']['url']);
        self::assertStringContainsString('hero-intro-poster.jpg', $videoBlocks[0]['props']['poster']);
    }

    /**
     * Commercial Productization fase-3 (visual identity): the theme must
     * have its own distinct palette (not just the platform's generic
     * indigo default every new Theme starts from) — primary, secondary,
     * accent, and all three semantic status colors — plus a real, distinct
     * dark palette, and every one of these must survive
     * ThemeTokenNormalizer::normalize() into real, WCAG-AA-safe derived
     * tokens (not just raw hex values sitting unused in global_styles).
     */
    public function test_theme_has_a_distinct_complete_color_identity_with_light_and_dark_palettes(): void
    {
        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::query()->where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->whereNull('site_id')->sole();

        $styles = $theme->global_styles;
        self::assertNotSame('#4f46e5', $styles['primary_color'], 'the theme must not ship with the platform\'s generic default primary color');
        self::assertSame('#0f766e', $styles['primary_color']);
        self::assertSame('#0e7490', $styles['secondary_color']);
        self::assertSame('#f59e0b', $styles['accent_color']);
        self::assertSame('#16a34a', $styles['success_color']);
        self::assertSame('#d97706', $styles['warning_color']);
        self::assertSame('#dc2626', $styles['error_color']);

        self::assertTrue($styles['dark_mode']['enabled'] ?? false);
        self::assertNotSame($styles['primary_color'], $styles['dark_mode']['primary'], 'dark mode must have its own tuned primary, not just reuse the light one');
        self::assertSame('#0f172a', $styles['dark_mode']['background']);

        $normalizer = new ThemeTokenNormalizer();
        $vars = $normalizer->normalize($styles);
        foreach (['--tb-primary', '--tb-secondary', '--tb-accent', '--tb-success', '--tb-warning', '--tb-error'] as $key) {
            self::assertArrayHasKey($key, $vars);
            self::assertArrayHasKey(str_replace('--tb-', '--tb-on-', $key), $vars);
        }

        $css = $normalizer->renderCssBlock($styles);
        self::assertStringContainsString(':root {', $css);
        self::assertStringContainsString('[data-theme="dark"] {', $css);
        self::assertStringContainsString('--tb-primary: #0f766e;', $css);
        self::assertStringContainsString('--tb-primary: #2dd4bf;', $css);
    }
}
