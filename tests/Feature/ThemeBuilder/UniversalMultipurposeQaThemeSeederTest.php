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
        self::assertCount(16, $theme->pages);
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

        self::assertCount(97, BlockRegistry::all());
        // Coverage invariant (§F): every registered type except the one
        // deliberately-excluded module:gallery_widget (no backend content
        // model — see the seeder's own docblock) is represented somewhere,
        // either meaningfully placed or on the explicit 'builder-coverage'
        // regression page.
        self::assertSame(['module:gallery_widget'], array_values(array_diff(array_keys(BlockRegistry::all()), array_keys($included))));
        self::assertCount(count(BlockRegistry::all()) - 1, $included);

        // §M/N: 6 structurally distinct Header/Footer presets, each a real
        // different block composition (not a color reskin of one block).
        self::assertCount(6, $theme->settings['header_presets']);
        self::assertCount(6, $theme->settings['footer_presets']);
        foreach ($theme->settings['header_presets'] as $preset) {
            self::assertNotEmpty($preset['blocks']);
        }
        foreach ($theme->settings['footer_presets'] as $preset) {
            self::assertNotEmpty($preset['blocks']);
        }
        // Builder integrity final gate (2026-09-12): all 6 presets on each
        // side must have a genuinely distinct BLOCK-TYPE signature — the
        // exact ordered list of block types, not merely a different block
        // COUNT and not merely different props on the same single
        // nav_header/nav_footer. A signature collision here means two
        // presets are cosmetic reskins of the same structure, which does
        // not satisfy "6 structurally distinct presets".
        $headerSignatures = array_map(
            fn ($p) => implode(',', array_map(fn ($b) => $b['type'], $p['blocks'])),
            $theme->settings['header_presets']
        );
        $footerSignatures = array_map(
            fn ($p) => implode(',', array_map(fn ($b) => $b['type'], $p['blocks'])),
            $theme->settings['footer_presets']
        );
        self::assertCount(6, array_unique($headerSignatures), 'Header presets are not all structurally distinct by block-type signature: ' . implode(' | ', $headerSignatures));
        self::assertCount(6, array_unique($footerSignatures), 'Footer presets are not all structurally distinct by block-type signature: ' . implode(' | ', $footerSignatures));

        $results = app(StructuralIntegrityCheck::class)->run($theme, '1.0.0', null);
        self::assertCount(1, $results);
        self::assertSame('passed', $results[0]->toArray()['status']);
    }
}
