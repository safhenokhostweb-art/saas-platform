<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\QualityGate\Checks\StructuralIntegrityCheck;
use Database\Seeders\UniversalMultipurposeQaThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        self::assertCount(21, $theme->pages);
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
        self::assertCount(101, BlockRegistry::all());
        // Coverage invariant (§F): every registered type except the one
        // deliberately-excluded module:gallery_widget (no backend content
        // model — see the seeder's own docblock) is represented somewhere,
        // either meaningfully placed or on the explicit 'builder-coverage'
        // regression page.
        self::assertSame(['module:gallery_widget'], array_values(array_diff(array_keys(BlockRegistry::all()), array_keys($included))));
        self::assertCount(count(BlockRegistry::all()) - 1, $included);

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
    }
}
