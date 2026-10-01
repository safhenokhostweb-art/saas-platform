<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\DefaultHomepage;
use App\Services\ThemeBuilder\BlockRenderer;
use Tests\TestCase;

class DefaultHomepageTest extends TestCase
{
    public function test_default_registration_preserves_explicit_documents(): void
    {
        $data = DefaultHomepage::withDefaults(['name' => 'کسب‌وکار']);
        self::assertCount(3, $data['pages']['home']['blocks']);
        self::assertCount(1, $data['header_blocks']);
        self::assertCount(1, $data['footer_blocks']);
        self::assertSame(['pages' => []], DefaultHomepage::withDefaults(['pages' => []]));
        self::assertSame(['type' => 'header'], DefaultHomepage::withDefaults(['type' => 'header']));
    }

    public function test_shared_rendering_preserves_chrome_roots_and_content_order(): void
    {
        $document = DefaultHomepage::document();
        foreach (['editor', 'public'] as $mode) {
            $renderer = (new BlockRenderer())->setMode($mode)->setContext(['editor_mode' => $mode === 'editor']);
            $header = $renderer->renderBlocks($document['header_blocks']);
            $footer = $renderer->renderBlocks($document['footer_blocks']);
            self::assertSame(1, substr_count($header, 'data-layout-slot="header"'));
            self::assertSame(1, substr_count($footer, 'data-layout-slot="footer"'));
            $content = $renderer->renderBlocks($document['pages']['home']['blocks']);
            self::assertStringNotContainsString('data-layout-slot', $content);
            self::assertStringContainsString('محصولات جدید به‌زودی', $content);
            self::assertStringContainsString('کسب‌وکار شما، آماده رشد', $content);
        }
    }
}
