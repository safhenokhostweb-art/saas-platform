<?php

namespace SaaSPlatform\CoreUtils\Analytics;

use Illuminate\Support\Facades\Cache;

class UxInteractionTelemetryService
{
    public function recordInteraction(string $tenantId, int $userId, string $component, string $action, array $meta = []): void
    {
        $key    = "ux:interactions:{$tenantId}";
        $bucket = now()->format('Y-m-d-H');
        $data   = Cache::get($key, []);
        if (! isset($data[$bucket])) $data[$bucket] = [];
        $data[$bucket][] = compact('component', 'action', 'meta') + ['user_id' => $userId, 'at' => now()->toISOString()];

        // Keep last 24 hours
        $cutoff = now()->subHours(24)->format('Y-m-d-H');
        $data = array_filter($data, fn($k) => $k >= $cutoff, ARRAY_FILTER_USE_KEY);
        Cache::put($key, $data, now()->addHours(25));
    }

    public function detectRageClicks(string $tenantId): array
    {
        $key  = "ux:rage:{$tenantId}";
        return Cache::get($key, []);
    }

    public function recordRageClick(string $tenantId, int $userId, string $element, int $clicks, string $page): void
    {
        if ($clicks < 3) return;
        $key  = "ux:rage:{$tenantId}";
        $data = Cache::get($key, []);
        $data[] = [
            'element' => $element,
            'clicks'  => $clicks,
            'page'    => $page,
            'user_id' => $userId,
            'at'      => now()->toISOString(),
        ];
        Cache::put($key, array_slice($data, -100), now()->addDays(7));
    }

    public function detectAbandonment(string $tenantId, int $userId, string $feature, string $step): void
    {
        $key  = "ux:abandon:{$tenantId}";
        $data = Cache::get($key, []);
        $data[] = ['feature' => $feature, 'step' => $step, 'user_id' => $userId, 'at' => now()->toISOString()];
        Cache::put($key, array_slice($data, -500), now()->addDays(7));
    }

    public function getAbandonmentAnalytics(string $tenantId, int $days = 7): array
    {
        $key    = "ux:abandon:{$tenantId}";
        $data   = Cache::get($key, []);
        $cutoff = now()->subDays($days)->toISOString();
        $data   = array_filter($data, fn($r) => ($r['at'] ?? '') >= $cutoff);

        $byFeature = [];
        foreach ($data as $r) {
            $f = $r['feature'];
            if (! isset($byFeature[$f])) $byFeature[$f] = ['count' => 0, 'steps' => []];
            $byFeature[$f]['count']++;
            $s = $r['step'];
            $byFeature[$f]['steps'][$s] = ($byFeature[$f]['steps'][$s] ?? 0) + 1;
        }

        return ['period_days' => $days, 'by_feature' => $byFeature, 'total_abandonments' => count($data)];
    }

    public function getFeatureAdoptionMetrics(string $tenantId, int $days = 30): array
    {
        $key    = "ux:interactions:{$tenantId}";
        $data   = Cache::get($key, []);
        $cutoff = now()->subDays($days)->format('Y-m-d-H');
        $data   = array_filter($data, fn($k) => $k >= $cutoff, ARRAY_FILTER_USE_KEY);

        $byComponent = [];
        foreach ($data as $bucket => $events) {
            foreach ($events as $e) {
                $c = $e['component'];
                $byComponent[$c] = ($byComponent[$c] ?? 0) + 1;
            }
        }
        arsort($byComponent);

        return ['period_days' => $days, 'by_component' => $byComponent];
    }

    public function getRealtimePerformanceMetrics(): array
    {
        return [
            'page_load_p95_ms'   => (int) Cache::get('ux:perf:page_load_p95', 0),
            'api_response_p95_ms' => (int) Cache::get('ux:perf:api_p95', 0),
            'js_error_rate'      => (float) Cache::get('ux:perf:js_error_rate', 0),
            'lcp_p75_ms'         => (int) Cache::get('ux:perf:lcp_p75', 0),
        ];
    }

    public function recordPerformanceSample(string $metric, float $value): void
    {
        $key   = "ux:perf:samples:{$metric}";
        $samples = Cache::get($key, []);
        $samples[] = $value;
        $samples = array_slice($samples, -200);
        Cache::put($key, $samples, now()->addHours(2));

        // Compute p95
        sort($samples);
        $p95idx = (int) ceil(0.95 * count($samples)) - 1;
        Cache::put("ux:perf:{$metric}", $samples[$p95idx] ?? 0, now()->addHours(2));
    }
}
