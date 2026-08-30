<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3D-B — proves the stable cross-release block/page identity
 * assumption the future BASE/LOCAL/REMOTE upgrade reconciler depends on:
 * ThemeReleaseService::release() must copy a Theme's block/page structure
 * into the immutable ThemeRelease.snapshot completely verbatim — no id
 * regeneration, no reordering, no reshaping — for any release, not just
 * the first. This is the master-side half of the identity contract; the
 * companion customer-side half (new/duplicated blocks get a distinct
 * 'cust_' id, ordinary edits never mutate an existing id) is covered by
 * customer-site's CustomerBlockIdNamespaceTest.
 */
class ThemeReleaseSnapshotIdentityTest extends TestCase
{
    use RefreshDatabase;

    private ThemeReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ThemeReleaseService::class);
    }

    private function makeThemeWithIdentifiedBlocks(): Theme
    {
        return Theme::create([
            'name' => 'قالب شناسه‌دار تست',
            'slug' => 'identity-test-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'header_blocks' => [
                ['id' => 'blk_header_001', 'type' => 'nav_header', 'props' => ['logo' => 'x']],
            ],
            'footer_blocks' => [
                ['id' => 'blk_footer_001', 'type' => 'footer_basic', 'props' => []],
            ],
            'pages' => [
                'home' => ['title' => 'صفحه اصلی', 'slug' => 'home', 'blocks' => [
                    ['id' => 'blk_hero_001', 'type' => 'hero', 'props' => ['title' => 'خوش آمدید'], 'children' => [
                        ['id' => 'blk_hero_cta_001', 'type' => 'button', 'props' => ['text' => 'شروع']],
                    ]],
                    ['id' => 'blk_text_001', 'type' => 'text', 'props' => ['content' => 'متن']],
                ]],
            ],
            'global_styles' => ['primary_color' => '#0ea5e9'],
            'settings' => [],
        ]);
    }

    public function test_release_creation_preserves_every_block_id_exactly(): void
    {
        $theme = $this->makeThemeWithIdentifiedBlocks();

        $release = $this->service->release($theme, '1.0.0', 'Initial release.');

        $this->assertSame('blk_header_001', $release->snapshot['header_blocks'][0]['id']);
        $this->assertSame('blk_footer_001', $release->snapshot['footer_blocks'][0]['id']);
        $this->assertSame('blk_hero_001', $release->snapshot['pages']['home']['blocks'][0]['id']);
        $this->assertSame('blk_hero_cta_001', $release->snapshot['pages']['home']['blocks'][0]['children'][0]['id']);
        $this->assertSame('blk_text_001', $release->snapshot['pages']['home']['blocks'][1]['id']);
    }

    public function test_release_creation_preserves_block_and_page_order_exactly(): void
    {
        $theme = $this->makeThemeWithIdentifiedBlocks();

        $release = $this->service->release($theme, '1.0.0', 'Initial release.');

        $this->assertSame(
            ['blk_hero_001', 'blk_text_001'],
            array_column($release->snapshot['pages']['home']['blocks'], 'id')
        );
    }

    public function test_a_second_release_after_further_authoring_still_preserves_untouched_block_ids(): void
    {
        $theme = $this->makeThemeWithIdentifiedBlocks();
        $this->service->release($theme, '1.0.0', 'Initial release.');

        // Master author edits an existing block's content in place (never
        // touching its id) and adds one brand-new block with a fresh id —
        // exactly the pattern the canonical Theme Builder JS follows
        // (Phase 3D-A: makeBlock()/_regenIds() in canvas.blade.php).
        $pages = $theme->pages;
        $pages['home']['blocks'][1]['props']['content'] = 'متن ویرایش‌شده';
        $pages['home']['blocks'][] = ['id' => 'blk_new_002', 'type' => 'text', 'props' => ['content' => 'جدید']];
        $theme->update(['pages' => $pages]);

        $release2 = $this->service->release($theme, '2.0.0', 'Second release.');

        $ids = array_column($release2->snapshot['pages']['home']['blocks'], 'id');
        $this->assertSame(['blk_hero_001', 'blk_text_001', 'blk_new_002'], $ids, 'the edited block kept its original id, and the new block is additive — nothing was regenerated');
        $this->assertSame('متن ویرایش‌شده', $release2->snapshot['pages']['home']['blocks'][1]['props']['content']);

        // The FIRST release's own immutable snapshot is completely
        // untouched by the second release's authoring.
        $release1 = $theme->releases()->where('version', '1.0.0')->sole();
        $this->assertSame('متن', $release1->snapshot['pages']['home']['blocks'][1]['props']['content']);
    }

    public function test_release_never_introduces_a_block_id_that_was_not_already_on_the_theme(): void
    {
        $theme = $this->makeThemeWithIdentifiedBlocks();

        $release = $this->service->release($theme, '1.0.0', 'Initial release.');

        $themeIds = array_column($theme->pages['home']['blocks'], 'id');
        $snapshotIds = array_column($release->snapshot['pages']['home']['blocks'], 'id');
        $this->assertSame($themeIds, $snapshotIds);
    }
}
