<?php

namespace SaaSPlatform\CoreUtils\AI\Governance;

use Illuminate\Support\Facades\Cache;

class AiRateLimitCoordinator
{
    private const RATE_KEY_PREFIX = 'ai:rate:';

    public function allow(string $tenantId, string $userId = ''): bool
    {
        // Check global RPM first
        if (! $this->checkGlobalRate()) {
            return false;
        }

        // Check per-tenant RPM
        $tenantRpm = config('ai.rate_limits.per_tenant_rpm', 20);
        if (! $this->increment(self::RATE_KEY_PREFIX . 'tenant:' . $tenantId, $tenantRpm)) {
            return false;
        }

        // Check per-user RPM if userId provided
        if ($userId) {
            $userRpm = config('ai.rate_limits.per_user_rpm', 10);
            if (! $this->increment(self::RATE_KEY_PREFIX . 'user:' . $userId, $userRpm)) {
                return false;
            }
        }

        return true;
    }

    public function getRemainingRequests(string $tenantId): array
    {
        $tenantRpm = config('ai.rate_limits.per_tenant_rpm', 20);
        $used      = (int) Cache::get(self::RATE_KEY_PREFIX . 'tenant:' . $tenantId . ':' . $this->minuteWindow(), 0);

        return [
            'tenant_rpm'   => $tenantRpm,
            'tenant_used'  => $used,
            'tenant_remaining' => max(0, $tenantRpm - $used),
            'window'       => $this->minuteWindow(),
        ];
    }

    public function getGlobalRate(): array
    {
        $globalRpm  = config('ai.rate_limits.global_rpm', 60);
        $used       = (int) Cache::get(self::RATE_KEY_PREFIX . 'global:' . $this->minuteWindow(), 0);

        return [
            'rpm'       => $globalRpm,
            'used'      => $used,
            'remaining' => max(0, $globalRpm - $used),
        ];
    }

    private function checkGlobalRate(): bool
    {
        $globalRpm = config('ai.rate_limits.global_rpm', 60);
        return $this->increment(self::RATE_KEY_PREFIX . 'global', $globalRpm);
    }

    private function increment(string $baseKey, int $limit): bool
    {
        $key     = $baseKey . ':' . $this->minuteWindow();
        $current = (int) Cache::get($key, 0);

        $burst = $limit * config('ai.rate_limits.burst_factor', 1.5);
        if ($current >= (int) $burst) {
            return false;
        }

        Cache::put($key, $current + 1, 60);
        return true;
    }

    private function minuteWindow(): string
    {
        return date('Y-m-d-H-i');
    }
}
