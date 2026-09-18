<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use Tests\TestCase;

/**
 * BLD-01 (Full Product Commercial Audit, TASK 1/3): the shared canonical
 * `SaaSPlatform\ThemeRuntime\BlockRenderer::renderBlock()` had the identical
 * wrong-signature re-dispatch bug as customer-site's own copy — the generic
 * `render{Type}($props, $inner, $id)` dispatch re-invoked
 * `renderTabs(array $tabs)`/`renderAccordion(array $items)` with the whole
 * `$props` array bound to the single declared parameter, discarding the
 * correct `$inner` this same method had just computed. This affects
 * saas-platform's own master-Theme-authoring live preview.
 */
class TabsAccordionRenderTest extends TestCase
{
    private function render(string $type, array $props, string $id = 'b1'): string
    {
        return (new BlockRenderer())->renderBlock(['type' => $type, 'id' => $id, 'props' => $props]);
    }

    public function test_tabs_renders_real_titles_and_content(): void
    {
        $html = $this->render('tabs', ['tabs' => [
            ['title' => 'یک', 'blocks' => [['id' => 'b1', 'type' => 'text', 'props' => ['content' => 'اول']]]],
            ['title' => 'دو', 'blocks' => [['id' => 'b2', 'type' => 'text', 'props' => ['content' => 'دوم']]]],
        ]]);

        $this->assertStringContainsString('یک', $html);
        $this->assertStringContainsString('دو', $html);
        $this->assertStringContainsString('اول', $html);
        $this->assertStringContainsString('دوم', $html);
        // substr_count('tb-tab-panel') would also match the JS selector
        // string ':scope > .tb-tabs-panels > .tb-tab-panel' below — count
        // the actual rendered panel DIVs by their exact class attribute.
        $this->assertSame(2, substr_count($html, 'class="tb-tab-panel"'));

        // TASK 3/3 Phase 5 (BLD-16): real browser certification found tabs
        // had no switching mechanism at all (every panel always visible,
        // `.tb-tabs { }` had no CSS, no script rendered). Regression
        // coverage for the fix: a real nav button per tab, correct
        // aria-selected default, and the switching script actually present.
        $this->assertSame(2, substr_count($html, 'class="tb-tab-nav-btn"'));
        $this->assertStringContainsString('aria-selected="true"', $html);
        $this->assertStringContainsString('aria-selected="false"', $html);
        $this->assertStringContainsString('tbTabsInit', $html);
    }

    public function test_accordion_renders_real_titles_and_content(): void
    {
        $html = $this->render('accordion', ['items' => [
            ['title' => 'سوال', 'blocks' => [['id' => 'b1', 'type' => 'text', 'props' => ['content' => 'پاسخ']]]],
        ]]);

        $this->assertStringContainsString('سوال', $html);
        $this->assertStringContainsString('پاسخ', $html);
        $this->assertSame(1, substr_count($html, 'tb-accordion-item'));
    }

    public function test_empty_tabs_and_accordion_render_nothing_not_a_phantom_panel(): void
    {
        $this->assertStringNotContainsString('tb-tab-panel', $this->render('tabs', ['tabs' => []]));
        $this->assertStringNotContainsString('tb-accordion-item', $this->render('accordion', ['items' => []]));
    }
}
