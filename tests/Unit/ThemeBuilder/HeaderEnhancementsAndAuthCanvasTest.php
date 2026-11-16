<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use Tests\TestCase;

class HeaderEnhancementsAndAuthCanvasTest extends TestCase
{
    private BlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new BlockRenderer();
    }

    public function test_topbar_renders_when_enabled(): void
    {
        $props = [
            'topbar_enable' => true,
            'topbar_phone' => '۰۲۱-۱۲۳۴۵۶۷۸',
            'topbar_email' => 'info@oxinsolar.com',
            'topbar_address' => 'تهران، پارک فناوری',
            'topbar_text' => 'پیشرو در صنعت انرژی خورشیدی',
            'topbar_links' => [
                ['label' => 'درباره ما', 'url' => '/about'],
                ['label' => 'پشتیبانی', 'url' => '/support'],
            ],
            'social_links' => [
                ['network' => 'instagram', 'url' => 'https://instagram.com/demo'],
            ],
            'logo_text' => 'اکسین‌سولار',
        ];

        $html = $this->renderer->renderBlock([
            'type' => 'nav_header',
            'id' => 'hdr-topbar-test',
            'props' => $props,
        ]);

        $this->assertStringContainsString('tb-nav-header-topbar', $html);
        $this->assertStringContainsString('۰۲۱-۱۲۳۴۵۶۷۸', $html);
        $this->assertStringContainsString('info@oxinsolar.com', $html);
        $this->assertStringContainsString('تهران، پارک فناوری', $html);
        $this->assertStringContainsString('پیشرو در صنعت انرژی خورشیدی', $html);
        $this->assertStringContainsString('درباره ما', $html);
        $this->assertStringContainsString('پشتیبانی', $html);
    }

    public function test_topbar_does_not_render_when_disabled(): void
    {
        $props = [
            'topbar_enable' => false,
            'topbar_phone' => '۰۲۱-۱۲۳۴۵۶۷۸',
            'logo_text' => 'اکسین‌سولار',
        ];

        $html = $this->renderer->renderBlock([
            'type' => 'nav_header',
            'id' => 'hdr-notopbar-test',
            'props' => $props,
        ]);

        $this->assertStringNotContainsString('tb-nav-header-topbar', $html);
    }

    public function test_sun_logo_and_rotate_pop_animation(): void
    {
        $props = [
            'logo_text' => 'اکسین‌سولار',
            'logo_icon' => 'fas fa-sun',
            'logo_animation' => 'rotate_pop',
            'logo_animation_duration' => 5,
            'header_icons_animation' => 'pulse',
        ];

        $html = $this->renderer->renderBlock([
            'type' => 'nav_header',
            'id' => 'hdr-anim-test',
            'props' => $props,
        ]);

        $this->assertStringContainsString('tb-logo-anim--rotate_pop', $html);
        $this->assertStringContainsString('fas fa-sun', $html);
        $this->assertStringContainsString('tb-fa-sun-svg', $html);
        $this->assertStringContainsString('--tb-hdr-logo-anim-dur:5s', $html);
        $this->assertStringContainsString('tb-hdr-icons-anim--pulse', $html);
    }

    public function test_universal_auth_canvas_renders_dual_mode_and_hero(): void
    {
        $props = [
            'auth_mode' => 'dual',
            'layout_type' => 'split',
            'brand_name' => 'اکسین‌سولار',
            'brand_tagline' => 'صنعت انرژی و برق خورشیدی',
            'login_title' => 'ورود به حساب کاربری',
            'register_title' => 'ایجاد حساب کاربری جدید',
            'show_hero' => true,
            'hero_title' => 'پلتفرم جامع صنعت انرژی خورشیدی',
            'hero_icon' => 'fas fa-sun',
            'btn_bg_color' => '#FF7900',
        ];

        $html = $this->renderer->renderBlock([
            'type' => 'auth_canvas',
            'id' => 'auth-test',
            'props' => $props,
        ]);

        $this->assertStringContainsString('tb-auth-canvas', $html);
        $this->assertStringContainsString('tb-auth-layout--split', $html);
        $this->assertStringContainsString('tb-auth-tabs', $html);
        $this->assertStringContainsString('ورود به حساب کاربری', $html);
        $this->assertStringContainsString('ایجاد حساب کاربری جدید', $html);
        $this->assertStringContainsString('tb-auth-hero', $html);
        $this->assertStringContainsString('پلتفرم جامع صنعت انرژی خورشیدی', $html);
        $this->assertStringContainsString('fas fa-sun', $html);
        $this->assertStringContainsString('--tb-auth-btn-bg:#FF7900', $html);
    }

    public function test_auth_canvas_is_registered_in_block_registry(): void
    {
        $all = BlockRegistry::all();
        $this->assertArrayHasKey('auth_canvas', $all);
        $this->assertArrayHasKey('login_register', $all);
        $this->assertArrayHasKey('auth_mode', $all['auth_canvas']['schema']);
        $this->assertArrayHasKey('layout_type', $all['auth_canvas']['schema']);
        $this->assertArrayHasKey('page_bg_color', $all['auth_canvas']['schema']);
        $this->assertArrayHasKey('page_bg_image', $all['auth_canvas']['schema']);
        $this->assertArrayHasKey('card_bg_color', $all['auth_canvas']['schema']);
        $this->assertArrayHasKey('btn_bg_color', $all['auth_canvas']['schema']);
    }
}
