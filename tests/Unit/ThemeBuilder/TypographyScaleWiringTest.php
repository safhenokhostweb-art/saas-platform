<?php

namespace Tests\Unit\ThemeBuilder;

use Tests\TestCase;

/**
 * Commercial Productization fase-4 — `--tb-scale` (ThemeTokenNormalizer)
 * was defined but genuinely never consumed anywhere in either host's CSS:
 * a Theme author's typography-scale choice in the Builder had zero visible
 * effect on real page output. These tests assert the derived --tb-*-size
 * tokens exist, actually compound the author's scale ratio (not a fixed
 * value), and that both hosts' own heading/hero/section/card-title/button
 * rules genuinely reference them (not a second, independently hardcoded
 * copy of the same sizes).
 */
class TypographyScaleWiringTest extends TestCase
{
    private function contractCss(): string
    {
        return file_get_contents(base_path('packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-contract.css'));
    }

    public function test_shared_contract_defines_a_full_derived_type_scale_compounding_the_real_scale_token(): void
    {
        $css = $this->contractCss();
        foreach (['--tb-h1-size', '--tb-h2-size', '--tb-h3-size', '--tb-h4-size', '--tb-h5-size', '--tb-h6-size', '--tb-hero-title-size', '--tb-section-title-size', '--tb-card-title-size', '--tb-big-number-size'] as $token) {
            $this->assertStringContainsString($token, $css);
        }
        // h1 must compound var(--tb-scale) more times than h4 — a genuine
        // multiplicative scale, not four independently hardcoded sizes.
        preg_match('/--tb-h1-size:\s*([^;]+);/', $css, $h1);
        preg_match('/--tb-h4-size:\s*([^;]+);/', $css, $h4);
        $this->assertNotEmpty($h1[1] ?? null);
        $this->assertNotEmpty($h4[1] ?? null);
        $this->assertGreaterThan(substr_count($h4[1], 'var(--tb-scale'), substr_count($h1[1], 'var(--tb-scale'));
    }

    public function test_body_caption_label_button_route_through_the_token_system_without_multiplying_by_the_heading_scale(): void
    {
        $css = $this->contractCss();
        foreach (['--tb-body-size', '--tb-caption-size', '--tb-label-size', '--tb-button-size'] as $token) {
            $this->assertStringContainsString($token, $css);
            preg_match('/'.preg_quote($token, '/').':\s*([^;]+);/', $css, $m);
            $this->assertStringNotContainsString('--tb-scale', $m[1] ?? '', "{$token} must not multiply by the heading scale ratio — it would look absurd blown up by scale^4");
        }
    }

    /**
     * Phase 0.3 (ADR-063): both hosts' block rules are the shared package
     * stylesheet, so the derived tokens are asserted there once, and each
     * host is asserted to include it.
     */
    public function test_shared_block_stylesheet_headings_and_titles_reference_the_derived_tokens(): void
    {
        $css = file_get_contents(base_path('packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css'));
        foreach (['var(--tb-h1-size', 'var(--tb-hero-title-size', 'var(--tb-section-title-size', 'var(--tb-card-title-size', 'var(--tb-big-number-size', 'var(--tb-button-size', 'var(--tb-caption-size', 'var(--tb-label-size'] as $ref) {
            $this->assertStringContainsString($ref, $css);
        }
    }

    public function test_customer_site_runtime_includes_the_shared_block_stylesheet(): void
    {
        $customer = base_path('../customer-site/resources/views');
        if (! is_dir($customer)) {
            $this->markTestSkipped('customer-site checkout not present alongside this one — cross-repo assertion skipped.');
        }
        $layout = file_get_contents($customer.'/layouts/canvas-theme.blade.php');
        $this->assertStringContainsString("@include('partials.theme-canvas-style-contract'", $layout);
        $partial = file_get_contents($customer.'/partials/theme-canvas-style-contract.blade.php');
        $this->assertStringContainsString("@include('theme-runtime::css.blocks')", $partial);
        $this->assertStringContainsString('var(--tb-body-size', $partial);
    }

    public function test_saas_preview_includes_the_same_shared_block_stylesheet(): void
    {
        $preview = file_get_contents(base_path('resources/views/admin/theme-builder/canvas-preview.blade.php'));
        $this->assertStringContainsString("@include('admin.theme-builder.partials.canvas-css-contract')", $preview);
        $partial = file_get_contents(base_path('resources/views/admin/theme-builder/partials/canvas-css-contract.blade.php'));
        $this->assertStringContainsString("@include('theme-runtime::css.blocks')", $partial);
    }
}
