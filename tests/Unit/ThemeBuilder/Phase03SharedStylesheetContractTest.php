<?php

namespace Tests\Unit\ThemeBuilder;

use Tests\TestCase;

/**
 * Phase 0.3 / 0.4 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-063):
 * block CSS and the Vazirmatn faces have one source in the theme-runtime
 * package. Both hosts include them; neither keeps a copy that could drift.
 */
class Phase03SharedStylesheetContractTest extends TestCase
{
    private const PACKAGE = 'packages/saas-platform/modules/theme-runtime/resources';

    /** Rules customer-site keeps because only its own markup or script needs them. */
    private const CUSTOMER_HOST_ONLY = [
        '.tb-notifications-bar', '.tb-skill-bar-fill[data-width]', '.tb-skill-bar-fill.animated',
    ];

    private function blocksCss(): string
    {
        return file_get_contents(base_path(self::PACKAGE . '/css/theme-runtime-blocks.css'));
    }

    /** @return list<string> selectors of a Blade partial's own rules (comments and includes stripped) */
    private function ownSelectors(string $blade): array
    {
        $css = preg_replace(['/\{\{--.*?--\}\}/s', '#/\*.*?\*/#s', '/@include\([^)]*\)/', '/\{!!.*?!!\}/s'], '', $blade);
        preg_match_all('/([^{}]+)\{[^{}]*\}/', $css, $m);
        return array_map('trim', $m[1]);
    }

    public function test_the_platform_preview_partial_keeps_no_block_rules(): void
    {
        $partial = file_get_contents(base_path('resources/views/admin/theme-builder/partials/canvas-css-contract.blade.php'));
        $this->assertStringContainsString("@include('theme-runtime::css.contract')", $partial);
        $this->assertStringContainsString("@include('theme-runtime::css.blocks')", $partial);
        $this->assertSame(['body'], $this->ownSelectors($partial));
    }

    public function test_the_customer_runtime_partial_keeps_only_host_chrome(): void
    {
        $path = base_path('../customer-site/resources/views/partials/theme-canvas-style-contract.blade.php');
        if (!is_file($path)) {
            $this->markTestSkipped('customer-site checkout not present alongside this one — cross-repo assertion skipped.');
        }
        $partial = file_get_contents($path);
        $this->assertStringContainsString("@include('theme-runtime::css.blocks')", $partial);
        $blockRules = array_filter(
            $this->ownSelectors($partial),
            fn ($s) => str_contains($s, '.tb-') && !in_array($s, self::CUSTOMER_HOST_ONLY, true) && !str_starts_with($s, '.canvas-site-header')
        );
        $this->assertSame([], array_values($blockRules), 'Block rules belong in theme-runtime-blocks.css.');
    }

    public function test_the_shared_sheet_is_well_formed_and_has_the_phase_fixes(): void
    {
        $css = $this->blocksCss();
        $this->assertSame(substr_count($css, '{'), substr_count($css, '}'));
        // `auto-rows` is not a CSS property; every browser dropped it.
        $this->assertDoesNotMatchRegularExpression('/(?<![-\w])auto-rows\s*:/', $css);
        $this->assertMatchesRegularExpression('/\.tb-bento-grid\s*\{[^}]*grid-auto-rows:minmax\(160px,auto\)/', $css);

        // The stale preview-only rules that out-ranked the live ones are gone.
        foreach (['.tb-hero h1', '.tb-hero p', '.tb-hero .tb-btn', '.tb-cta h2', '.tb-cta p', '.tb-cta a'] as $selector) {
            $this->assertDoesNotMatchRegularExpression('/^' . preg_quote($selector, '/') . '\s*\{/m', $css, $selector);
        }
    }

    public function test_hero_titles_have_no_negative_tracking_in_rtl(): void
    {
        $css = $this->blocksCss();
        foreach (['.tb-hero-split-title', '.tb-hero-gradient-title'] as $selector) {
            preg_match_all('/^([^{}\n]*' . preg_quote($selector, '/') . '[^{}\n]*)\{([^}]*)\}/m', $css, $rules, PREG_SET_ORDER);
            $this->assertNotEmpty($rules, $selector);
            foreach ($rules as [, $sel, $body]) {
                if (preg_match('/letter-spacing\s*:\s*-/', $body)) {
                    $this->assertStringStartsWith('[dir="ltr"]', trim($sel), "{$selector}: negative letter-spacing must be LTR-only.");
                }
            }
        }
    }

    public function test_previews_load_the_local_vazirmatn_faces_and_no_google_fonts(): void
    {
        foreach (['admin/theme-builder/canvas-preview', 'admin/theme-builder/canvas-live-preview', 'layouts/canvas'] as $view) {
            $source = file_get_contents(base_path("resources/views/{$view}.blade.php"));
            $this->assertStringNotContainsString('fonts.googleapis.com', $source, $view);
            $this->assertStringNotContainsString('fonts.gstatic.com', $source, $view);
        }
        foreach (['admin/theme-builder/canvas-preview', 'admin/theme-builder/canvas-live-preview'] as $view) {
            $this->assertStringContainsString("@include('theme-runtime::css.fonts')", file_get_contents(base_path("resources/views/{$view}.blade.php")), $view);
        }

        // customer-site ships no font-CDN reference in any view either.
        $customerViews = base_path('../customer-site/resources/views');
        if (is_dir($customerViews)) {
            $offenders = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($customerViews, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (str_ends_with($file->getFilename(), '.blade.php') && preg_match('/fonts\.(googleapis|gstatic)\.com/', file_get_contents($file->getPathname()))) {
                    $offenders[] = substr($file->getPathname(), strlen($customerViews) + 1);
                }
            }
            $this->assertSame([], $offenders, 'customer-site views loading a font CDN');
        }

        $faces = file_get_contents(base_path(self::PACKAGE . '/css/vazirmatn-font-faces.css'));
        preg_match_all("#url\('/(assets/fonts/vazirmatn/[^']+\.woff2)'\)#", $faces, $m);
        $this->assertCount(5, $m[1]);
        foreach ($m[1] as $file) {
            $this->assertFileExists(public_path($file));
        }
    }
}
