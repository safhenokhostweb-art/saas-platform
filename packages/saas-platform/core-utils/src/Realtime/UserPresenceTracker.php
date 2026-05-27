<?php

namespace SaaSPlatform\CoreUtils\Realtime;

use Illuminate\Support\Facades\Cache;

class UserPresenceTracker
{
    public function __construct(
        private readonly RealtimePresenceService $presenceService
    ) {}

    public function track(string $tenantId, int|string $userId, array $meta = []): void
    {
        $this->presenceService->registerUserInIndex($tenantId, $userId);
        $this->presenceService->markOnline($tenantId, $userId, $meta);
    }

    public function untrack(string $tenantId, int|string $userId): void
    {
        $this->presenceService->markOffline($tenantId, $userId);
    }

    public function pulse(string $tenantId, int|string $userId): void
    {
        $this->presenceService->heartbeat($tenantId, $userId);
    }

    public function viewing(string $tenantId, int|string $userId, string $resource, string $resourceId): void
    {
        $this->presenceService->setContext($tenantId, $userId, $resource, $resourceId);
    }

    public function getActiveUsers(string $tenantId): array
    {
        return $this->presenceService->getOnlineUsers($tenantId);
    }

    public function countActive(string $tenantId): int
    {
        return count($this->getActiveUsers($tenantId));
    }

    public function getResourceViewers(string $tenantId, string $resource, string $resourceId): array
    {
        return array_values($this->presenceService->getViewersOf($tenantId, $resource, $resourceId));
    }

    public function isOnline(string $tenantId, int|string $userId): bool
    {
        return $this->presenceService->isOnline($tenantId, $userId);
    }

    public function snapshot(string $tenantId): array
    {
        $users = $this->getActiveUsers($tenantId);
        return [
            'total_online' => count($users),
            'users'        => array_map(fn ($u) => [
                'user_id'   => $u['user_id'],
                'status'    => $u['status'],
                'last_seen' => $u['last_seen'],
                'context'   => $u['context'] ?? null,
            ], $users),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
