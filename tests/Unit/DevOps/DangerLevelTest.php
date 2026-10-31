<?php

namespace Tests\Unit\DevOps;

use App\Services\DevOps\DangerLevel;
use Tests\TestCase;

/**
 * Danger zones against the soft quota, read from the shared
 * resource_governance.alert_* thresholds (defaults 80 / 90).
 */
class DangerLevelTest extends TestCase
{
    public function test_default_thresholds_are_80_and_90(): void
    {
        $this->assertSame(80, config('resource_governance.alert_warning_percent'));
        $this->assertSame(90, config('resource_governance.alert_critical_percent'));
    }

    /**
     * @dataProvider percentCases
     */
    public function test_percent_maps_to_zone(float $percent, DangerLevel $expected): void
    {
        $this->assertSame($expected, DangerLevel::fromPercent($percent));
    }

    public static function percentCases(): array
    {
        return [
            'empty'            => [0.0, DangerLevel::Normal],
            'just below 80'    => [79.9, DangerLevel::Normal],
            'exactly 80'       => [80.0, DangerLevel::Warning],
            'mid warning'      => [85.0, DangerLevel::Warning],
            'just below 90'    => [89.9, DangerLevel::Warning],
            'exactly 90'       => [90.0, DangerLevel::Critical],
            'over quota'       => [140.0, DangerLevel::Critical],
        ];
    }

    public function test_thresholds_follow_shared_config(): void
    {
        config(['resource_governance.alert_warning_percent' => 70, 'resource_governance.alert_critical_percent' => 95]);

        $this->assertSame(DangerLevel::Warning, DangerLevel::fromPercent(70.0));
        $this->assertSame(DangerLevel::Warning, DangerLevel::fromPercent(94.9));
        $this->assertSame(DangerLevel::Critical, DangerLevel::fromPercent(95.0));
    }

    public function test_from_usage_uses_soft_limit(): void
    {
        $gb = 1073741824;

        $this->assertSame(DangerLevel::Normal, DangerLevel::fromUsage(3 * $gb, 5 * $gb));       // 60%
        $this->assertSame(DangerLevel::Warning, DangerLevel::fromUsage(4 * $gb, 5 * $gb));      // 80%
        $this->assertSame(DangerLevel::Critical, DangerLevel::fromUsage((int) (4.6 * $gb), 5 * $gb)); // 92%
    }

    public function test_missing_measurement_or_quota_is_unknown(): void
    {
        $this->assertSame(DangerLevel::Unknown, DangerLevel::fromUsage(null, 5368709120));
        $this->assertSame(DangerLevel::Unknown, DangerLevel::fromUsage(100, 0));
        $this->assertSame(DangerLevel::Unknown, DangerLevel::fromPercent(null));
    }

    public function test_severity_orders_escalation(): void
    {
        $this->assertGreaterThan(DangerLevel::Warning->severity(), DangerLevel::Critical->severity());
        $this->assertGreaterThan(DangerLevel::Normal->severity(), DangerLevel::Warning->severity());
        $this->assertGreaterThan(DangerLevel::Unknown->severity(), DangerLevel::Normal->severity());
    }

    public function test_labels_are_persian(): void
    {
        $this->assertSame('عادی', DangerLevel::Normal->label());
        $this->assertSame('هشدار', DangerLevel::Warning->label());
        $this->assertSame('بحرانی', DangerLevel::Critical->label());
    }
}
