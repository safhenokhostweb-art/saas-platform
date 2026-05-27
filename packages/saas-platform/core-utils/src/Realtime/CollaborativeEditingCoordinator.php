<?php

namespace SaaSPlatform\CoreUtils\Realtime;

use Illuminate\Support\Facades\Cache;

class CollaborativeEditingCoordinator
{
    private const LOCK_PREFIX = 'collab:lock:';
    private const LOCK_TTL    = 120;

    public function __construct(
        private readonly UserPresenceTracker $tracker
    ) {}

    public function acquireLock(string $tenantId, string $resource, string $resourceId, int|string $userId): bool
    {
        $key     = $this->lockKey($tenantId, $resource, $resourceId);
        $current = Cache::get($key);

        if ($current && $current['user_id'] !== $userId) {
            return false;
        }

        Cache::put($key, [
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'resource'   => $resource,
            'resource_id'=> $resourceId,
            'locked_at'  => now()->toIso8601String(),
            'expires_at' => now()->addSeconds(self::LOCK_TTL)->toIso8601String(),
        ], self::LOCK_TTL);

        $this->tracker->viewing($tenantId, $userId, $resource, $resourceId);

        return true;
    }

    public function releaseLock(string $tenantId, string $resource, string $resourceId, int|string $userId): void
    {
        $key     = $this->lockKey($tenantId, $resource, $resourceId);
        $current = Cache::get($key);

        if ($current && $current['user_id'] === $userId) {
            Cache::forget($key);
        }
    }

    public function getLock(string $tenantId, string $resource, string $resourceId): ?array
    {
        return Cache::get($this->lockKey($tenantId, $resource, $resourceId));
    }

    public function isLocked(string $tenantId, string $resource, string $resourceId): bool
    {
        return Cache::has($this->lockKey($tenantId, $resource, $resourceId));
    }

    public function isLockedByOther(string $tenantId, string $resource, string $resourceId, int|string $userId): bool
    {
        $lock = $this->getLock($tenantId, $resource, $resourceId);
        return $lock !== null && $lock['user_id'] !== $userId;
    }

    public function refreshLock(string $tenantId, string $resource, string $resourceId, int|string $userId): bool
    {
        $key     = $this->lockKey($tenantId, $resource, $resourceId);
        $current = Cache::get($key);

        if (! $current || $current['user_id'] !== $userId) {
            return false;
        }

        $current['expires_at'] = now()->addSeconds(self::LOCK_TTL)->toIso8601String();
        Cache::put($key, $current, self::LOCK_TTL);

        return true;
    }

    public function forceRelease(string $tenantId, string $resource, string $resourceId): void
    {
        Cache::forget($this->lockKey($tenantId, $resource, $resourceId));
    }

    public function getViewers(string $tenantId, string $resource, string $resourceId): array
    {
        return $this->tracker->getResourceViewers($tenantId, $resource, $resourceId);
    }

    private function lockKey(string $tenantId, string $resource, string $resourceId): string
    {
        return self::LOCK_PREFIX . "{$tenantId}:{$resource}:{$resourceId}";
    }
}
