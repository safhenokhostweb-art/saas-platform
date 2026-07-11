<?php

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\SiteModuleUpgradeController;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 13-M1 — isolates the exact Cache::lock() mechanism
 * SiteModuleUpgradeController::store() relies on, using the same key
 * format (SiteModuleUpgradeController::lockKey()) production code uses.
 */
class SiteModuleUpgradeControllerLockTest extends TestCase
{
    public function test_lock_cannot_be_acquired_twice_for_the_same_site(): void
    {
        $key = SiteModuleUpgradeController::lockKey(42);

        $first = Cache::lock($key, 10);
        $this->assertTrue($first->get());

        $second = Cache::lock($key, 10);
        $this->assertFalse($second->get());

        $first->release();
    }

    public function test_lock_for_different_sites_do_not_contend(): void
    {
        $lockA = Cache::lock(SiteModuleUpgradeController::lockKey(1), 10);
        $lockB = Cache::lock(SiteModuleUpgradeController::lockKey(2), 10);

        $this->assertTrue($lockA->get());
        $this->assertTrue($lockB->get());

        $lockA->release();
        $lockB->release();
    }

    public function test_lock_is_released_when_callback_throws(): void
    {
        $key = SiteModuleUpgradeController::lockKey(7);

        try {
            Cache::lock($key, 10)->get(function () {
                throw new \RuntimeException('simulated failure inside critical section');
            });
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated failure inside critical section', $e->getMessage());
        }

        // If the lock survived the exception, this second acquisition fails.
        $retry = Cache::lock($key, 10);
        $this->assertTrue($retry->get());
        $retry->release();
    }

    public function test_lock_key_format_is_stable_and_scoped_per_site(): void
    {
        $this->assertSame('site-upgrade-lock:5', SiteModuleUpgradeController::lockKey(5));
        $this->assertNotSame(
            SiteModuleUpgradeController::lockKey(5),
            SiteModuleUpgradeController::lockKey(6)
        );
    }
}
