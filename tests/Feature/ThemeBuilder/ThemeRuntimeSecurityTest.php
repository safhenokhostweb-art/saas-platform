<?php

namespace Tests\Feature\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\BlockRenderer;
use Tests\TestCase;

/**
 * Phase 2j — Phase 2i found the shared canonical renderer was, in two real
 * ways, LESS safe than customer-site's independent fork: no escapeAttr()
 * on inline-style colors (BlockRenderer::buildCssDeclarations, fixed in
 * Phase 2i), and no javascript:-scheme rejection on href/src URLs
 * (BlockRenderer::escapeUrl/isValidUrl, added this phase, promoted from the
 * Element SDK's WithSanitization trait). This suite proves the shared
 * authority is now at least as safe as either previous implementation, not
 * just structurally consolidated.
 */
class ThemeRuntimeSecurityTest extends TestCase
{
    private function renderer(): BlockRenderer
    {
        return new BlockRenderer();
    }

    // ── URL scheme rejection ────────────────────────────────────────────────

    public function test_javascript_scheme_url_is_rejected_on_button(): void
    {
        $html = $this->renderer()->renderBlock(['type' => 'button', 'id' => 'b1', 'props' => [
            'text' => 'کلیک', 'url' => 'javascript:alert(1)',
        ]]);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('href="#"', $html);
    }

    public function test_javascript_scheme_url_is_rejected_on_hero_button(): void
    {
        $html = $this->renderer()->renderBlock(['type' => 'hero', 'id' => 'h1', 'props' => [
            'title' => 'عنوان', 'button_text' => 'دکمه', 'button_url' => 'JavaScript:alert(document.cookie)',
        ]]);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('Javascript:', $html);
    }

    public function test_javascript_scheme_url_is_rejected_on_image(): void
    {
        $html = $this->renderer()->renderBlock(['type' => 'image', 'id' => 'i1', 'props' => [
            'url' => 'javascript:alert(1)', 'alt' => 'x',
        ]]);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('tb-image-placeholder', $html);
    }

    public function test_a_scheme_less_garbage_string_is_treated_as_a_harmless_relative_path(): void
    {
        // No scheme = nothing to exploit; a broken-but-harmless href is
        // safe (and correct — the same class of string a real relative
        // path check must not reject, see test_relative_internal_paths_...).
        // The safety property under test is XSS/attribute-injection, not URL well-formedness.
        $html = $this->renderer()->renderBlock(['type' => 'button', 'id' => 'b2', 'props' => [
            'text' => 'x', 'url' => 'not a url at all ###',
        ]]);
        $this->assertStringContainsString('href="not a url at all ###"', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_a_genuinely_valid_url_passes_through(): void
    {
        $html = $this->renderer()->renderBlock(['type' => 'button', 'id' => 'b3', 'props' => [
            'text' => 'x', 'url' => 'https://example.com/path?x=1',
        ]]);
        $this->assertStringContainsString('href="https://example.com/path?x=1"', $html);
    }

    public function test_relative_and_hash_urls_are_still_accepted(): void
    {
        $html = $this->renderer()->renderBlock(['type' => 'button', 'id' => 'b4', 'props' => [
            'text' => 'x', 'url' => '#',
        ]]);
        $this->assertStringContainsString('href="#"', $html);
    }

    /**
     * Real regression: the URL check first promoted from the Element SDK
     * used FILTER_VALIDATE_URL, which rejects relative paths — common,
     * safe, everyday internal links (no scheme to exploit). Found by
     * CanonicalBlockRenderersTest's service_cards test the moment
     * escapeUrl() was applied broadly.
     */
    public function test_relative_internal_paths_are_accepted_not_treated_as_invalid(): void
    {
        foreach (['/services/design', 'about.html', '?ref=1', '../parent', 'مسیر/فارسی'] as $relative) {
            $html = $this->renderer()->renderBlock(['type' => 'button', 'id' => 'b6', 'props' => [
                'text' => 'x', 'url' => $relative,
            ]]);
            $this->assertStringContainsString('href="' . htmlspecialchars($relative, ENT_QUOTES, 'UTF-8') . '"', $html, "Relative URL '{$relative}' was incorrectly rejected.");
        }
    }

    public function test_protocol_relative_and_mailto_tel_urls_are_accepted(): void
    {
        foreach (['//cdn.example.com/x', 'mailto:a@b.com', 'tel:+123456'] as $url) {
            $html = $this->renderer()->renderBlock(['type' => 'button', 'id' => 'b7', 'props' => ['text' => 'x', 'url' => $url]]);
            $this->assertStringContainsString('href="' . $url . '"', $html);
        }
    }

    public function test_unlisted_dangerous_looking_schemes_are_rejected(): void
    {
        foreach (['file:///etc/passwd', 'ftp://x', 'vbscript:msgbox(1)'] as $url) {
            $html = $this->renderer()->renderBlock(['type' => 'button', 'id' => 'b8', 'props' => ['text' => 'x', 'url' => $url]]);
            $this->assertStringContainsString('href="#"', $html, "Dangerous/unlisted scheme '{$url}' was not rejected.");
        }
    }

    // ── attribute/HTML escaping ──────────────────────────────────────────────

    public function test_html_injection_in_text_props_is_escaped(): void
    {
        $html = $this->renderer()->renderBlock(['type' => 'heading', 'id' => 'h2', 'props' => [
            'text' => '<script>alert(1)</script>',
        ]]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_attribute_breakout_attempt_in_color_style_is_escaped(): void
    {
        $html = $this->renderer()->renderBlock(['type' => 'heading', 'id' => 'h3', 'props' => [
            'text' => 'x',
        ], 'style' => [
            'textColor' => '"><script>alert(1)</script>',
        ]]);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_button_style_prop_cannot_break_out_of_the_class_attribute(): void
    {
        $html = $this->renderer()->renderBlock(['type' => 'button', 'id' => 'b5', 'props' => [
            'text' => 'x', 'url' => '#', 'style' => '"><script>alert(1)</script>',
        ]]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('tb-btn-primary', $html);
    }

    public function test_rich_text_content_only_allows_the_whitelisted_tags(): void
    {
        $html = $this->renderer()->renderBlock(['type' => 'text', 'id' => 't1', 'props' => [
            'content' => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(1)>',
        ]]);
        $this->assertStringContainsString('<p>ok</p>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_malformed_style_object_does_not_throw(): void
    {
        // Style keys as unexpected types (arrays where strings expected) must
        // never crash rendering — a theme with corrupted authored data should
        // degrade, not 500.
        $html = $this->renderer()->renderBlock(['type' => 'heading', 'id' => 'h4', 'props' => ['text' => 'x'], 'style' => [
            'textColor' => ['not', 'a', 'string'],
        ]]);
        $this->assertStringContainsString('tb-heading', $html);
    }
}
