<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Notifications;

use Illuminate\Support\Facades\Cache;

class MobileNotificationPolicyManager
{
    private const PREFIX       = 'mobile:notif_policy:';
    private const TTL          = 3600;
    private const DAILY_TTL    = 86400;
    private const HOURLY_TTL   = 3600;

    public function getPolicy(string $tenantId): array
    {
        return Cache::get(self::PREFIX . $tenantId, $this->getDefaultPolicy());
    }

    public function setPolicy(string $tenantId, array $policy): void
    {
        Cache::put(self::PREFIX . $tenantId, array_merge($this->getDefaultPolicy(), $policy), self::TTL);
    }

    public function isAllowed(string $tenantId, string $notificationType, string $role): bool
    {
        $policy        = $this->getPolicy($tenantId);
        $allowedTypes  = $policy['allowed_types'] ?? [];
        if (! empty($allowedTypes) && ! in_array($notificationType, $allowedTypes, true)) {
            return false;
        }
        $quietHours = $policy['quiet_hours'] ?? null;
        if ($quietHours) {
            $currentHour = (int) now()->format('H');
            $start       = (int) $quietHours['start'];
            $end         = (int) $quietHours['end'];
            if ($start <= $end ? ($currentHour >= $start && $currentHour < $end) : ($currentHour >= $start || $currentHour < $end)) {
                return in_array($notificationType, ['security_alert', 'mandatory_update'], true);
            }
        }
        return true;
    }

    public function getQuota(string $tenantId): array
    {
        $policy    = $this->getPolicy($tenantId);
        $usedToday = (int) Cache::get(self::PREFIX . "daily:{$tenantId}:" . now()->format('Y-m-d'), 0);
        $usedHour  = (int) Cache::get(self::PREFIX . "hourly:{$tenantId}:" . now()->format('Y-m-d-H'), 0);

        return [
            'daily_limit'   => $policy['daily_limit'] ?? 10000,
            'hourly_limit'  => $policy['hourly_limit'] ?? 500,
            'used_today'    => $usedToday,
            'used_hour'     => $usedHour,
        ];
    }

    public function recordSent(string $tenantId): void
    {
        Cache::increment(self::PREFIX . "daily:{$tenantId}:" . now()->format('Y-m-d'));
        Cache::increment(self::PREFIX . "hourly:{$tenantId}:" . now()->format('Y-m-d-H'));
        // Set TTL on first increment
        Cache::add(self::PREFIX . "daily:{$tenantId}:" . now()->format('Y-m-d'), 0, self::DAILY_TTL);
        Cache::add(self::PREFIX . "hourly:{$tenantId}:" . now()->format('Y-m-d-H'), 0, self::HOURLY_TTL);
    }

    public function isQuotaExceeded(string $tenantId): bool
    {
        $quota = $this->getQuota($tenantId);
        return $quota['used_today'] >= $quota['daily_limit']
            || $quota['used_hour'] >= $quota['hourly_limit'];
    }

    public function getRolePolicy(string $tenantId, string $role): array
    {
        $policy = $this->getPolicy($tenantId);
        return $policy['role_policies'][$role] ?? $policy;
    }

    public function getDefaultPolicy(): array
    {
        return [
            'daily_limit'   => 10000,
            'hourly_limit'  => 500,
            'allowed_types' => [],
            'quiet_hours'   => null,
            'role_policies' => [],
        ];
    }
}
