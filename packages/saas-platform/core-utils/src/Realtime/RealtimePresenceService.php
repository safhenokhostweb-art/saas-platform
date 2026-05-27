<?php

namespace SaaSPlatform\CoreUtils\Realtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class RealtimePresenceService
{
    private const PRESENCE_TTL = 90;
    private const PRESENCE_PREFIX = 'presence:';

    public function markOnline(string $tenantId, int|string $userId, array $context = []): void
    {
        $key = $this->presenceKey($tenantId, $userId);
        Cache::put($key, array_merge([
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'status'     => 'online',
            'last_seen'  => now()->toIso8601String(),
            'context'    => $context,
        ], $context), self::PRESENCE_TTL);
    }

    public function markOffline(string $tenantId, int|string $userId): void
    {
        Cache::forget($this->presenceKey($tenantId, $userId));
    }

    public function getOnlineUsers(string $tenantId): array
    {
        $pattern = self::PRESENCE_PREFIX . "tenant:{$tenantId}:user:*";
        return $this->scanPresenceKeys($tenantId);
    }

    public function getPresence(string $tenantId, int|string $userId): ?array
    {
        return Cache::get($this->presenceKey($tenantId, $userId));
    }

    public function isOnline(string $tenantId, int|string $userId): bool
    {
        return Cache::has($this->presenceKey($tenantId, $userId));
    }

    public function setContext(string $tenantId, int|string $userId, string $resource, string $resourceId): void
    {
        $key  = $this->presenceKey($tenantId, $userId);
        $data = Cache::get($key, []);
        if (empty($data)) {
            return;
        }
        $data['context'] = ['resource' => $resource, 'resource_id' => $resourceId, 'since' => now()->toIso8601String()];
        Cache::put($key, $data, self::PRESENCE_TTL);
    }

    public function getViewersOf(string $tenantId, string $resource, string $resourceId): array
    {
        $users = $this->scanPresenceKeys($tenantId);
        return array_filter($users, fn ($u) =>
            ($u['context']['resource'] ?? null) === $resource &&
            (string)($u['context']['resource_id'] ?? '') === (string)$resourceId
        );
    }

    public function heartbeat(string $tenantId, int|string $userId): void
    {
        $key  = $this->presenceKey($tenantId, $userId);
        $data = Cache::get($key);
        if ($data) {
            $data['last_seen'] = now()->toIso8601String();
            Cache::put($key, $data, self::PRESENCE_TTL);
        }
    }

    private function presenceKey(string $tenantId, int|string $userId): string
    {
        return self::PRESENCE_PREFIX . "tenant:{$tenantId}:user:{$userId}";
    }

    private function scanPresenceKeys(string $tenantId): array
    {
        // Cache-driver-agnostic: store an index of active user IDs per tenant
        $indexKey = self::PRESENCE_PREFIX . "tenant:{$tenantId}:index";
        $userIds  = Cache::get($indexKey, []);
        $results  = [];
        $alive    = [];
        foreach ($userIds as $uid) {
            $data = Cache::get($this->presenceKey($tenantId, $uid));
            if ($data) {
                $results[] = $data;
                $alive[]   = $uid;
            }
        }
        // Prune stale user IDs from the index
        if (count($alive) !== count($userIds)) {
            Cache::put($indexKey, $alive, 3600);
        }
        return $results;
    }

    public function registerUserInIndex(string $tenantId, int|string $userId): void
    {
        $indexKey = self::PRESENCE_PREFIX . "tenant:{$tenantId}:index";
        $ids      = Cache::get($indexKey, []);
        if (! in_array($userId, $ids)) {
            $ids[] = $userId;
            Cache::put($indexKey, $ids, 3600);
        }
    }
}
