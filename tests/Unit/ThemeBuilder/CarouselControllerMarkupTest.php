<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

/**
 * WU-B (2026-10-31): the server half of the carousel upgrade. The client
 * half (autoplay gates, fade swipe, dot classes) is
 * tests/js/carousel-motion.test.mjs.
 *
 *  - B4: a hero slider's first image is fetched eagerly at high priority;
 *  - B5: carousel/slider styling lives in the shared sheet — the only
 *    inline styles left are the per-instance item counts and aspect ratio;
 *    the first slide and dot carry their active state from the server;
 *  - B6: the accessible name is the block's title, never its internal id,
 *    and slide numbers use Persian digits;
 *  - B1: the sheet switches the slide animations off for reduced motion.
 */
class CarouselControllerMarkupTest extends TestCase
{
    private function xpathFor(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    private function render(string $type, array $props, string $id = 'wub1'): string
    {
        return (new BlockRenderer())->renderBlock(['type' => $type, 'id' => $id, 'props' => $props]);
    }

    private function slider(array $extra = []): string
    {
        return $this->render('slider', $extra + [
            'slides' => [
                ['image' => '/uploads/one.jpg', 'title' => 'یک', 'subtitle' => 'زیر'],
                ['image' => '/uploads/two.jpg', 'title' => 'دو'],
                ['image' => '/uploads/three.jpg', 'title' => 'سه'],
            ],
        ]);
    }

    private function sheet(): string
    {
        return (string) file_get_contents(base_path('packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css'));
    }

    public function test_only_the_first_slide_image_is_a_high_priority_eager_fetch(): void
    {
        $imgs = $this->xpathFor($this->slider())->query('//div[contains(@class,"tb-carousel-track")]//img');

        $this->assertSame(3, $imgs->length);
        $this->assertSame('eager', $imgs->item(0)->getAttribute('loading'));
        $this->assertSame('high', $imgs->item(0)->getAttribute('fetchpriority'));
        foreach ([1, 2] as $i) {
            $this->assertSame('lazy', $imgs->item($i)->getAttribute('loading'));
            $this->assertFalse($imgs->item($i)->hasAttribute('fetchpriority'));
        }
    }

    public function test_the_accessible_name_never_leaks_the_block_id(): void
    {
        $html = $this->slider();
        $region = $this->xpathFor($html)->query('//*[@aria-roledescription="carousel"]')->item(0);

        $this->assertSame('اسلایدر', $region->getAttribute('aria-label'));
        $this->assertStringNotContainsString('wub1', $region->getAttribute('aria-label'));
    }

    public function test_a_titled_carousel_is_named_by_its_title_escaped_once(): void
    {
        $html = $this->render('product_slider', ['title' => 'پیشنهاد ویژه & تخفیف']);
        $region = $this->xpathFor($html)->query('//*[@aria-roledescription="carousel"]')->item(0);

        $this->assertSame('پیشنهاد ویژه & تخفیف', $region->getAttribute('aria-label'));
        $this->assertStringContainsString('aria-label="پیشنهاد ویژه &amp; تخفیف"', $html, 'escaped exactly once');
    }

    public function test_slide_and_dot_labels_use_persian_digits(): void
    {
        $xpath = $this->xpathFor($this->slider());

        $this->assertSame('اسلاید ۲ از ۳', $xpath->query('//*[@aria-roledescription="slide"]')->item(1)->getAttribute('aria-label'));
        $this->assertSame('رفتن به اسلاید ۳', $xpath->query('//button[contains(@class,"tb-carousel-dot")]')->item(2)->getAttribute('aria-label'));
    }

    public function test_the_first_dot_is_active_from_the_server(): void
    {
        $dots = $this->xpathFor($this->slider())->query('//button[contains(@class,"tb-carousel-dot")]');

        $this->assertSame('tb-carousel-dot is-active', $dots->item(0)->getAttribute('class'));
        $this->assertSame('true', $dots->item(0)->getAttribute('aria-current'));
        foreach ([1, 2] as $i) {
            $this->assertSame('tb-carousel-dot', $dots->item($i)->getAttribute('class'));
            $this->assertSame('false', $dots->item($i)->getAttribute('aria-current'));
            $this->assertFalse($dots->item($i)->hasAttribute('style'));
        }
    }

    public function test_slider_and_carousel_chrome_carry_no_inline_presentation(): void
    {
        $xpath = $this->xpathFor($this->slider(['transition' => 'fade', 'overlay' => false]));

        $root = $xpath->query('//*[@aria-roledescription="carousel"]')->item(0);
        $this->assertSame('--tb-items-desktop:1;--tb-items-tablet:1;--tb-items-mobile:1;', $root->getAttribute('style'));
        $this->assertStringContainsString('tb-carousel--fade', $root->getAttribute('class'));

        foreach (['tb-carousel-viewport', 'tb-carousel-track', 'tb-carousel-slide', 'tb-carousel-dots', 'tb-carousel-dot', 'tb-slide-overlay', 'tb-slide-caption', 'tb-slide-title', 'tb-slide-subtitle'] as $class) {
            $nodes = $xpath->query('//*[contains(concat(" ", @class, " "), " ' . $class . ' ")]');
            $this->assertGreaterThan(0, $nodes->length, $class);
            foreach ($nodes as $node) {
                $this->assertFalse($node->hasAttribute('style'), "{$class} must not carry an inline style");
            }
        }
        foreach ($xpath->query('//*[contains(concat(" ", @class, " "), " tb-slide ")]') as $slide) {
            $this->assertMatchesRegularExpression('#^aspect-ratio:\d+/\d+;$#', $slide->getAttribute('style'));
        }
        $this->assertSame(3, $xpath->query('//div[@class="tb-slide-overlay tb-slide-overlay--off"]')->length, 'overlay element kept, gradient off');
    }

    public function test_the_shared_sheet_owns_the_slider_styles_behind_tokens(): void
    {
        $sheet = $this->sheet();

        foreach ([
            '.tb-carousel-dot { width: 0.55rem; height: 0.55rem; padding: 0; border-radius: 999px; border: 0; background: var(--tb-border-strong, #d1d5db);',
            '.tb-carousel-dot.is-active { background: var(--tb-primary, #4f46e5); }',
            '.tb-slide { position: relative; display: flex; align-items: center; justify-content: center; background: var(--tb-media-bg, #111827);',
            '.tb-carousel--fade .tb-carousel-slide:first-child { opacity: 1; }',
            '.tb-slide .tb-slide-title { margin: 0 0 0.5rem; font-size: clamp(1.25rem, 1rem + 2vw, 2.25rem);',
        ] as $rule) {
            $this->assertStringContainsString($rule, $sheet);
        }
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: reduce\) \{ \.tb-carousel-track, \.tb-carousel--fade \.tb-carousel-slide, \.tb-carousel \.tb-carousel-dot \{ transition: none; \} \}/', $sheet);
    }
}
