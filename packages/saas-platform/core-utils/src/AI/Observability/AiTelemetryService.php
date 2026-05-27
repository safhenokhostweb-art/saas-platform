<?php

namespace SaaSPlatform\CoreUtils\AI\Observability;

use Illuminate\Support\Facades\Cache;

class AiTelemetryService
{
    private const STATS_KEY  = 'ai:telemetry:stats:';
    private const EVENTS_KEY = 'ai:telemetry:events:';
    private const MAX_EVENTS = 1000;

    public function record(array $event): void
    {
        $tenantId = $event['tenant_id'] ?? '__system__';
        $date     = date('Y-m-d');

        // Append to event log
        $key    = self::EVENTS_KEY . $tenantId . ':' . $date;
        $events = Cache::get($key, []);
        $events[] = array_merge($event, ['ts' => now()->toISOString()]);
        if (count($events) > self::MAX_EVENTS) {
            $events = array_slice($events, -self::MAX_EVENTS);
        }
        Cache::put($key, $events, 86400 * 7);

        // Update aggregated stats
        $this->updateStats($tenantId, $event);
    }

    public function recordQuotaBlock(string $tenantId, string $domain): void
    {
        $this->record([
            'tenant_id' => $tenantId,
            'type'      => 'quota_block',
            'domain'    => $domain,
            'success'   => false,
        ]);
    }

    public function recordRateLimit(string $tenantId, string $domain): void
    {
        $this->record([
            'tenant_id' => $tenantId,
            'type'      => 'rate_limit',
            'domain'    => $domain,
            'success'   => false,
        ]);
    }

    public function getStats(string $tenantId = '__system__', int $days = 7): array
    {
        $aggregate = ['total' => 0, 'success' => 0, 'failures' => 0, 'tokens' => 0, 'avg_latency' => 0];
        $latencies = [];

        for ($i = 0; $i < $days; $i++) {
            $date  = date('Y-m-d', strtotime("-{$i} days"));
            $stats = Cache::get(self::STATS_KEY . $tenantId . ':' . $date, []);

            $aggregate['total']    += $stats['total'] ?? 0;
            $aggregate['success']  += $stats['success'] ?? 0;
            $aggregate['failures'] += $stats['failures'] ?? 0;
            $aggregate['tokens']   += $stats['tokens'] ?? 0;
            if (! empty($stats['latencies'])) {
                $latencies = array_merge($latencies, $stats['latencies']);
            }
        }

        if (count($latencies) > 0) {
            $aggregate['avg_latency'] = round(array_sum($latencies) / count($latencies), 1);
        }

        $aggregate['success_rate'] = $aggregate['total'] > 0
            ? round(($aggregate['success'] / $aggregate['total']) * 100, 1)
            : 0;

        return $aggregate;
    }

    public function getRecentEvents(string $tenantId, int $limit = 50): array
    {
        $date = date('Y-m-d');
        $events = Cache::get(self::EVENTS_KEY . $tenantId . ':' . $date, []);
        return array_slice(array_reverse($events), 0, $limit);
    }

    public function getDomainBreakdown(string $tenantId, int $days = 7): array
    {
        $breakdown = [];
        for ($i = 0; $i < $days; $i++) {
            $date   = date('Y-m-d', strtotime("-{$i} days"));
            $stats  = Cache::get(self::STATS_KEY . $tenantId . ':' . $date, []);
            $domains = $stats['domains'] ?? [];
            foreach ($domains as $domain => $count) {
                $breakdown[$domain] = ($breakdown[$domain] ?? 0) + $count;
            }
        }
        arsort($breakdown);
        return $breakdown;
    }

    private function updateStats(string $tenantId, array $event): void
    {
        $date = date('Y-m-d');
        $key  = self::STATS_KEY . $tenantId . ':' . $date;
        $stats = Cache::get($key, ['total' => 0, 'success' => 0, 'failures' => 0, 'tokens' => 0, 'latencies' => [], 'domains' => []]);

        $stats['total']++;
        if ($event['success'] ?? false) {
            $stats['success']++;
        } else {
            $stats['failures']++;
        }

        $stats['tokens'] += $event['tokens'] ?? 0;

        if (! empty($event['latency_ms'])) {
            $stats['latencies'][] = $event['latency_ms'];
            if (count($stats['latencies']) > 500) {
                $stats['latencies'] = array_slice($stats['latencies'], -500);
            }
        }

        $domain = $event['domain'] ?? 'general';
        $stats['domains'][$domain] = ($stats['domains'][$domain] ?? 0) + 1;

        Cache::put($key, $stats, 86400 * 8);
    }
}
