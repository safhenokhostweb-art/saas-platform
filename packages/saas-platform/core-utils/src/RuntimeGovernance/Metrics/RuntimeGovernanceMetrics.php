<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Metrics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RuntimeGovernanceMetrics
{
    private const METRICS_CACHE_TTL = 120;

    public function policyExecutions(int $hours = 24): array
    {
        $key = 'governance.metrics.policy_executions.' . $hours;
        return Cache::remember($key, self::METRICS_CACHE_TTL, function () use ($hours) {
            try {
                if (!DB::getSchemaBuilder()->hasTable('telemetry_events')) {
                    return ['available' => false];
                }

                $since = now()->subHours($hours);
                $total = DB::table('telemetry_events')
                    ->where('event', 'like', 'governance.policy.%')
                    ->where('created_at', '>=', $since)
                    ->count();

                return ['available' => true, 'total' => $total, 'hours' => $hours];
            } catch (\Throwable) {
                return ['available' => false];
            }
        });
    }

    public function recoveryActions(int $hours = 24): array
    {
        $key = 'governance.metrics.recovery_actions.' . $hours;
        return Cache::remember($key, self::METRICS_CACHE_TTL, function () use ($hours) {
            try {
                if (!DB::getSchemaBuilder()->hasTable('recovery_logs')) {
                    return ['available' => false];
                }

                $since = now()->subHours($hours);
                $rows  = DB::table('recovery_logs')
                    ->where('executed_at', '>=', $since)
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status')
                    ->toArray();

                return [
                    'available' => true,
                    'by_status' => $rows,
                    'total'     => array_sum($rows),
                    'success_rate' => $this->computeRate($rows),
                    'hours'     => $hours,
                ];
            } catch (\Throwable) {
                return ['available' => false];
            }
        });
    }

    public function incidentMetrics(int $hours = 24): array
    {
        $key = 'governance.metrics.incidents.' . $hours;
        return Cache::remember($key, self::METRICS_CACHE_TTL, function () use ($hours) {
            try {
                if (!DB::getSchemaBuilder()->hasTable('governance_incidents')) {
                    return ['available' => false];
                }

                $since = now()->subHours($hours);
                $bySeverity = DB::table('governance_incidents')
                    ->where('opened_at', '>=', $since)
                    ->selectRaw('severity, count(*) as total')
                    ->groupBy('severity')
                    ->pluck('total', 'severity')
                    ->toArray();

                $resolved = DB::table('governance_incidents')
                    ->where('opened_at', '>=', $since)
                    ->where('status', 'resolved')
                    ->count();

                return [
                    'available'   => true,
                    'by_severity' => $bySeverity,
                    'total'       => array_sum($bySeverity),
                    'resolved'    => $resolved,
                    'hours'       => $hours,
                ];
            } catch (\Throwable) {
                return ['available' => false];
            }
        });
    }

    public function stateTransitions(int $hours = 24): array
    {
        $history = Cache::get('governance.runtime.state_history', []);
        $since   = now()->subHours($hours)->timestamp;

        $filtered = array_filter($history, function ($entry) use ($since) {
            return isset($entry['timestamp']) && strtotime($entry['timestamp']) >= $since;
        });

        return [
            'transitions' => array_values($filtered),
            'count'       => count($filtered),
            'hours'       => $hours,
        ];
    }

    private function computeRate(array $statusCounts): ?float
    {
        $total = array_sum($statusCounts);
        if ($total === 0) {
            return null;
        }
        return round(($statusCounts['success'] ?? 0) / $total * 100, 1);
    }
}
