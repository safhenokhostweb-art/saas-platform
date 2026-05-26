<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class QueuePressureController
{
    private const PRESSURE_KEY    = 'governance.queue.pressure';
    private const HIGH_THRESHOLD  = 500;
    private const CRIT_THRESHOLD  = 1000;

    public function __construct(private TelemetryManager $telemetry) {}

    public function assess(int $pendingCount, int $failedCount): string
    {
        if ($pendingCount >= self::CRIT_THRESHOLD || $failedCount >= 50) {
            $level = 'critical';
        } elseif ($pendingCount >= self::HIGH_THRESHOLD || $failedCount >= 10) {
            $level = 'high';
        } else {
            $level = 'normal';
        }

        Cache::put(self::PRESSURE_KEY, [
            'level'         => $level,
            'pending'       => $pendingCount,
            'failed'        => $failedCount,
            'assessed_at'   => now()->toIso8601String(),
        ], 300);

        if ($level !== 'normal') {
            $this->telemetry->warning('governance', 'governance.queue.pressure', [
                'level'   => $level,
                'pending' => $pendingCount,
                'failed'  => $failedCount,
            ]);
        }

        return $level;
    }

    public function currentPressure(): array
    {
        return Cache::get(self::PRESSURE_KEY, ['level' => 'normal', 'pending' => 0, 'failed' => 0]);
    }

    public function applyBackpressure(float $factor): void
    {
        Cache::put('governance.queue.backpressure', [
            'active'      => true,
            'factor'      => $factor,
            'applied_at'  => now()->toIso8601String(),
        ], 600);
    }

    public function releaseBackpressure(): void
    {
        Cache::forget('governance.queue.backpressure');
    }

    public function isUnderBackpressure(): bool
    {
        return (bool) (Cache::get('governance.queue.backpressure.active') ||
                       (Cache::get('governance.queue.backpressure')['active'] ?? false));
    }
}
