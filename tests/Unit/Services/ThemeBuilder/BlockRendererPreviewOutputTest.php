<?php

namespace Tests\Unit\Services\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Theme Builder commercial polish — regression coverage for the admin
 * preview/screenshot renderer gap found in the Preview Renderer + Screenshot
 * Pipeline Hardening audit: gallery, promo_banner and team used to fall
 * through to renderFallback() and dump raw JSON instead of visual HTML.
 */
class BlockRendererPreviewOutputTest extends TestCase
{
    private function renderer(): BlockRenderer
    {
        return new BlockRenderer();
    }

    public function test_gallery_renders_html_not_raw_json(): void
    {
        $html = $this->renderer()->renderBlock([
            'id' => 'blk_1',
            'type' => 'gallery',
            'props' => ['title' => 'گالری تصاویر', 'items' => [['url' => 'https://example.com/a.jpg', 'alt' => 'a']]],
        ]);

        $this->assertStringNotContainsString('{"title"', $html);
        $this->assertStringContainsString('tb-gallery', $html);
        $this->assertStringContainsString('https://example.com/a.jpg', $html);
    }

    public function test_promo_banner_renders_html_not_raw_json(): void
    {
        $html = $this->renderer()->renderBlock([
            'id' => 'blk_2',
            'type' => 'promo_banner',
            'props' => ['text' => 'تخفیف ویژه', 'link' => '/shop', 'style' => 'info'],
        ]);

        $this->assertStringNotContainsString('{"text"', $html);
        $this->assertStringContainsString('tb-promo-banner', $html);
        $this->assertStringContainsString('تخفیف ویژه', $html);
    }

    public function test_team_renders_html_not_raw_json(): void
    {
        $html = $this->renderer()->renderBlock([
            'id' => 'blk_3',
            'type' => 'team',
            'props' => ['title' => 'پزشکان و متخصصان ما', 'limit' => 6],
        ]);

        $this->assertStringNotContainsString('{"title"', $html);
        $this->assertStringNotContainsString('"limit"', $html);
        $this->assertStringContainsString('پزشکان و متخصصان ما', $html);
    }

    public function test_team_cards_renders_member_html_not_raw_json(): void
    {
        $html = $this->renderer()->renderBlock([
            'id' => 'blk_4',
            'type' => 'team_cards',
            'props' => ['title' => 'مشاوران املاک ما', 'members' => json_encode([
                ['name' => 'محمد رستمی', 'role' => 'مشاور ارشد', 'image' => '', 'bio' => ''],
            ], JSON_UNESCAPED_UNICODE)],
        ]);

        $this->assertStringNotContainsString('{"name"', $html);
        $this->assertStringContainsString('tb-team-member', $html);
        $this->assertStringContainsString('محمد رستمی', $html);
    }

    /**
     * Existing, already-working block types must remain unaffected by the
     * new renderGallery/renderPromoBanner/renderTeam methods.
     */
    public function test_existing_block_types_remain_compatible(): void
    {
        $renderer = $this->renderer();

        $hero = $renderer->renderBlock(['id' => 'h1', 'type' => 'hero', 'props' => ['title' => 'عنوان', 'background' => 'primary']]);
        $this->assertStringContainsString('tb-hero', $hero);
        $this->assertStringContainsString('tb-bg-primary', $hero);

        $features = $renderer->renderBlock(['id' => 'f1', 'type' => 'features_grid', 'props' => ['title' => 'ویژگی‌ها', 'features' => '[{"icon":"⚡","title":"سریع","text":"متن"}]']]);
        $this->assertStringContainsString('tb-features-grid', $features);
        $this->assertStringContainsString('سریع', $features);

        $contact = $renderer->renderBlock(['id' => 'c1', 'type' => 'contact_split', 'props' => ['phone' => '۰۲۱-۱۲۳۴۵۶۷۸']]);
        $this->assertStringContainsString('tb-contact-split', $contact);
        $this->assertStringContainsString('۰۲۱-۱۲۳۴۵۶۷۸', $contact);
    }
}
