<?php

namespace SaaSPlatform\CoreUtils\Search;

use Illuminate\Support\Facades\Cache;

class SearchTelemetryService
{
    private const KEY_PREFIX = 'search:telemetry:';

    public function recordQuery(string $tenantId, string $query, int $resultCount, float $durationMs): void
    {
        $entry = [
            'query'        => $query,
            'results'      => $resultCount,
            'duration_ms'  => $durationMs,
            'timestamp'    => now()->toIso8601String(),
        ];

        $dailyKey = self::KEY_PREFIX . "tenant:{$tenantId}:daily:" . now()->format('Y-m-d');
        $entries  = Cache::get($dailyKey, []);
        $entries[] = $entry;
        Cache::put($dailyKey, array_slice($entries, -500), 86400 * 7);

        $this->incrementCounter($tenantId, 'total_queries');
        if ($resultCount === 0) {
            $this->incrementCounter($tenantId, 'zero_result_queries');
            $this->recordZeroResultQuery($tenantId, $query);
        }
    }

    public function getStats(string $tenantId): array
    {
        return [
            'total_queries'        => (int) Cache::get(self::KEY_PREFIX . "tenant:{$tenantId}:cnt:total_queries", 0),
            'zero_result_queries'  => (int) Cache::get(self::KEY_PREFIX . "tenant:{$tenantId}:cnt:zero_result_queries", 0),
            'top_zero_queries'     => $this->getTopZeroResultQueries($tenantId),
            'generated_at'         => now()->toIso8601String(),
        ];
    }

    private function incrementCounter(string $tenantId, string $metric): void
    {
        $key     = self::KEY_PREFIX . "tenant:{$tenantId}:cnt:{$metric}";
        $current = (int) Cache::get($key, 0);
        Cache::put($key, $current + 1, 86400 * 30);
    }

    private function recordZeroResultQuery(string $tenantId, string $query): void
    {
        $key     = self::KEY_PREFIX . "tenant:{$tenantId}:zero_queries";
        $queries = Cache::get($key, []);
        $lower   = mb_strtolower(trim($query));
        $queries[$lower] = ($queries[$lower] ?? 0) + 1;
        Cache::put($key, $queries, 86400 * 30);
    }

    private function getTopZeroResultQueries(string $tenantId, int $limit = 10): array
    {
        $queries = Cache::get(self::KEY_PREFIX . "tenant:{$tenantId}:zero_queries", []);
        arsort($queries);
        return array_slice($queries, 0, $limit, true);
    }
}
