<?php

namespace SaaSPlatform\CoreUtils\Analytics;

use Illuminate\Support\Facades\Cache;

class AiUsageAnalyticsService
{
    public function recordUsage(string $tenantId, string $operation, array $meta = []): void
    {
        $key    = "ai:analytics:usage:{$tenantId}";
        $bucket = now()->format('Y-m-d-H');
        $data   = Cache::get($key, []);
        if (! isset($data[$bucket])) {
            $data[$bucket] = [];
        }
        if (! isset($data[$bucket][$operation])) {
            $data[$bucket][$operation] = ['count' => 0, 'tokens' => 0, 'cost_usd' => 0, 'errors' => 0];
        }
        $data[$bucket][$operation]['count']++;
        $data[$bucket][$operation]['tokens']   += $meta['tokens'] ?? 0;
        $data[$bucket][$operation]['cost_usd'] += $meta['cost_usd'] ?? 0;
        if ($meta['error'] ?? false) {
            $data[$bucket][$operation]['errors']++;
        }
        // Keep 7 days
        $cutoff = now()->subDays(7)->format('Y-m-d-H');
        $data = array_filter($data, fn($k) => $k >= $cutoff, ARRAY_FILTER_USE_KEY);
        Cache::put($key, $data, now()->addDays(8));
    }

    public function getUsageSummary(string $tenantId, int $days = 7): array
    {
        $key    = "ai:analytics:usage:{$tenantId}";
        $data   = Cache::get($key, []);
        $cutoff = now()->subDays($days)->format('Y-m-d-H');
        $data   = array_filter($data, fn($k) => $k >= $cutoff, ARRAY_FILTER_USE_KEY);

        $totals = [];
        foreach ($data as $bucket => $ops) {
            foreach ($ops as $op => $stats) {
                if (! isset($totals[$op])) {
                    $totals[$op] = ['count' => 0, 'tokens' => 0, 'cost_usd' => 0, 'errors' => 0];
                }
                foreach ($stats as $k => $v) {
                    $totals[$op][$k] += $v;
                }
            }
        }

        $totalRequests = array_sum(array_column($totals, 'count'));
        $totalTokens   = array_sum(array_column($totals, 'tokens'));
        $totalCost     = array_sum(array_column($totals, 'cost_usd'));
        $totalErrors   = array_sum(array_column($totals, 'errors'));

        return [
            'tenant_id'       => $tenantId,
            'period_days'     => $days,
            'total_requests'  => $totalRequests,
            'total_tokens'    => $totalTokens,
            'total_cost_usd'  => round($totalCost, 4),
            'total_errors'    => $totalErrors,
            'error_rate'      => $totalRequests > 0 ? round($totalErrors / $totalRequests, 4) : 0,
            'by_operation'    => $totals,
        ];
    }

    public function getEffectivenessScore(string $tenantId): array
    {
        $key    = "ai:effectiveness:{$tenantId}";
        $data   = Cache::get($key, ['accepted' => 0, 'rejected' => 0, 'modified' => 0]);
        $total  = $data['accepted'] + $data['rejected'] + $data['modified'];
        $score  = $total > 0 ? round(($data['accepted'] / $total) * 100, 1) : null;
        return ['score' => $score, 'total_rated' => $total, 'breakdown' => $data];
    }

    public function recordSuggestionFeedback(string $tenantId, string $suggestionId, string $outcome): void
    {
        $key  = "ai:effectiveness:{$tenantId}";
        $data = Cache::get($key, ['accepted' => 0, 'rejected' => 0, 'modified' => 0]);
        $outcome = in_array($outcome, ['accepted', 'rejected', 'modified']) ? $outcome : 'rejected';
        $data[$outcome]++;
        Cache::put($key, $data, now()->addDays(30));
    }

    public function getAdoptionMetrics(string $tenantId): array
    {
        $summary = $this->getUsageSummary($tenantId, 30);
        $ops     = $summary['by_operation'] ?? [];

        $features = [
            'ai_form_assistant'    => ($ops['form-assistant']['count'] ?? 0) > 0,
            'ai_ticket_assistant'  => ($ops['ticket-assistant']['count'] ?? 0) > 0,
            'ai_suggestions'       => ($ops['suggest']['count'] ?? 0) > 0,
            'ai_chat'              => ($ops['chat']['count'] ?? 0) > 0,
            'ai_summarization'     => ($ops['summarize']['count'] ?? 0) > 0,
            'ai_translation'       => ($ops['translate']['count'] ?? 0) > 0,
        ];

        $adoptedCount = count(array_filter($features));
        return [
            'features'         => $features,
            'adoption_rate'    => round($adoptedCount / count($features) * 100, 1),
            'active_features'  => $adoptedCount,
            'total_features'   => count($features),
        ];
    }
}
