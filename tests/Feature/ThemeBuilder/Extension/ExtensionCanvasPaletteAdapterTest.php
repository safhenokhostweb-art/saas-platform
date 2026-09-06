<?php

namespace Tests\Feature\ThemeBuilder\Extension;

use App\Models\ExtensionRelease;
use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\Extension\ExtensionCanvasPaletteAdapter;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 3G-B-6 — proves ExtensionCanvasPaletteAdapter is the ONE truthful
 * bridge between a Theme's recorded `settings.extension_selections` and the
 * SaaS Theme Canvas palette/save-boundary, without ever falling back to
 * Extension.current_release_id, executing provider code, or inventing a
 * second registry.
 */
class ExtensionCanvasPaletteAdapterTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ExtensionReleaseService::class);
    }

    private function manifest(array $overrides = []): array
    {
        return array_merge([
            'elements' => [
                [
                    'type' => 'ext:acme:gallery',
                    'label' => 'گالری اکمی',
                    'category' => 'content',
                    'icon' => 'gallery',
                    'is_container' => false,
                    'controls' => [
                        'title' => ['control_type' => 'text', 'label' => 'عنوان', 'default' => ''],
                        'limit' => ['control_type' => 'number', 'default' => 6],
                        'featured' => ['control_type' => 'toggle', 'default' => false],
                        'layout' => ['control_type' => 'select', 'default' => 'grid', 'options' => ['grid' => 'گرید', 'list' => 'لیست']],
                        'cover' => ['control_type' => 'media', 'default' => ''],
                    ],
                ],
            ],
        ], $overrides);
    }

    private function theme(array $settings = []): Theme
    {
        return Theme::create([
            'name' => 'قالب تست افزونه',
            'slug' => 'ext-canvas-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
            'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => []]],
            'global_styles' => [],
            'settings' => $settings,
        ]);
    }

    // ── manifest → palette projection ───────────────────────────────────────

    public function test_manifest_projects_into_block_registry_shape(): void
    {
        $ext = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, $this->manifest());

        $theme = $this->theme(['extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']]]);

        $palette = ExtensionCanvasPaletteAdapter::paletteFor($theme);

        $this->assertArrayHasKey('ext:acme:gallery', $palette);
        $def = $palette['ext:acme:gallery'];
        $this->assertSame('گالری اکمی', $def['name']);
        $this->assertSame('content', $def['category']); // recognized canonical category passes through
        $this->assertFalse($def['is_container']);
        // assertEquals, not assertSame: manifest control key order is not
        // guaranteed to survive the DB's JSON round-trip (same convention as
        // ExtensionReleaseServiceTest's own persisted-manifest assertions).
        $this->assertEquals(['title' => '', 'limit' => 6, 'featured' => false, 'layout' => 'grid', 'cover' => ''], $def['defaultProps']);

        $this->assertSame('string', $def['schema']['title']['type']);
        $this->assertSame('number', $def['schema']['limit']['type']);
        $this->assertSame('boolean', $def['schema']['featured']['type']);
        $this->assertSame('select', $def['schema']['layout']['type']);
        $this->assertSame(['grid' => 'گرید', 'list' => 'لیست'], $def['schema']['layout']['options']);
        $this->assertSame('image', $def['schema']['cover']['type']); // media -> image widget
    }

    public function test_unrecognized_category_falls_back_to_extension_bucket(): void
    {
        $ext = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, $this->manifest([
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'Gallery', 'category' => 'media']],
        ]));

        $theme = $this->theme(['extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']]]);
        $palette = ExtensionCanvasPaletteAdapter::paletteFor($theme);

        $this->assertSame(ExtensionCanvasPaletteAdapter::FALLBACK_CATEGORY, $palette['ext:acme:gallery']['category']);
        $this->assertNotSame(BlockRegistry::CATEGORY_MODULE, $palette['ext:acme:gallery']['category']);
    }

    public function test_container_flag_is_carried_through(): void
    {
        $ext = $this->service->createExtension('acme', 'layout-kit', 'Layout Kit');
        $this->service->release($ext, '1.0.0', 1, $this->manifest([
            'elements' => [['type' => 'ext:acme:section', 'label' => 'Section', 'category' => 'layout', 'is_container' => true]],
        ]));

        $theme = $this->theme(['extension_selections' => ['acme/layout-kit' => ['version' => '1.0.0']]]);
        $palette = ExtensionCanvasPaletteAdapter::paletteFor($theme);

        $this->assertTrue($palette['ext:acme:section']['is_container']);
    }

    // ── fail-closed resolution ───────────────────────────────────────────────

    public function test_selection_for_nonexistent_product_fails_closed(): void
    {
        $theme = $this->theme(['extension_selections' => ['nobody/nothing' => ['version' => '1.0.0']]]);

        $palette = ExtensionCanvasPaletteAdapter::paletteFor($theme);
        $errors = ExtensionCanvasPaletteAdapter::resolveSelections($theme->settings['extension_selections'])['errors'];

        $this->assertSame([], $palette);
        $this->assertArrayHasKey('nobody/nothing', $errors);
    }

    public function test_selection_for_nonexistent_version_fails_closed(): void
    {
        $ext = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, $this->manifest());

        $theme = $this->theme(['extension_selections' => ['acme/gallery-pro' => ['version' => '9.9.9']]]);
        $errors = ExtensionCanvasPaletteAdapter::resolveSelections($theme->settings['extension_selections'])['errors'];

        $this->assertArrayHasKey('acme/gallery-pro', $errors);
        $this->assertStringContainsString('9.9.9', $errors['acme/gallery-pro']);
    }

    public function test_corrupted_manifest_row_fails_closed_not_throws(): void
    {
        $ext = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $release = $this->service->release($ext, '1.0.0', 1, $this->manifest());

        // Simulate a hypothetically corrupted historical row — bypass the
        // model's immutability guard entirely (raw query builder), exactly
        // like ExtensionElementOwnershipResolverTest's own defensive
        // corrupted-row scenarios do for the sibling B-5 resolvers.
        DB::table('extension_releases')->where('id', $release->id)->update(['manifest' => json_encode(['not' => 'a manifest'])]);

        $theme = $this->theme(['extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']]]);

        $palette = ExtensionCanvasPaletteAdapter::paletteFor($theme);
        $this->assertSame([], $palette);
    }

    // ── historical resolvability vs. NEW-selection policy (§6) ──────────────

    public function test_historical_superseded_selection_still_resolves_for_palette(): void
    {
        $ext = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, $this->manifest());
        $this->service->release($ext->fresh(), '1.1.0', 1, $this->manifest([
            'elements' => [['type' => 'ext:acme:gallery_v2', 'label' => 'Gallery v2', 'category' => 'content']],
        ]));

        // Theme deliberately still selected the now-superseded 1.0.0.
        $theme = $this->theme(['extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']]]);

        $palette = ExtensionCanvasPaletteAdapter::paletteFor($theme);

        $this->assertArrayHasKey('ext:acme:gallery', $palette);
        $this->assertArrayNotHasKey('ext:acme:gallery_v2', $palette);
    }

    public function test_current_release_id_is_never_consulted(): void
    {
        $ext = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, $this->manifest());
        $this->service->release($ext->fresh(), '1.1.0', 1, $this->manifest([
            'elements' => [['type' => 'ext:acme:gallery_v2', 'label' => 'Gallery v2', 'category' => 'content']],
        ]));

        $this->assertSame('1.1.0', $ext->fresh()->currentRelease->version);

        $theme = $this->theme(['extension_selections' => ['acme/gallery-pro' => ['version' => '1.0.0']]]);
        $palette = ExtensionCanvasPaletteAdapter::paletteFor($theme);

        // Palette reflects the EXPLICIT 1.0.0 selection, not the product's
        // current 1.1.0 release.
        $this->assertArrayHasKey('ext:acme:gallery', $palette);
        $this->assertArrayNotHasKey('ext:acme:gallery_v2', $palette);
    }

    public function test_selectable_products_offers_published_releases_only(): void
    {
        $ext = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $first = $this->service->release($ext, '1.0.0', 1, $this->manifest());
        $this->service->release($ext->fresh(), '1.1.0', 1, $this->manifest([
            'elements' => [['type' => 'ext:acme:gallery_v2', 'label' => 'Gallery v2', 'category' => 'content']],
        ]));

        $this->assertSame(ExtensionRelease::STATUS_SUPERSEDED, $first->fresh()->status);

        $products = ExtensionCanvasPaletteAdapter::selectableProducts();
        $entry = collect($products)->firstWhere('identifier', 'acme/gallery-pro');

        $versions = collect($entry['releases'])->pluck('version')->all();
        $this->assertSame(['1.1.0'], $versions); // 1.0.0 (superseded) is not offered for a NEW selection
    }

    // ── save-boundary validation (§12, §14, §22, §23) ───────────────────────

    public function test_block_using_type_from_selected_release_is_accepted(): void
    {
        $ext = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, $this->manifest());

        $selections = ['acme/gallery-pro' => ['version' => '1.0.0']];
        $blocks = [['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => ['title' => 'x']]];

        $result = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, $selections);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
    }

    public function test_block_using_type_not_in_any_selection_is_rejected(): void
    {
        $blocks = [['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []]];

        $result = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, []);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('ext:acme:gallery', $result['error']);
    }

    public function test_builtin_blocks_are_unaffected_by_extension_validation(): void
    {
        $blocks = [['id' => 'b1', 'type' => 'hero', 'props' => ['title' => 'x']]];

        $result = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, []);

        $this->assertTrue($result['ok']);
    }

    public function test_deliberate_version_change_rejected_when_new_release_drops_a_used_type(): void
    {
        $ext = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($ext, '1.0.0', 1, $this->manifest([
            'elements' => [
                ['type' => 'ext:acme:gallery', 'label' => 'Gallery', 'category' => 'content'],
                ['type' => 'ext:acme:carousel', 'label' => 'Carousel', 'category' => 'content'],
            ],
        ]));
        // 1.1.0 drops the "carousel" element.
        $this->service->release($ext->fresh(), '1.1.0', 1, $this->manifest([
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'Gallery', 'category' => 'content']],
        ]));

        $blocks = [
            ['id' => 'b1', 'type' => 'ext:acme:gallery', 'props' => []],
            ['id' => 'b2', 'type' => 'ext:acme:carousel', 'props' => []],
        ];

        // Theme still uses both types but the author is attempting to move
        // the selection forward to 1.1.0, which no longer provides carousel.
        $result = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, ['acme/gallery-pro' => ['version' => '1.1.0']]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('ext:acme:carousel', $result['error']);

        // Staying on 1.0.0 (which provides both) remains accepted.
        $stillOk = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, ['acme/gallery-pro' => ['version' => '1.0.0']]);
        $this->assertTrue($stillOk['ok']);
    }

    public function test_same_product_multiple_elements_both_must_be_in_selected_release(): void
    {
        $ext = $this->service->createExtension('acme', 'suite', 'Suite');
        $this->service->release($ext, '1.0.0', 1, $this->manifest([
            'elements' => [['type' => 'ext:acme:widget_a', 'label' => 'A', 'category' => 'content']],
        ]));

        $blocks = [
            ['id' => 'b1', 'type' => 'ext:acme:widget_a', 'props' => []],
            ['id' => 'b2', 'type' => 'ext:acme:widget_b', 'props' => []], // never published
        ];

        $result = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($blocks, ['acme/suite' => ['version' => '1.0.0']]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('ext:acme:widget_b', $result['error']);
    }

    public function test_multi_product_selections_are_independent(): void
    {
        $gallery = $this->service->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->service->release($gallery, '1.0.0', 1, $this->manifest());

        $forms = $this->service->createExtension('acme', 'forms-pro', 'Forms Pro');
        $this->service->release($forms, '2.0.0', 1, $this->manifest([
            'elements' => [['type' => 'ext:acme:contact_form', 'label' => 'Contact Form', 'category' => 'content']],
        ]));

        $theme = $this->theme(['extension_selections' => [
            'acme/gallery-pro' => ['version' => '1.0.0'],
            'acme/forms-pro' => ['version' => '2.0.0'],
        ]]);

        $palette = ExtensionCanvasPaletteAdapter::paletteFor($theme);

        $this->assertArrayHasKey('ext:acme:gallery', $palette);
        $this->assertArrayHasKey('ext:acme:contact_form', $palette);

        // Changing one product's selection never touches the other's resolution.
        $resolved = ExtensionCanvasPaletteAdapter::resolveSelections([
            'acme/gallery-pro' => ['version' => '9.9.9'], // now invalid
            'acme/forms-pro' => ['version' => '2.0.0'],
        ]);

        $this->assertArrayHasKey('acme/gallery-pro', $resolved['errors']);
        $this->assertArrayHasKey('acme/forms-pro', $resolved['manifests']);
    }
}
