<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\BlockRenderer as SharedBlockRenderer;
use SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider;
use Tests\TestCase;

class HeaderModernBlockTest extends TestCase
{
    public function test_definition_is_valid_and_registered_for_the_canvas(): void
    {
        $definition = json_decode(
            (string) file_get_contents(ThemeRuntimeServiceProvider::headerModernDefinitionPath()),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $registered = BlockRegistry::all()['header_modern_v1'] ?? null;

        $this->assertIsArray($registered);
        $this->assertSame('header_modern_v1', $definition['type']);
        $this->assertSame(['bgColor', 'textColor', 'ctaColor'], $definition['fastPathProps']);
        foreach (['bgColor', 'textColor', 'ctaColor', 'logoUrl', 'menuItems'] as $prop) {
            $this->assertArrayHasKey($prop, $definition['schema']);
            $this->assertArrayHasKey($prop, $registered['schema']);
        }
    }

    public function test_renderer_outputs_safe_rtl_navigation_and_packaged_assets(): void
    {
        $html = (new BlockRenderer())
            ->setMode(SharedBlockRenderer::MODE_PUBLIC)
            ->renderBlock([
                'type' => 'header_modern_v1',
                'id' => 'header-1',
                'props' => [
                    'brandName' => 'فروشگاه من',
                    'logoUrl' => '/images/logo.svg',
                    'menuItems' => [
                        ['label' => 'خانه', 'url' => '/'],
                        ['label' => '<script>alert(1)</script>', 'url' => 'javascript:alert(1)'],
                    ],
                    'ctaText' => 'شروع کنید',
                    'ctaUrl' => '/start',
                ],
            ]);

        $this->assertStringContainsString('data-tb-block-type="header_modern_v1"', $html);
        $this->assertStringContainsString('class="tb-header-modern"', $html);
        $this->assertStringContainsString('aria-controls="tb-header-modern-menu-header-1"', $html);
        $this->assertStringContainsString('src="/images/logo.svg"', $html);
        $this->assertStringContainsString('href="/start"', $html);
        $this->assertStringContainsString('direction: rtl', $html);
        $this->assertStringContainsString('HeaderModern.type = \'header_modern_v1\'', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringContainsString('href="#"', $html);
    }

    public function test_renderer_rejects_css_injection_and_emits_assets_once(): void
    {
        $renderer = (new BlockRenderer())->setMode(SharedBlockRenderer::MODE_PUBLIC);
        $first = $renderer->renderBlock([
            'type' => 'header_modern_v1',
            'id' => 'first',
            'props' => ['bgColor' => '#fff;position:fixed', 'ctaColor' => '#ffffff'],
        ]);
        $second = $renderer->renderBlock([
            'type' => 'header_modern_v1',
            'id' => 'second',
            'props' => [],
        ]);

        $this->assertMatchesRegularExpression('/class="tb-header-modern" style="([^"]+)"/', $first);
        preg_match('/class="tb-header-modern" style="([^"]+)"/', $first, $styleMatch);
        $this->assertStringNotContainsString('position:fixed', $styleMatch[1]);
        $this->assertStringContainsString('--tb-header-bg:#ffffff', $first);
        $this->assertStringContainsString('--tb-header-cta-text:#111827', $first);
        $this->assertSame(2, substr_count($first . $second, 'data-tb-block-asset="header_modern_v1"'));
    }
}
