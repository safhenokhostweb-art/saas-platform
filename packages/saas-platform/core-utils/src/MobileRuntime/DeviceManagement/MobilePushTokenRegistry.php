<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MobilePushTokenRegistry
{
    private const CACHE_PREFIX = 'mobile:push_tokens:';
    private const CACHE_TTL    = 3600;

    public function register(string $deviceId, string $userId, string $tenantId, string $token, string $platform): void
    {
        $record = [
            'device_id'  => $deviceId,
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'token'      => $token,
            'platform'   => $platform,
            'is_valid'   => true,
            'updated_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
        ];

        DB::table('mobile_push_tokens')->upsert(
            [$record],
            ['device_id'],
            ['token', 'platform', 'is_valid', 'updated_at']
        );

        Cache::put(self::CACHE_PREFIX . $deviceId, [
            'token'      => $token,
            'platform'   => $platform,
            'is_valid'   => true,
            'updated_at' => now()->toIso8601String(),
        ], self::CACHE_TTL);

        Log::debug('mobile.push_token.registered', ['device_id' => $deviceId, 'platform' => $platform]);
    }

    public function unregister(string $deviceId): void
    {
        DB::table('mobile_push_tokens')->where('device_id', $deviceId)->delete();
        Cache::forget(self::CACHE_PREFIX . $deviceId);
    }

    public function getToken(string $deviceId): ?array
    {
        return Cache::remember(self::CACHE_PREFIX . $deviceId, self::CACHE_TTL, function () use ($deviceId) {
            $row = DB::table('mobile_push_tokens')->where('device_id', $deviceId)->where('is_valid', true)->first();
            return $row ? (array) $row : null;
        });
    }

    public function getTokensForUser(string $userId, string $tenantId): array
    {
        return DB::table('mobile_push_tokens')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('is_valid', true)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }

    public function updateToken(string $deviceId, string $newToken): void
    {
        DB::table('mobile_push_tokens')
            ->where('device_id', $deviceId)
            ->update(['token' => $newToken, 'updated_at' => now()]);
        Cache::forget(self::CACHE_PREFIX . $deviceId);
    }

    public function markTokenInvalid(string $deviceId, string $reason): void
    {
        DB::table('mobile_push_tokens')
            ->where('device_id', $deviceId)
            ->update(['is_valid' => false, 'updated_at' => now()]);
        Cache::forget(self::CACHE_PREFIX . $deviceId);
        Log::warning('mobile.push_token.invalidated', ['device_id' => $deviceId, 'reason' => $reason]);
    }

    public function getValidTokensForTenant(string $tenantId): array
    {
        return DB::table('mobile_push_tokens')
            ->where('tenant_id', $tenantId)
            ->where('is_valid', true)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }

    public function rotateToken(string $deviceId, string $newToken): void
    {
        $this->updateToken($deviceId, $newToken);
        Log::info('mobile.push_token.rotated', ['device_id' => $deviceId]);
    }
}
