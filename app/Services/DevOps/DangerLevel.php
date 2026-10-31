<?php

namespace App\Services\DevOps;

/**
 * Disk danger zone of a site against its soft quota. Thresholds come from the
 * shared resource_governance.alert_* settings so the DevOps dashboard and the
 * LIFECYCLE-2 storage alerts never disagree about what "critical" means.
 */
enum DangerLevel: string
{
    case Normal   = 'normal';
    case Warning  = 'warning';
    case Critical = 'critical';
    case Unknown  = 'unknown';

    public static function fromPercent(?float $percent, ?float $warningPct = null, ?float $criticalPct = null): self
    {
        if ($percent === null || is_nan($percent)) {
            return self::Unknown;
        }

        $warningPct  ??= (float) config('resource_governance.alert_warning_percent', 80);
        $criticalPct ??= (float) config('resource_governance.alert_critical_percent', 90);

        return match (true) {
            $percent >= $criticalPct => self::Critical,
            $percent >= $warningPct  => self::Warning,
            default                  => self::Normal,
        };
    }

    public static function fromUsage(?int $usedBytes, int $softLimitBytes): self
    {
        if ($usedBytes === null || $softLimitBytes <= 0) {
            return self::Unknown;
        }

        return self::fromPercent($usedBytes / $softLimitBytes * 100);
    }

    public function label(): string
    {
        return match ($this) {
            self::Normal   => 'عادی',
            self::Warning  => 'هشدار',
            self::Critical => 'بحرانی',
            self::Unknown  => 'نامشخص',
        };
    }

    /** Higher = worse; used to detect escalation between audits. */
    public function severity(): int
    {
        return match ($this) {
            self::Unknown  => 0,
            self::Normal   => 1,
            self::Warning  => 2,
            self::Critical => 3,
        };
    }
}
