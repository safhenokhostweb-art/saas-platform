<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use Tests\TestCase;

/**
 * Placeholder re-evaluation, `countdown` (Full Product Commercial Audit,
 * TASK 2A): previously showed a static "set an end date" hint forever,
 * with no JS ever reading `data-end` — no real countdown ever ran even
 * once an end_date was authored.
 *
 * Phase 9.3: the ticker moved out of the renderer's inline `<script>` and
 * into resources/js/blocks/countdown_adapter.js. The assertions below are
 * therefore about the ADAPTER being the carrier, not about the literal
 * string 'setInterval' — the adapter still schedules a timer, so its
 * presence can no longer be inferred from the rendered bytes alone. What is
 * pinned instead is (a) that the adapter really is inlined, (b) that the
 * pre-migration inline entry point is gone, and (c) the shape of the
 * rendered markup, which must not have moved.
 */
class CountdownRenderTest extends TestCase
{
    /**
     * Structural snapshots: the rendered markup with <script> BODIES stripped
     * and collapsed to a bare `<script></script>`.
     *
     * This is what makes "the markup did not change" a checkable claim across
     * the migration. The adapter replaces the inline body — which the
     * normalisation discards — so a byte-for-byte match here proves the
     * <script> element kept its position, its attributes and its
     * surrounding whitespace, and that no other node was added or removed.
     */
    private const NO_DATE_STRUCT = '35616221b9a4a3599d0dd839576427a15c844ab8443364c3383c1b3cb08e5f55';
    // Re-pinned on purpose in Phase 3 (ADR-068): the timer moved from inline
    // styles to the shared sheet and gained role="timer"; the expiry line is
    // `hidden`. The no-date shape did not change.
    private const WITH_DATE_STRUCT = 'bbf52229e6c4ff4cc2c9ee06f77f71e6727a5fc8e6bc87e34ad52071804347b8';

    protected function setUp(): void
    {
        parent::setUp();

        // The carrier is emitted once per document via a static gate, so
        // every test must start from a clean slate or the first render in the
        // process would starve the rest.
        BlockRenderer::resetCountdownAdapterEmission();
    }

    private function render(array $props, string $id = 'b1'): string
    {
        return (new BlockRenderer())->renderBlock(['type' => 'countdown', 'id' => $id, 'props' => $props]);
    }

    /** Strips <script> bodies so only the surrounding markup is compared. */
    private function structural(string $html): string
    {
        return (string) preg_replace('#<script\b[^>]*>.*?</script>#s', '<script></script>', $html);
    }

    public function test_no_end_date_shows_the_honest_hint_only(): void
    {
        $html = $this->render(['label' => 'حراج ویژه']);
        $this->assertStringContainsString('تاریخ پایان را در تنظیمات المان وارد کنید', $html);
        $this->assertStringContainsString('حراج ویژه', $html);
        $this->assertStringNotContainsString('tb-countdown__timer', $html);
    }

    /**
     * A countdown with no target date has nothing to tick, so the server
     * must ship no script for it at all — not an empty one, not a guard-only
     * one. This is the behaviour that lets the adapter stay self-skipping:
     * it only ever initialises roots carrying a data-end-date.
     */
    public function test_no_end_date_emits_no_script_at_all(): void
    {
        $html = $this->render(['label' => 'SPEC-LBL']);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertSame(0, preg_match_all('#<script\b#', $html));
        $this->assertStringNotContainsString('data-end-date', $html);
    }

    public function test_real_end_date_renders_a_real_timer_not_just_the_hint(): void
    {
        $future = date('c', strtotime('+3 days'));
        $html = $this->render(['label' => 'پایان تخفیف', 'end_date' => $future]);

        $this->assertStringContainsString('tb-countdown__timer', $html);
        $this->assertStringContainsString($future, $html);
        $this->assertStringNotContainsString('تاریخ پایان را در تنظیمات المان وارد کنید', $html, 'the "please set a date" hint must not still show once one is set');
    }

    /**
     * Replaces the old `assertStringContainsString('setInterval', ...)`
     * check. The adapter still schedules a timer, so the rendered bytes can
     * no longer prove a ticking clock by containing that word; what they can
     * prove is WHICH code is carrying it.
     */
    public function test_the_carrier_is_the_packaged_adapter_not_an_inline_copy(): void
    {
        $html = $this->render(['end_date' => date('c', strtotime('+3 days'))]);

        $this->assertStringContainsString('ThemeCountdown', $html, 'the packaged adapter must be what the renderer inlines');

        $this->assertStringNotContainsString(
            'document.currentScript.closest',
            $html,
            'the pre-migration inline entry point must be gone — it only worked for the first script '
            . 'tag in a document, which the once-per-document shared asset makes untrue'
        );

        $this->assertStringContainsString(
            'clearInterval',
            $html,
            'the adapter must be able to cancel its timer on teardown, which the inline copy could not do'
        );
    }

    /**
     * The single-instance markup must be exactly what it was before the
     * adapter existed. Only the <script> body changed, and the normalisation
     * discards bodies, so these two hashes pin the entire rest of the output.
     */
    public function test_rendered_markup_is_unchanged_by_the_adapter_migration(): void
    {
        $noDate = $this->render(['label' => 'SPEC-LBL']);
        $this->assertSame(
            self::NO_DATE_STRUCT,
            hash('sha256', $this->structural($noDate)),
            'the no-date countdown markup must not have moved'
        );

        $withDate = $this->render([
            'label'    => 'SPEC-LBL',
            'end_date' => '2027-01-01T00:00:00+00:00',
        ]);
        $this->assertSame(
            self::WITH_DATE_STRUCT,
            hash('sha256', $this->structural($withDate)),
            'the with-date countdown markup must not have moved'
        );

        // Exactly one script element: the carrier. Zero would mean the
        // countdown silently lost its ticker; two would mean the asset is
        // being inlined per instance again.
        $this->assertSame(1, preg_match_all('#<script\b#', $withDate));
    }

    /**
     * A page with N countdowns pays for the adapter once. The gate is a
     * static, so short-lived renderer instances share it — and the second
     * instance must contribute NO <script> element rather than an empty one,
     * which is the bug the carousel carrier's contract was written to avoid.
     */
    /** Phase 3 (ADR-068): a Jalali date in any digit script ends that day, Tehran time. */
    public function test_a_jalali_end_date_targets_the_end_of_that_day_in_tehran(): void
    {
        foreach (['1405/08/15', '۱۴۰۵/۰۸/۱۵', '1405-8-15', '۱۴۰۵.۰۸.۱۵'] as $value) {
            BlockRenderer::resetCountdownAdapterEmission();
            $html = $this->render(['end_date' => $value]);
            $this->assertStringContainsString('data-end-date="2026-11-06T23:59:59+03:30"', $html, $value);
            $this->assertStringContainsString('پایان: <time datetime="2026-11-06T23:59:59+03:30">۱۵ آبان ۱۴۰۵</time>', $html, $value);
        }
    }

    public function test_end_time_sets_the_tehran_clock_for_jalali_and_gregorian_dates(): void
    {
        $html = $this->render(['end_date' => '۱۴۰۵/۰۸/۱۵', 'end_time' => '۲۰:۳۰']);
        $this->assertStringContainsString('data-end-date="2026-11-06T20:30:00+03:30"', $html);
        $this->assertStringContainsString('۱۵ آبان ۱۴۰۵، ساعت ۲۰:۳۰', $html);

        BlockRenderer::resetCountdownAdapterEmission();
        $html = $this->render(['end_date' => '2026-11-06', 'end_time' => '20:30']);
        $this->assertStringContainsString('data-end-date="2026-11-06T20:30:00+03:30"', $html);
        $this->assertStringContainsString('۱۵ آبان ۱۴۰۵، ساعت ۲۰:۳۰', $html);
    }

    /** Stored Gregorian countdowns keep the exact instant they had before Phase 3. */
    public function test_a_gregorian_value_without_end_time_is_emitted_as_stored(): void
    {
        foreach (['2026-12-31', '2026-12-31 23:59', '2027-01-01T00:00:00+00:00'] as $value) {
            BlockRenderer::resetCountdownAdapterEmission();
            $html = $this->render(['end_date' => $value]);
            $this->assertStringContainsString('data-end-date="' . $value . '"', $html);
            $this->assertStringNotContainsString('tb-countdown__end', $html, 'no end line for an instant this renderer did not compute');
        }
    }

    public function test_an_impossible_date_shows_the_format_hint_and_no_script(): void
    {
        foreach (['1405/13/01', '1404/12/30', '2026-02-30'] as $value) {
            $html = $this->render(['end_date' => $value]);
            $this->assertStringContainsString('تاریخ پایان معتبر نیست', $html, $value);
            $this->assertStringNotContainsString('<script', $html, $value);
            $this->assertStringNotContainsString('data-end-date', $html, $value);
        }
    }

    public function test_expired_text_style_and_end_line_toggle(): void
    {
        $html = $this->render(['end_date' => '1405/08/15', 'expired_text' => 'حراج تمام شد', 'style' => 'banner', 'show_end_date' => false]);
        $this->assertStringContainsString('class="tb-countdown tb-countdown--banner"', $html);
        $this->assertStringContainsString('<p class="tb-countdown__expired" hidden>حراج تمام شد</p>', $html);
        $this->assertStringNotContainsString('tb-countdown__end', $html);
        $this->assertStringContainsString('role="timer"', $html);
    }

    public function test_the_adapter_is_emitted_once_per_document(): void
    {
        $renderer = new BlockRenderer();
        $props    = ['end_date' => date('c', strtotime('+3 days'))];

        $first  = $renderer->renderBlock(['type' => 'countdown', 'id' => 'b1', 'props' => $props]);
        $second = $renderer->renderBlock(['type' => 'countdown', 'id' => 'b2', 'props' => $props]);

        $this->assertStringContainsString('ThemeCountdown', $first);
        $this->assertStringNotContainsString('ThemeCountdown', $second, 'the adapter must not be re-inlined per block');
        $this->assertSame(0, preg_match_all('#<script\b#', $second), 'the second countdown must emit no empty <script> element');
    }
}