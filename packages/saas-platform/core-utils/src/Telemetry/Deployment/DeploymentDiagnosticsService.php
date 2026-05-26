<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Deployment;

use Illuminate\Support\Facades\DB;

class DeploymentDiagnosticsService
{
    public function diagnose(string $deploymentUuid): array
    {
        $logs  = $this->getLogs($deploymentUuid);
        $lock  = $this->getLock($deploymentUuid);
        $phases = $this->phaseBreakdown($logs);

        return [
            'deployment_uuid' => $deploymentUuid,
            'lock_record'     => $lock,
            'phases'          => $phases,
            'failed_phases'   => array_filter($phases, fn($p) => $p['status'] === 'failed'),
            'total_phases'    => count($phases),
            'has_failure'     => collect($phases)->contains('status', 'failed'),
            'diagnosed_at'    => now()->toIso8601String(),
        ];
    }

    public function currentDeploymentStatus(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('release_locks')) {
                return ['active' => false];
            }

            $lock = DB::table('release_locks')
                ->where('status', 'active')
                ->orderByDesc('acquired_at')
                ->first();

            if (!$lock) {
                return ['active' => false];
            }

            return [
                'active'          => true,
                'deployment_uuid' => $lock->deployment_uuid,
                'phase'           => $lock->phase,
                'acquired_at'     => $lock->acquired_at,
                'expires_at'      => $lock->expires_at,
            ];
        } catch (\Throwable) {
            return ['active' => false];
        }
    }

    private function getLogs(string $uuid): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('deployment_logs')) {
                return [];
            }

            return DB::table('deployment_logs')
                ->where('deployment_uuid', $uuid)
                ->orderBy('logged_at')
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function getLock(string $uuid): ?array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('release_locks')) {
                return null;
            }

            $lock = DB::table('release_locks')->where('deployment_uuid', $uuid)->first();
            return $lock ? (array) $lock : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function phaseBreakdown(array $logs): array
    {
        return array_map(fn($log) => [
            'phase'    => $log['phase'],
            'status'   => $log['status'],
            'message'  => $log['message'],
            'duration' => $log['duration_ms'],
            'at'       => $log['logged_at'],
        ], $logs);
    }
}
