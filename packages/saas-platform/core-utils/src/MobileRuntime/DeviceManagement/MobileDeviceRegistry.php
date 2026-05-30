<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MobileDeviceRegistry
{
    private const CACHE_PREFIX = 'mobile:devices:';
    private const CACHE_TTL    = 3600;

    public function register(string $tenantId, string $userId, array $deviceInfo): array
    {
        $deviceId = $deviceInfo['device_id'] ?? Str::uuid()->toString();
        $record   = array_merge($deviceInfo, [
            'id'         => $deviceId,
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'is_active'  => true,
            'registered_at' => now()->toDateTimeString(),
            'last_seen_at'  => now()->toDateTimeString(),
        ]);

        DB::table('mobile_devices')->upsert(
            [array_merge($record, ['created_at' => now(), 'updated_at' => now()])],
            ['id'],
            ['tenant_id', 'user_id', 'platform', 'os_version', 'app_version', 'device_model', 'timezone', 'locale', 'is_active', 'last_seen_at', 'updated_at']
        );

        Cache::put(self::CACHE_PREFIX . $deviceId, $record, self::CACHE_TTL);
        Log::info('mobile.device.registered', ['device_id' => $deviceId, 'tenant_id' => $tenantId, 'user_id' => $userId]);
        return $record;
    }

    public function unregister(string $deviceId, string $tenantId): void
    {
        DB::table('mobile_devices')
            ->where('id', $deviceId)
            ->where('tenant_id', $tenantId)
            ->update(['is_active' => false, 'updated_at' => now()]);
        Cache::forget(self::CACHE_PREFIX . $deviceId);
    }

    public function getDevice(string $deviceId): ?array
    {
        return Cache::remember(self::CACHE_PREFIX . $deviceId, self::CACHE_TTL, function () use ($deviceId) {
            $row = DB::table('mobile_devices')->where('id', $deviceId)->first();
            return $row ? (array) $row : null;
        });
    }

    public function getDevicesForUser(string $userId, string $tenantId): array
    {
        return Cache::remember(self::CACHE_PREFIX . "user:{$userId}:{$tenantId}", 300, function () use ($userId, $tenantId) {
            return DB::table('mobile_devices')
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('last_seen_at', 'desc')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->toArray();
        });
    }

    public function updateDeviceInfo(string $deviceId, array $info): void
    {
        DB::table('mobile_devices')
            ->where('id', $deviceId)
            ->update(array_merge($info, ['last_seen_at' => now(), 'updated_at' => now()]));
        Cache::forget(self::CACHE_PREFIX . $deviceId);
    }

    public function getActiveDevices(string $tenantId): array
    {
        return DB::table('mobile_devices')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('last_seen_at', 'desc')
            ->limit(500)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }

    public function countDevicesForUser(string $userId, string $tenantId): int
    {
        return DB::table('mobile_devices')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();
    }

    public function isRegistered(string $deviceId): bool
    {
        return $this->getDevice($deviceId) !== null;
    }
}
