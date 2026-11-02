<?php

namespace Tests\Feature;

use App\Providers\RouteServiceProvider;
use Tests\TestCase;

/**
 * The platform's public front door (panel root). The Sentinel UI verifier
 * (ADR-077) found the stock Laravel page in production: LTR, English,
 * Figtree from fonts.bunny.net (blocked by the CSP), 93 px of sideways
 * scroll, and a "Home" link to the non-existent /home.
 */
class PlatformLandingPageTest extends TestCase
{
    public function test_the_landing_page_is_persian_rtl_and_self_contained(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="fa" dir="rtl">', $html);
        $this->assertStringContainsString("font-family: 'Vazirmatn'", $html);
        $this->assertStringContainsString("url('/assets/fonts/vazirmatn/Vazirmatn-Regular.woff2')", $html);
        $this->assertStringNotContainsString('fonts.bunny.net', $html);
        $this->assertStringNotContainsString('Figtree', $html);
        $this->assertDoesNotMatchRegularExpression('#<(link|script)\b[^>]*(href|src)="https?://#i', $html, 'no third-party stylesheet or script');
        $this->assertStringNotContainsString('<title>Laravel</title>', $html);
        $this->assertStringContainsString('href="' . route('login') . '"', $html);
    }

    public function test_tap_targets_and_layout_rules_are_mobile_safe(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('min-block-size: 2.75rem; min-inline-size: 2.75rem;', $html, '44px buttons');
        $this->assertStringContainsString('inline-size: min(72rem, 100%)', $html, 'the container never exceeds the viewport');
        $this->assertStringContainsString('overflow-wrap: anywhere', $html);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $html);
        $this->assertDoesNotMatchRegularExpression('/(margin|padding)-(left|right)\s*:/', $html, 'logical properties only');
    }

    public function test_a_signed_in_user_goes_to_the_real_dashboard(): void
    {
        $user = \App\Models\User::factory()->make(['id' => 999999]);
        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="' . url(RouteServiceProvider::HOME) . '"', $html);
        $this->assertStringNotContainsString('/home"', $html);
    }
}
