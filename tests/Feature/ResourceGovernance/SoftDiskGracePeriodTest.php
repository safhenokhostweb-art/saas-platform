<?php

namespace Tests\Feature\ResourceGovernance;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\NotificationCenterLog;
use App\Models\Site;
use App\Models\SiteResourceLimit;
use App\Models\SiteResourceUsage;
use App\Services\DevOps\TenantResourceGovernor;
use App\Services\Infrastructure\SlackAlertService;
use App\Services\Notification\NotificationCenterService;
use App\Services\ResourceGovernance\SiteResourceAlertService;
use App\Services\ResourceGovernance\SiteResourceCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1 (2026-11-19): alerts use the governor's REAL disk measurement only, and
 * the 7-day soft-disk grace period is a state machine with its own flag —
 * it used to be message text only and shared alerted_80 with the hard 80%
 * warning (never setting a flag, so it re-fired on every run).
 */
class SoftDiskGracePeriodTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 ** 3;

    /** @var list<array<string, mixed>> */
    private array $notifications = [];

    protected function setUp(): void
    {
        parent::setUp();
        $sink = &$this->notifications;
        $nc = $this->createMock(NotificationCenterService::class);
        $nc->method('notify')->willReturnCallback(function (array $n) use (&$sink) { $sink[] = $n; return new NotificationCenterLog(); });
        $this->app->instance(NotificationCenterService::class, $nc);
        $this->app->instance(SlackAlertService::class, $this->createMock(SlackAlertService::class));
    }

    /** 10 GB hard quota, 7 GB soft quota. */
    private function site(): Site
    {
        $customer = Customer::create(['name' => 'Grace', 'mobile' => '0912' . random_int(1000000, 9999999)]);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'Grace Site', 'domain' => 'grace' . random_int(1, 99999) . '.test', 'is_active' => true]);
        SiteResourceLimit::create([
            'site_id' => $site->id, 'disk_hard_limit_bytes' => 10 * self::GB, 'storage_limit_bytes' => 10 * self::GB,
            'disk_soft_limit_bytes' => 7 * self::GB, 'memory_limit_bytes' => 1024 ** 3, 'cpu_cores_limit' => 1,
        ]);

        return $site;
    }

    private function measure(Site $site, ?float $usedGb, ?\DateTimeInterface $at = null): SiteResourceUsage
    {
        $usage = SiteResourceUsage::firstOrNew(['site_id' => $site->id]);
        $usage->fill([
            'storage_bytes'    => (int) (($usedGb ?? 0) * self::GB),
            'disk_usage_bytes' => $usedGb === null ? null : (int) ($usedGb * self::GB),
            'disk_audited_at'  => $usedGb === null ? null : ($at ?? now()),
        ])->save();

        return $usage->refresh();
    }

    private function check(Site $site): SiteResourceUsage
    {
        $usage = SiteResourceUsage::where('site_id', $site->id)->firstOrFail();
        app(SiteResourceAlertService::class)->checkAndAlertStorage($site, $usage, SiteResourceLimit::where('site_id', $site->id)->firstOrFail());

        return $usage->refresh();
    }

    private function logged(string $action): int
    {
        return ActivityLog::query()->where('action', $action)->count();
    }

    public function test_no_alert_without_a_real_measurement(): void
    {
        $site = $this->site();
        $this->measure($site, null);

        $usage = $this->check($site);

        $this->assertSame([], $this->notifications);
        $this->assertNull($usage->soft_exceeded_at);
    }

    public function test_a_stale_measurement_is_not_alerted_on(): void
    {
        $site = $this->site();
        $this->measure($site, 9.9, now()->subDays(3));

        $this->check($site);

        $this->assertSame([], $this->notifications);
    }

    public function test_crossing_the_soft_quota_starts_the_grace_period_once(): void
    {
        $site = $this->site();
        $this->measure($site, 7.5); // 75% of hard: over soft, under the 80% hard warning

        $usage = $this->check($site);
        $this->check($site);
        $this->check($site);

        $this->assertNotNull($usage->soft_exceeded_at);
        $this->assertTrue($usage->alerted_soft);
        $this->assertFalse($usage->alerted_80, 'The soft flag is independent of the hard 80% flag.');
        $this->assertSame(1, collect($this->notifications)->where('type', 'soft_disk_warning')->count(), 'No re-fire on every run.');
        $this->assertSame(1, $this->logged('resource.soft_quota_exceeded'));
    }

    public function test_the_grace_period_escalates_exactly_once_after_seven_days(): void
    {
        $site = $this->site();
        $this->measure($site, 7.5);
        $this->check($site);

        $this->travel(6)->days();
        $this->measure($site, 7.5);
        $this->assertNull($this->check($site)->soft_grace_escalated_at, 'Not before the grace period ends.');

        $this->travel(2)->days();
        $this->measure($site, 7.6);
        $usage = $this->check($site);
        $this->check($site);

        $this->assertNotNull($usage->soft_grace_escalated_at);
        $this->assertSame(1, collect($this->notifications)->where('type', 'soft_disk_grace_expired')->count());
        $this->assertSame('critical', collect($this->notifications)->firstWhere('type', 'soft_disk_grace_expired')['priority']);
        $this->assertSame(1, $this->logged('resource.soft_quota_grace_expired'));
    }

    public function test_dropping_back_under_the_soft_quota_clears_the_state(): void
    {
        $site = $this->site();
        $this->measure($site, 7.5);
        $this->check($site);

        $this->measure($site, 5.0);
        $usage = $this->check($site);

        $this->assertNull($usage->soft_exceeded_at);
        $this->assertFalse($usage->alerted_soft);
        $this->assertSame(1, $this->logged('resource.soft_quota_resolved'));
    }

    public function test_hard_warning_and_soft_grace_both_fire_when_crossed_together(): void
    {
        $site = $this->site();
        $this->measure($site, 8.5); // 85% of hard, over soft

        $usage = $this->check($site);

        $this->assertTrue($usage->alerted_80);
        $this->assertNotNull($usage->soft_exceeded_at, 'The soft alert is no longer suppressed by alerted_80.');
        $this->assertSame(1, $this->logged('resource.storage_warning'));
        $this->assertSame(1, $this->logged('resource.soft_quota_exceeded'));
    }

    public function test_calculator_uses_the_fresh_measurement_and_never_estimates(): void
    {
        $site = $this->site();
        $this->measure($site, 3.25);

        $this->assertSame((int) (3.25 * self::GB), app(SiteResourceCalculatorService::class)->calculate($site)['storage_bytes']);
    }

    public function test_calculator_reports_unmeasured_instead_of_inventing_a_figure(): void
    {
        $site = $this->site();
        $usage = $this->measure($site, 2.0, now()->subDays(5)); // stale
        $governor = $this->createMock(TenantResourceGovernor::class);
        $governor->method('audit')->willReturn(['ok' => false, 'disk_usage_bytes' => null, 'error' => 'unreachable']);
        $this->app->instance(TenantResourceGovernor::class, $governor);

        $metrics = app(SiteResourceCalculatorService::class)->calculate($site);

        $this->assertNull($metrics['storage_bytes']);
        $this->assertFalse($metrics['storage_measured']);
        $this->assertSame((int) (2.0 * self::GB), (int) $usage->fresh()->storage_bytes, 'The last real figure is kept, not overwritten.');
    }

    public function test_events_are_still_logged_on_the_platform_when_the_customer_site_is_unreachable(): void
    {
        $site = $this->site(); // no db_name: customer-site write is skipped

        $this->assertFalse(app(SiteResourceCalculatorService::class)->recordCustomerSiteEvent($site, 'resource.test', 't', 'b'));

        $this->measure($site, 7.5);
        $this->check($site);
        $this->assertSame(1, $this->logged('resource.soft_quota_exceeded'));
    }
}
