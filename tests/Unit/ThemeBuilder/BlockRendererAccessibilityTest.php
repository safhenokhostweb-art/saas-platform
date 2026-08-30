<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

/**
 * Phase 2b — root-cause fix for accessibility.form_field_missing_label
 * (26/27 reconstructed catalog themes failed the Commercial Quality Gate on
 * this before the fix). Proves the fix at its actual source — BlockRenderer,
 * the shared rendering boundary every theme goes through — rather than
 * patching authored theme data.
 */
class BlockRendererAccessibilityTest extends TestCase
{
    private function xpathFor(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    public function test_contact_split_input_fields_have_accessible_labels(): void
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'contact_split', 'id' => 'c1', 'props' => []]);
        $xpath = $this->xpathFor($html);

        foreach ($xpath->query('//input') as $input) {
            $id = $input->getAttribute('id');
            $this->assertNotSame('', $id, 'every input must have a stable id');
            $this->assertGreaterThan(0, $xpath->query("//label[@for='{$id}']")->length, "input #{$id} has no associated label");
        }
    }

    public function test_contact_split_textarea_has_an_accessible_label(): void
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'contact_split', 'id' => 'c1', 'props' => []]);
        $xpath = $this->xpathFor($html);

        $textareas = $xpath->query('//textarea');
        $this->assertGreaterThan(0, $textareas->length);
        foreach ($textareas as $textarea) {
            $id = $textarea->getAttribute('id');
            $this->assertNotSame('', $id);
            $this->assertGreaterThan(0, $xpath->query("//label[@for='{$id}']")->length);
        }
    }

    public function test_newsletter_form_input_has_an_accessible_label(): void
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'newsletter_form', 'id' => 'n1', 'props' => []]);
        $xpath = $this->xpathFor($html);

        $input = $xpath->query('//input')->item(0);
        $this->assertNotNull($input);
        $id = $input->getAttribute('id');
        $this->assertNotSame('', $id);
        $this->assertGreaterThan(0, $xpath->query("//label[@for='{$id}']")->length);
    }

    public function test_multiple_contact_forms_on_one_page_produce_no_id_collisions(): void
    {
        $renderer = new BlockRenderer();
        $html = $renderer->renderBlock(['type' => 'contact_split', 'id' => 'block-a', 'props' => []])
            . $renderer->renderBlock(['type' => 'contact_split', 'id' => 'block-b', 'props' => []]);

        $xpath = $this->xpathFor($html);
        $ids = [];
        foreach ($xpath->query('//input | //textarea') as $field) {
            $id = $field->getAttribute('id');
            $this->assertNotSame('', $id);
            $this->assertArrayNotHasKey($id, $ids, "duplicate field id \"{$id}\" across two blocks on the same page");
            $ids[$id] = true;
        }
        $this->assertCount(6, $ids); // 2 inputs + 1 textarea, per block, x2 blocks
    }

    public function test_form_functional_behavior_and_rtl_styling_are_unaffected(): void
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'contact_split', 'id' => 'c1', 'props' => ['title' => 'تماس با ما']]);

        // Submit handler, action-less client-side form, and visible button
        // text are all preserved exactly as before the fix.
        $this->assertStringContainsString('onsubmit="event.preventDefault();"', $html);
        $this->assertStringContainsString('ارسال پیام', $html);
        $this->assertStringContainsString('تماس با ما', $html);
        // Visual placeholders (what a sighted user actually sees) are unchanged.
        $this->assertStringContainsString('placeholder="نام شما"', $html);
    }

    public function test_visually_hidden_label_style_does_not_alter_visible_layout(): void
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'contact_split', 'id' => 'c1', 'props' => []]);

        // The standard, W3C-documented visually-hidden pattern — not
        // display:none (which would also hide it from screen readers).
        $this->assertStringContainsString('clip:rect(0,0,0,0)', $html);
        $this->assertStringNotContainsString('display:none', $html);
    }
}
