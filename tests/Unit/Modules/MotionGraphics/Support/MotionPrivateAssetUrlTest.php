<?php

namespace Tests\Unit\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\Support\MotionPrivateAssetUrl;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ADR-081: the signed-URL scheme on its own — one site, one asset version,
 * one fixed window, signature in the path.
 */
final class MotionPrivateAssetUrlTest extends TestCase
{
    private const BASE = '/motion/private/demo-motion/v3/animation.svg';
    private const SIGNED = '#^/motion/private/demo-motion/v3/7/(\d+)/([a-f0-9]{64})/animation\.svg$#';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_url_is_stable_inside_one_window_and_rotates_after_it(): void
    {
        $urls = new MotionPrivateAssetUrl();
        $start = intdiv(1_800_000_000, MotionPrivateAssetUrl::WINDOW_SECONDS) * MotionPrivateAssetUrl::WINDOW_SECONDS;

        Carbon::setTestNow(Carbon::createFromTimestamp($start + 10));
        $first = $urls->sign(self::BASE, 7);
        Carbon::setTestNow(Carbon::createFromTimestamp($start + MotionPrivateAssetUrl::WINDOW_SECONDS - 10));
        $this->assertSame($first, $urls->sign(self::BASE, 7), 'one window, one URL: stable manifest checksum and browser cache');

        Carbon::setTestNow(Carbon::createFromTimestamp($start + MotionPrivateAssetUrl::WINDOW_SECONDS + 10));
        $this->assertNotSame($first, $urls->sign(self::BASE, 7));
        $this->assertMatchesRegularExpression(self::SIGNED, (string) $first);
    }

    public function test_verification_binds_site_asset_version_and_expiry(): void
    {
        $urls = new MotionPrivateAssetUrl();
        preg_match(self::SIGNED, (string) $urls->sign(self::BASE, 7), $m);
        [, $expires, $signature] = $m;

        $this->assertTrue($urls->verify('demo-motion', 'v3', 7, (int) $expires, $signature));
        $this->assertFalse($urls->verify('demo-motion', 'v3', 8, (int) $expires, $signature));
        $this->assertFalse($urls->verify('other-motion', 'v3', 7, (int) $expires, $signature));
        $this->assertFalse($urls->verify('demo-motion', 'v2', 7, (int) $expires, $signature));
        $this->assertFalse($urls->verify('demo-motion', 'v3', 7, (int) $expires + 1, $signature));

        Carbon::setTestNow(Carbon::createFromTimestamp((int) $expires + 1));
        $this->assertFalse($urls->verify('demo-motion', 'v3', 7, (int) $expires, $signature));
    }

    public function test_only_a_well_formed_base_reference_for_a_real_site_is_signed(): void
    {
        $urls = new MotionPrivateAssetUrl();

        $this->assertNull($urls->sign('/motion/demo-motion/v3/animation.svg', 7));
        $this->assertNull($urls->sign('/motion/private/demo-motion/v3/../x/animation.svg', 7));
        $this->assertNull($urls->sign('/motion/private/Demo/v3/animation.svg', 7));
        $this->assertNull($urls->sign(self::BASE, 0));
        $this->assertTrue(MotionPrivateAssetUrl::isPrivateReference(self::BASE));
        $this->assertFalse(MotionPrivateAssetUrl::isPrivateReference('/motion/hero-motion/v2/animation.svg'));
    }
}
