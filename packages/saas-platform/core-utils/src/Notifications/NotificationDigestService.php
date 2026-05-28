<?php

namespace SaaSPlatform\CoreUtils\Notifications;

use Illuminate\Support\Facades\Cache;

class NotificationDigestService
{
    public function queue(string $tenantId, int $userId, string $notificationId, array $data): void
    {
        $key  = "notif:digest:{$tenantId}:{$userId}";
        $q    = Cache::get($key, []);
        $q[]  = array_merge($data, ['id' => $notificationId, 'queued_at' => now()->toISOString()]);
        Cache::put($key, $q, now()->addDays(7));
    }

    public function flush(string $tenantId, int $userId): array
    {
        $key   = "notif:digest:{$tenantId}:{$userId}";
        $items = Cache::pull($key, []);
        return $items;
    }

    public function count(string $tenantId, int $userId): int
    {
        return count(Cache::get("notif:digest:{$tenantId}:{$userId}", []));
    }
}
