<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\BlockRenderer;
use Database\Seeders\ArianaMultiPlusThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArianaMultiPlusThemeSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seededTheme(): Theme
    {
        $this->seed(ArianaMultiPlusThemeSeeder::class);

        return Theme::query()->where('slug', ArianaMultiPlusThemeSeeder::SLUG)->sole();
    }

    /** @return list<array<string,mixed>> every block node, header/footer/pages, flattened */
    private function allBlockNodes(Theme $theme): array
    {
        $nodes = [];

        foreach ([$theme->header_blocks, $theme->footer_blocks] as $blocks) {
            foreach ($blocks as $block) {
                $nodes[] = $block;
            }
        }
        foreach ($theme->pages as $page) {
            foreach ($page['blocks'] ?? [] as $block) {
                $nodes[] = $block;
            }
        }

        return $nodes;
    }

    public function test_it_seeds_the_published_shared_multipurpose_template(): void
    {
        $theme = $this->seededTheme();

        self::assertSame('ariana-multiplus', $theme->slug);
        self::assertNull($theme->site_id, 'a shared platform template must not be site-scoped');
        self::assertSame(Theme::TYPE_FULL, $theme->type);
        self::assertSame(Theme::CATEGORY_BUSINESS, $theme->category);
        self::assertSame(Theme::SOURCE_SEEDER, $theme->source);
        self::assertSame(Theme::STATUS_PUBLISHED, $theme->status);
        self::assertTrue($theme->is_marketplace);
        self::assertSame(0, $theme->marketplace_price);
        self::assertIsArray($theme->pages);
        self::assertNotEmpty($theme->header_blocks);
        self::assertNotEmpty($theme->footer_blocks);
        self::assertNotEmpty($theme->global_styles);

        self::assertNotNull(
            ThemeMarketplace::query()->where('theme_id', $theme->id)->first(),
            'the theme must have an honest marketplace catalog row'
        );
    }

    /**
     * Every block type used anywhere in the document must be a
     * registered canonical type AND have a real renderer method —
     * mirroring RenderIntegrityCheck::isRenderable()'s exact
     * dispatch decision, not merely registry presence.
     */
    public function test_every_block_type_is_registered_with_a_real_renderer(): void
    {
        $theme = $this->seededTheme();
        $renderer = app(BlockRenderer::class);

        $types = [];
        $stack = $this->allBlockNodes($theme);
        while (! empty($stack)) {
            $node = array_pop($stack);
            $type = $node['type'];
            $types[$type] = true;

            self::assertNotNull(
                BlockRegistry::get($type),
                "block type \"{$type}\" must be registered in the canonical registry"
            );
            self::assertTrue(
                method_exists($renderer, 'render' . Str::studly(str_replace(':', '_', $type))),
                "block type \"{$type}\" must have a real renderer method"
            );

            foreach ($node['children'] ?? [] as $child) {
                $stack[] = $child;
            }
        }

        // The five professional slider families the theme promises.
        foreach (['slider', 'product_slider', 'category_slider', 'testimonials_slider', 'client_logos'] as $sliderType) {
            self::assertArrayHasKey($sliderType, $types, "the professional {$sliderType} must be used somewhere in the theme");
        }
    }

    /**
     * Render-integrity parity: every block (recursively) must
     * produce non-empty markup through the real shared renderer —
     * the same bar the Commercial Quality Gate's RenderIntegrityCheck
     * applies before a theme can reach commercial eligibility.
     */
    public function test_every_block_renders_non_empty_markup(): void
    {
        $theme = $this->seededTheme();
        $renderer = app(BlockRenderer::class);

        $render = function (array $block) use ($renderer, &$render): void {
            $html = $renderer->renderBlock($block);
            self::assertNotSame(
                '',
                trim($html),
                'block type "' . $block['type'] . '" must render non-empty markup'
            );
            foreach ($block['children'] ?? [] as $child) {
                $render($child);
            }
        };

        foreach ($this->allBlockNodes($theme) as $block) {
            $render($block);
        }
    }

    /**
     * Hard product requirement: this template's identity is
     * mobile-number-only — no email field, address, mention or
     * CTA anywhere in the persisted theme document.
     */
    public function test_no_email_appears_anywhere_in_the_theme_document(): void
    {
        $theme = $this->seededTheme();

        $document = json_encode([
            'pages' => $theme->pages,
            'header' => $theme->header_blocks,
            'footer' => $theme->footer_blocks,
            'global_styles' => $theme->global_styles,
            'settings' => $theme->settings,
        ], JSON_UNESCAPED_UNICODE);

        self::assertStringNotContainsString('email', $document);
        self::assertStringNotContainsString('ایمیل', $document);
    }

    public function test_it_is_persian_rtl_with_vazirmatn(): void
    {
        $theme = $this->seededTheme();

        self::assertSame(['fa-IR'], $theme->settings['supported_locales']);
        self::assertSame('fa-IR', $theme->settings['default_locale']);
        self::assertSame(['fa-IR' => 'rtl'], $theme->settings['locale_contract']['directions']);
        self::assertSame(['fa-IR' => 'Vazirmatn'], $theme->settings['locale_contract']['font_families']);
        self::assertStringContainsString('Vazirmatn', $theme->global_styles['typography']['font_heading']);
        self::assertStringContainsString('Vazirmatn', $theme->global_styles['typography']['font_body']);
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seed(ArianaMultiPlusThemeSeeder::class);
        $first = Theme::query()->where('slug', ArianaMultiPlusThemeSeeder::SLUG)->sole();

        $this->seed(ArianaMultiPlusThemeSeeder::class);
        $second = Theme::query()->where('slug', ArianaMultiPlusThemeSeeder::SLUG)->sole();

        self::assertSame($first->id, $second->id);
        self::assertSame(1, Theme::query()->where('slug', ArianaMultiPlusThemeSeeder::SLUG)->count());
    }
}
