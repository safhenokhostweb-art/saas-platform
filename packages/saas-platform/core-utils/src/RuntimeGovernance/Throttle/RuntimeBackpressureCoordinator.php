<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\Telemetry\Queue\QueueTelemetryService;

class RuntimeBackpressureCoordinator
{
    public function __construct(
        private RuntimeThrottleManager  $throttleManager,
        private QueuePressureController $queueController,
        private RequestRateRegulator    $rateRegulator,
        private TelemetryManager        $telemetry
    ) {}

    public function assess(): array
    {
        $queueSnapshot = $this->assessQueuePressure();
        $memoryPressure = $this->assessMemoryPressure();

        $overallLevel = $this->computeOverallPressure($queueSnapshot['level'], $memoryPressure);

        if ($overallLevel === 'critical') {
            $this->throttleManager->activateGlobal(0.3, 'backpressure:critical');
            $this->queueController->applyBackpressure(0.3);
        } elseif ($overallLevel === 'high') {
            $this->throttleManager->activateGlobal(0.6, 'backpressure:high');
            $this->queueController->applyBackpressure(0.6);
        } else {
            $this->throttleManager->deactivateGlobal();
            $this->queueController->releaseBackpressure();
        }

        return [
            'overall_level'   => $overallLevel,
            'queue'           => $queueSnapshot,
            'memory_pressure' => $memoryPressure,
            'throttle'        => $this->throttleManager->snapshot(),
            'assessed_at'     => now()->toIso8601String(),
        ];
    }

    private function assessQueuePressure(): array
    {
        $pending = 0;
        $failed  = 0;

        try {
            $pending = (int) \Illuminate\Support\Facades\DB::table('jobs')->count();
            $failed  = (int) \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
        } catch (\Throwable) {}

        $level = $this->queueController->assess($pending, $failed);

        return ['level' => $level, 'pending' => $pending, 'failed' => $failed];
    }

    private function assessMemoryPressure(): bool
    {
        $memoryMb  = memory_get_usage(true) / 1024 / 1024;
        $limitStr  = ini_get('memory_limit');

        if ($limitStr === '-1') {
            return false;
        }

        $unit  = strtolower(substr($limitStr, -1));
        $limit = (float) $limitStr;
        $limit = match ($unit) {
            'g' => $limit * 1024,
            'k' => $limit / 1024,
            default => $limit,
        };

        return $limit > 0 && ($memoryMb / $limit) > 0.85;
    }

    private function computeOverallPressure(string $queueLevel, bool $memoryPressure): string
    {
        if ($queueLevel === 'critical' || ($queueLevel === 'high' && $memoryPressure)) {
            return 'critical';
        }

        if ($queueLevel === 'high' || $memoryPressure) {
            return 'high';
        }

        return 'normal';
    }
}
