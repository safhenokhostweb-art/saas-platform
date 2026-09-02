<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer;
use Tests\TestCase;

/**
 * Phase 3E-C — direct unit coverage of the extracted, single sanitization
 * authority. BlockRenderer's own behavior is already covered end-to-end by
 * ThemeRuntimeSecurityTest (Phase 2j/3C); this suite proves the extraction
 * itself carries the exact same security semantics in isolation, since
 * App\Builder\* (customer-site's Element SDK) now calls this class directly
 * and cannot inherit BlockRenderer's protected wrappers.
 */
class HtmlSanitizerTest extends TestCase
{
    // A. event handler on an otherwise-whitelisted tag
    public function test_a_event_handler_attribute_is_stripped(): void
    {
        $html = HtmlSanitizer::sanitizeRichHtml('<a onmouseover="alert(1)">x</a>');
        $this->assertStringNotContainsString('onmouseover', $html);
        $this->assertSame('<a href="#">x</a>', $html);
    }

    // B. javascript: scheme href
    public function test_b_javascript_scheme_href_is_neutralized(): void
    {
        $html = HtmlSanitizer::sanitizeRichHtml('<a href="javascript:alert(1)">x</a>');
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame('<a href="#">x</a>', $html);
    }

    // C. disallowed tag with a dangerous src
    public function test_c_disallowed_tag_with_dangerous_src_is_removed_entirely(): void
    {
        $html = HtmlSanitizer::sanitizeRichHtml('<img src="javascript:alert(1)">');
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    // D. mixed-case / whitespace-obfuscated scheme
    public function test_d_mixed_case_and_leading_whitespace_scheme_is_rejected(): void
    {
        $this->assertFalse(HtmlSanitizer::isValidUrl('  JaVaScRiPt:alert(1)'));
        $html = HtmlSanitizer::sanitizeRichHtml('<a href="  JaVaScRiPt:alert(1)">x</a>');
        $this->assertStringNotContainsString('cript:', $html);
        $this->assertSame('<a href="#">x</a>', $html);
    }

    // E. safe https link
    public function test_e_safe_https_link_is_preserved(): void
    {
        $html = HtmlSanitizer::sanitizeRichHtml('<a href="https://example.com/docs">docs</a>');
        $this->assertSame('<a href="https://example.com/docs">docs</a>', $html);
    }

    // F. safe relative URL
    public function test_f_safe_relative_url_is_preserved(): void
    {
        $html = HtmlSanitizer::sanitizeRichHtml('<a href="/services/design">svc</a>');
        $this->assertSame('<a href="/services/design">svc</a>', $html);
    }

    // G. allowed formatting tags remain intact
    public function test_g_allowed_formatting_tags_are_preserved(): void
    {
        $html = HtmlSanitizer::sanitizeRichHtml('<p>Hello <strong>World</strong></p>');
        $this->assertSame('<p>Hello <strong>World</strong></p>', $html);
    }

    // H. unknown/disallowed attributes never survive on non-anchor tags
    public function test_h_unknown_attributes_do_not_survive_on_non_anchor_tags(): void
    {
        $html = HtmlSanitizer::sanitizeRichHtml('<p class="evil" onclick="alert(1)">text</p>');
        $this->assertSame('<p>text</p>', $html);
    }

    // I. plain text is unchanged
    public function test_i_plain_text_is_unchanged(): void
    {
        $this->assertSame('Hello World', HtmlSanitizer::sanitizeRichHtml('Hello World'));
    }

    public function test_disallowed_tag_is_stripped_but_inner_text_kept(): void
    {
        $html = HtmlSanitizer::sanitizeRichHtml('<p>ok</p><script>alert(1)</script>');
        $this->assertStringContainsString('<p>ok</p>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('alert(1)', $html); // text survives, tags don't
    }

    public function test_empty_href_falls_back_to_hash(): void
    {
        $this->assertSame('#', HtmlSanitizer::escapeUrl(''));
    }

    public function test_unlisted_dangerous_scheme_falls_back(): void
    {
        $this->assertSame('#', HtmlSanitizer::escapeUrl('vbscript:msgbox(1)'));
    }
}
