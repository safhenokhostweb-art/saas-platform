<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Deployment;

use Illuminate\Support\Facades\DB;

class ReleaseFailureIntelligence
{
    public function analyze(string $deploymentUuid): array
    {
        $logs         = $this->getFailedLogs($deploymentUuid);
        $pattern      = $this->detectPattern($logs);
        $suggestions  = $this->buildSuggestions($pattern, $logs);

        return [
            'deployment_uuid' => $deploymentUuid,
            'failure_pattern' => $pattern,
            'failed_phases'   => array_column($logs, 'phase'),
            'suggestions'     => $suggestions,
            'analyzed_at'     => now()->toIso8601String(),
        ];
    }

    public function topFailurePatterns(int $days = 30): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('deployment_logs')) {
                return [];
            }

            return DB::table('deployment_logs')
                ->where('status', 'failed')
                ->where('logged_at', '>=', now()->subDays($days)->toDateTimeString())
                ->selectRaw('phase, count(*) as failure_count')
                ->groupBy('phase')
                ->orderByDesc('failure_count')
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function getFailedLogs(string $uuid): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('deployment_logs')) {
                return [];
            }

            return DB::table('deployment_logs')
                ->where('deployment_uuid', $uuid)
                ->where('status', 'failed')
                ->orderBy('logged_at')
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function detectPattern(array $failedLogs): string
    {
        if (empty($failedLogs)) {
            return 'no_failure';
        }

        $phases = array_column($failedLogs, 'phase');

        if (in_array('migrations', $phases))       return 'migration_failure';
        if (in_array('health-check', $phases))     return 'health_check_failure';
        if (in_array('cache-rebuild', $phases))    return 'cache_failure';
        if (in_array('queue-restart', $phases))    return 'queue_failure';
        if (in_array('validating', $phases))       return 'pre_validation_failure';

        return 'unknown_phase_failure';
    }

    private function buildSuggestions(string $pattern, array $logs): array
    {
        return match ($pattern) {
            'migration_failure' => [
                'Run: php artisan migration:validate-safety',
                'Run: php artisan migration:detect-drift',
                'Check migration files for destructive operations',
                'Consider rollback if data integrity is at risk',
            ],
            'health_check_failure' => [
                'Run: php artisan platform:health ready',
                'Check database connectivity',
                'Check cache server availability',
                'Verify storage permissions',
            ],
            'cache_failure' => [
                'Run: php artisan cache:clear',
                'Check Redis/cache server connectivity',
                'Verify cache driver configuration',
            ],
            'queue_failure' => [
                'Run: php artisan queue:validate-runtime',
                'Restart queue workers: php artisan queue:restart',
                'Check for stale workers with outdated release stamp',
            ],
            default => [
                'Run: php artisan platform:health',
                'Review deployment logs for the failed phase',
                'Consider: php artisan deployment:rollback --dry-run',
            ],
        };
    }
}
