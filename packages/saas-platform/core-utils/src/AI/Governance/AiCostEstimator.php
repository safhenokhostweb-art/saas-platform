<?php

namespace SaaSPlatform\CoreUtils\AI\Governance;

use Illuminate\Support\Facades\Cache;

class AiCostEstimator
{
    private const USAGE_KEY_PREFIX = 'ai:cost:';

    public function estimateCost(string $provider, string $model, int $inputTokens, int $outputTokens): float
    {
        $rates = config("ai.cost_rates.{$provider}", []);
        $modelRates = $rates[$model] ?? null;

        if (! $modelRates) {
            return 0.0;
        }

        $inputCost  = ($inputTokens / 1000) * ($modelRates['input'] ?? 0);
        $outputCost = ($outputTokens / 1000) * ($modelRates['output'] ?? 0);

        return round($inputCost + $outputCost, 6);
    }

    public function recordUsage(
        string $tenantId,
        string $provider,
        string $model,
        int    $inputTokens,
        int    $outputTokens
    ): void {
        $cost = $this->estimateCost($provider, $model, $inputTokens, $outputTokens);
        $date = date('Y-m-d');

        $this->addToDaily($tenantId, $date, $inputTokens, $outputTokens, $cost);
        $this->addToDaily('__system__', $date, $inputTokens, $outputTokens, $cost);
    }

    public function getTenantCost(string $tenantId, int $days = 30): array
    {
        $report = [];
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $data = Cache::get(self::USAGE_KEY_PREFIX . $tenantId . ':' . $date, []);
            if (! empty($data)) {
                $report[$date] = $data;
            }
        }
        return array_reverse($report, true);
    }

    public function getTenantTotalCost(string $tenantId, int $days = 30): float
    {
        $total = 0.0;
        foreach ($this->getTenantCost($tenantId, $days) as $day) {
            $total += $day['cost'] ?? 0.0;
        }
        return round($total, 4);
    }

    public function getSystemDailyCost(): array
    {
        return Cache::get(self::USAGE_KEY_PREFIX . '__system__:' . date('Y-m-d'), [
            'input_tokens' => 0, 'output_tokens' => 0, 'cost' => 0.0,
        ]);
    }

    private function addToDaily(string $key, string $date, int $input, int $output, float $cost): void
    {
        $cacheKey = self::USAGE_KEY_PREFIX . $key . ':' . $date;
        $existing = Cache::get($cacheKey, ['input_tokens' => 0, 'output_tokens' => 0, 'cost' => 0.0]);
        $existing['input_tokens']  += $input;
        $existing['output_tokens'] += $output;
        $existing['cost']          += $cost;
        Cache::put($cacheKey, $existing, 86400 * 35);
    }
}
