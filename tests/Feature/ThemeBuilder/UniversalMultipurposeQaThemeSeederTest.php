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
        self::assertCount(11, $theme->pages);
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
        self::assertCount(96, $included);
        self::assertSame(['module:gallery_widget'], array_values(array_diff(array_keys(BlockRegistry::all()), array_keys($included))));

        $results = app(StructuralIntegrityCheck::class)->run($theme, '1.0.0', null);
        self::assertCount(1, $results);
        self::assertSame('passed', $results[0]->toArray()['status']);
    }
}
