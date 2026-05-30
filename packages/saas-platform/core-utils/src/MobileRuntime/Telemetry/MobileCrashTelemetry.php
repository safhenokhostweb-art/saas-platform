<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Telemetry;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MobileCrashTelemetry
{
    private const PREFIX = 'mobile:crashes:';
    private const TTL    = 86400;

    public function record(string $tenantId, string $userId, string $deviceId, array $crashData): string
    {
        $crashId = Str::uuid()->toString();
        $crash   = array_merge($crashData, [
            'id'         => $crashId,
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'device_id'  => $deviceId,
            'resolved'   => false,
            'hash'       => $this->hashCrash($crashData),
            'recorded_at' => now()->toIso8601String(),
        ]);

        $crashes = Cache::get(self::PREFIX . $tenantId, []);
        $crashes[] = $crash;
        if (count($crashes) > 500) $crashes = array_slice($crashes, -500);
        Cache::put(self::PREFIX . $tenantId, $crashes, self::TTL);

        Cache::increment(self::PREFIX . "count:{$tenantId}");
        Log::error('mobile.crash.recorded', ['crash_id' => $crashId, 'tenant_id' => $tenantId, 'type' => $crashData['error_type'] ?? 'unknown']);

        return $crashId;
    }

    public function getCrashes(string $tenantId, int $limit = 100): array
    {
        $crashes = Cache::get(self::PREFIX . $tenantId, []);
        return array_slice(array_reverse($crashes), 0, $limit);
    }

    public function getCrashRate(string $tenantId, int $hours = 24): float
    {
        $count = (int) Cache::get(self::PREFIX . "count:{$tenantId}", 0);
        return round($count / max(1, $hours), 4);
    }

    public function getCrashGroups(string $tenantId, int $limit = 0): array
    {
        $crashes = Cache::get(self::PREFIX . $tenantId, []);
        $groups  = [];
        foreach ($crashes as $crash) {
            $hash = $crash['hash'] ?? 'unknown';
            if (! isset($groups[$hash])) {
                $groups[$hash] = [
                    'hash'        => $hash,
                    'error_type'  => $crash['error_type'] ?? 'unknown',
                    'error_message' => $crash['error_message'] ?? '',
                    'count'       => 0,
                    'last_seen'   => $crash['recorded_at'],
                ];
            }
            $groups[$hash]['count']++;
            if ($crash['recorded_at'] > $groups[$hash]['last_seen']) {
                $groups[$hash]['last_seen'] = $crash['recorded_at'];
            }
        }
        usort($groups, fn ($a, $b) => $b['count'] - $a['count']);
        $result = array_values($groups);
        return $limit > 0 ? array_slice($result, 0, $limit) : $result;
    }

    public function getTopCrashes(string $tenantId, int $limit = 10): array
    {
        return array_slice($this->getCrashGroups($tenantId), 0, $limit);
    }

    public function markResolved(string $crashId): void
    {
        // Mark resolved in cache — production would update DB
        Log::info('mobile.crash.resolved', ['crash_id' => $crashId]);
    }

    public function generateCrashReport(string $tenantId): array
    {
        return [
            'tenant_id'      => $tenantId,
            'total_crashes'  => (int) Cache::get(self::PREFIX . "count:{$tenantId}", 0),
            'crash_rate_24h' => $this->getCrashRate($tenantId),
            'top_crashes'    => $this->getTopCrashes($tenantId),
            'generated_at'   => now()->toIso8601String(),
        ];
    }

    private function hashCrash(array $crashData): string
    {
        return md5(($crashData['error_type'] ?? '') . ($crashData['error_message'] ?? ''));
    }
}
