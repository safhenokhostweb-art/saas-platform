<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use Tests\TestCase;

/**
 * Placeholder re-evaluation, `countdown` (Full Product Commercial Audit,
 * TASK 2A): previously showed a static "set an end date" hint forever,
 * with no JS ever reading `data-end` — no real countdown ever ran even
 * once an end_date was authored.
 */
class CountdownRenderTest extends TestCase
{
    private function render(array $props, string $id = 'b1'): string
    {
        return (new BlockRenderer())->renderBlock(['type' => 'countdown', 'id' => $id, 'props' => $props]);
    }

    public function test_no_end_date_shows_the_honest_hint_only(): void
    {
        $html = $this->render(['label' => 'حراج ویژه']);
        $this->assertStringContainsString('تاریخ پایان را در تنظیمات المان وارد کنید', $html);
        $this->assertStringContainsString('حراج ویژه', $html);
        $this->assertStringNotContainsString('tb-countdown__timer', $html);
    }

    public function test_real_end_date_renders_a_real_timer_not_just_the_hint(): void
    {
        $future = date('c', strtotime('+3 days'));
        $html = $this->render(['label' => 'پایان تخفیف', 'end_date' => $future]);

        $this->assertStringContainsString('tb-countdown__timer', $html);
        $this->assertStringContainsString($future, $html);
        $this->assertStringContainsString('setInterval', $html, 'must ship a real ticking timer, not static markup');
        $this->assertStringNotContainsString('تاریخ پایان را در تنظیمات المان وارد کنید', $html, 'the "please set a date" hint must not still show once one is set');
    }
}
