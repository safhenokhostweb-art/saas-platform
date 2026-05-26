<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RecoveryExecutionLog
{
    public function __construct(private TelemetryManager $telemetry) {}

    public function record(
        string $recoveryId,
        string $strategy,
        string $status,
        string $failureType,
        array  $context = []
    ): void {
        $this->telemetry->emit('governance', $this->severityForStatus($status), 'governance.recovery.' . $status, [
            'recovery_id'  => $recoveryId,
            'strategy'     => $strategy,
            'failure_type' => $failureType,
            'context'      => $context,
        ]);

        try {
            if (DB::getSchemaBuilder()->hasTable('recovery_logs')) {
                DB::table('recovery_logs')->insert([
                    'recovery_id'  => $recoveryId,
                    'strategy'     => $strategy,
                    'status'       => $status,
                    'failure_type' => $failureType,
                    'context'      => json_encode($context),
                    'executed_at'  => now(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        } catch (\Throwable) {
            Log::warning('recovery_execution_log.db_write_failed', [
                'recovery_id' => $recoveryId,
                'strategy'    => $strategy,
            ]);
        }
    }

    public function getLogs(int $limit = 50): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('recovery_logs')) {
                return [];
            }

            return DB::table('recovery_logs')
                ->orderByDesc('executed_at')
                ->limit($limit)
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    public function successRate(int $hours = 24): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('recovery_logs')) {
                return ['available' => false];
            }

            $since = now()->subHours($hours)->toDateTimeString();

            $total   = DB::table('recovery_logs')->where('executed_at', '>=', $since)->count();
            $success = DB::table('recovery_logs')->where('executed_at', '>=', $since)->where('status', 'success')->count();

            return [
                'available'    => true,
                'total'        => $total,
                'success'      => $success,
                'success_rate' => $total > 0 ? round(($success / $total) * 100, 1) : 100,
            ];
        } catch (\Throwable) {
            return ['available' => false];
        }
    }

    private function severityForStatus(string $status): string
    {
        return match ($status) {
            'success' => TelemetryManager::SEVERITY_INFO,
            'skipped' => TelemetryManager::SEVERITY_DEBUG,
            'failed'  => TelemetryManager::SEVERITY_ERROR,
            default   => TelemetryManager::SEVERITY_WARNING,
        };
    }
}
