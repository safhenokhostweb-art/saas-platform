<?php

namespace SaaSPlatform\CoreUtils\AI\Governance;

use Illuminate\Support\Facades\Cache;

class TenantAiQuotaManager
{
    private const USAGE_KEY_PREFIX = 'ai:quota:usage:';
    private const PLAN_KEY_PREFIX  = 'ai:quota:plan:';

    public function check(string $tenantId): array
    {
        $limit = $this->getDailyLimit($tenantId);
        $used  = $this->getDailyUsage($tenantId);

        if ($limit === -1) {
            return ['allowed' => true, 'used' => $used, 'limit' => 'unlimited', 'remaining' => 'unlimited'];
        }

        $allowed = $used < $limit;
        return [
            'allowed'   => $allowed,
            'used'      => $used,
            'limit'     => $limit,
            'remaining' => max(0, $limit - $used),
            'pct'       => $limit > 0 ? round(($used / $limit) * 100, 1) : 0,
        ];
    }

    public function increment(string $tenantId, int $requests = 1): void
    {
        $key = self::USAGE_KEY_PREFIX . $tenantId . ':' . date('Y-m-d');
        $current = (int) Cache::get($key, 0);
        Cache::put($key, $current + $requests, 86400);
    }

    public function getDailyUsage(string $tenantId): int
    {
        return (int) Cache::get(self::USAGE_KEY_PREFIX . $tenantId . ':' . date('Y-m-d'), 0);
    }

    public function getDailyLimit(string $tenantId): int
    {
        // Check if tenant has a custom plan limit set
        $customLimit = Cache::get(self::PLAN_KEY_PREFIX . $tenantId);
        if ($customLimit !== null) {
            return (int) $customLimit;
        }

        // Fall back to config default
        return (int) config('ai.quotas.default_daily', 50);
    }

    public function setTenantPlan(string $tenantId, string $plan): void
    {
        $quotas = config('ai.quotas', []);
        $limit = match($plan) {
            'free'       => $quotas['free_plan_daily'] ?? 10,
            'basic'      => $quotas['basic_plan_daily'] ?? 100,
            'pro'        => $quotas['pro_plan_daily'] ?? 500,
            'enterprise' => $quotas['enterprise_daily'] ?? 2000,
            'unlimited'  => -1,
            default      => $quotas['default_daily'] ?? 50,
        };
        Cache::put(self::PLAN_KEY_PREFIX . $tenantId, $limit, 86400 * 365);
    }

    public function setCustomLimit(string $tenantId, int $dailyLimit): void
    {
        Cache::put(self::PLAN_KEY_PREFIX . $tenantId, $dailyLimit, 86400 * 365);
    }

    public function getUsageReport(string $tenantId, int $days = 7): array
    {
        $report = [];
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $report[$date] = (int) Cache::get(self::USAGE_KEY_PREFIX . $tenantId . ':' . $date, 0);
        }
        return array_reverse($report, true);
    }

    public function getSystemUsageReport(int $days = 7): array
    {
        // Platform-wide daily AI request counts
        $report = [];
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $report[$date] = (int) Cache::get('ai:quota:total:' . $date, 0);
        }
        return array_reverse($report, true);
    }

    public function incrementSystemTotal(int $requests = 1): void
    {
        $key = 'ai:quota:total:' . date('Y-m-d');
        Cache::put($key, (int) Cache::get($key, 0) + $requests, 86400);
    }
}
