<?php

namespace SaaSPlatform\CoreUtils\AI\Governance;

use Illuminate\Support\Facades\Cache;

class AiRequestBudgetManager
{
    private const BUDGET_KEY_PREFIX = 'ai:budget:';

    /**
     * Check if a request is within budget constraints.
     * Returns: ['allowed', 'reason', 'remaining_budget']
     */
    public function checkBudget(string $tenantId, float $estimatedCost): array
    {
        $dailyBudget = $this->getDailyBudget($tenantId);

        if ($dailyBudget <= 0) {
            return ['allowed' => true, 'reason' => 'no_budget_limit', 'remaining' => null];
        }

        $spent     = $this->getDailySpend($tenantId);
        $remaining = $dailyBudget - $spent;

        if ($estimatedCost > $remaining) {
            return [
                'allowed'   => false,
                'reason'    => 'budget_exceeded',
                'remaining' => max(0, $remaining),
                'budget'    => $dailyBudget,
                'spent'     => $spent,
            ];
        }

        return [
            'allowed'   => true,
            'reason'    => 'within_budget',
            'remaining' => max(0, $remaining),
            'budget'    => $dailyBudget,
            'spent'     => $spent,
        ];
    }

    public function recordSpend(string $tenantId, float $cost): void
    {
        $key     = self::BUDGET_KEY_PREFIX . $tenantId . ':spend:' . date('Y-m-d');
        $current = (float) Cache::get($key, 0.0);
        Cache::put($key, round($current + $cost, 6), 86400);
    }

    public function setDailyBudget(string $tenantId, float $usdLimit): void
    {
        Cache::put(self::BUDGET_KEY_PREFIX . $tenantId . ':daily', $usdLimit, 86400 * 365);
    }

    public function getDailyBudget(string $tenantId): float
    {
        return (float) Cache::get(self::BUDGET_KEY_PREFIX . $tenantId . ':daily', 0.0);
    }

    public function getDailySpend(string $tenantId): float
    {
        return (float) Cache::get(self::BUDGET_KEY_PREFIX . $tenantId . ':spend:' . date('Y-m-d'), 0.0);
    }

    public function getBudgetStatus(string $tenantId): array
    {
        $budget    = $this->getDailyBudget($tenantId);
        $spent     = $this->getDailySpend($tenantId);
        $remaining = max(0, $budget - $spent);
        $pct       = $budget > 0 ? round(($spent / $budget) * 100, 1) : 0;

        return [
            'has_budget' => $budget > 0,
            'budget'     => $budget,
            'spent'      => $spent,
            'remaining'  => $remaining,
            'pct_used'   => $pct,
            'status'     => $pct >= 100 ? 'exhausted' : ($pct >= 80 ? 'warning' : 'ok'),
        ];
    }
}
