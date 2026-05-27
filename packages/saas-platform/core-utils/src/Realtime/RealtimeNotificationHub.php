<?php

namespace SaaSPlatform\CoreUtils\Realtime;

use Illuminate\Support\Facades\Cache;

class RealtimeNotificationHub
{
    private const NOTIF_PREFIX = 'notif:hub:';
    private const MAX_NOTIFS   = 100;

    public function push(string $tenantId, int|string $userId, string $type, string $message, array $data = [], string $severity = 'info'): string
    {
        $id = uniqid('n_', true);
        $notification = [
            'id'         => $id,
            'type'       => $type,
            'severity'   => $severity,
            'message'    => $message,
            'data'       => $data,
            'read'       => false,
            'created_at' => now()->toIso8601String(),
        ];

        $key   = $this->userKey($tenantId, $userId);
        $items = Cache::get($key, []);
        array_unshift($items, $notification);

        if (count($items) > self::MAX_NOTIFS) {
            $items = array_slice($items, 0, self::MAX_NOTIFS);
        }

        Cache::put($key, $items, 86400);
        $this->updateUnreadCount($tenantId, $userId, 1);

        return $id;
    }

    public function pushBroadcast(string $tenantId, string $type, string $message, array $data = [], string $severity = 'info'): void
    {
        $key   = $this->broadcastKey($tenantId);
        $items = Cache::get($key, []);
        array_unshift($items, [
            'id'         => uniqid('nb_', true),
            'type'       => $type,
            'severity'   => $severity,
            'message'    => $message,
            'data'       => $data,
            'created_at' => now()->toIso8601String(),
        ]);
        if (count($items) > 20) {
            $items = array_slice($items, 0, 20);
        }
        Cache::put($key, $items, 3600);
    }

    public function getUnread(string $tenantId, int|string $userId): array
    {
        $items = Cache::get($this->userKey($tenantId, $userId), []);
        return array_filter($items, fn ($n) => ! $n['read']);
    }

    public function getAll(string $tenantId, int|string $userId, int $limit = 30): array
    {
        $items      = Cache::get($this->userKey($tenantId, $userId), []);
        $broadcasts = Cache::get($this->broadcastKey($tenantId), []);
        $merged     = array_merge($items, $broadcasts);

        usort($merged, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return array_slice($merged, 0, $limit);
    }

    public function markRead(string $tenantId, int|string $userId, string $notifId): void
    {
        $key   = $this->userKey($tenantId, $userId);
        $items = Cache::get($key, []);
        $decremented = false;
        foreach ($items as &$item) {
            if ($item['id'] === $notifId && ! $item['read']) {
                $item['read'] = true;
                $decremented  = true;
                break;
            }
        }
        unset($item);
        Cache::put($key, $items, 86400);
        if ($decremented) {
            $this->updateUnreadCount($tenantId, $userId, -1);
        }
    }

    public function markAllRead(string $tenantId, int|string $userId): void
    {
        $key   = $this->userKey($tenantId, $userId);
        $items = Cache::get($key, []);
        foreach ($items as &$item) {
            $item['read'] = true;
        }
        unset($item);
        Cache::put($key, $items, 86400);
        Cache::put($this->countKey($tenantId, $userId), 0, 86400);
    }

    public function getUnreadCount(string $tenantId, int|string $userId): int
    {
        return (int) Cache::get($this->countKey($tenantId, $userId), 0);
    }

    private function updateUnreadCount(string $tenantId, int|string $userId, int $delta): void
    {
        $key   = $this->countKey($tenantId, $userId);
        $count = max(0, (int) Cache::get($key, 0) + $delta);
        Cache::put($key, $count, 86400);
    }

    private function userKey(string $tenantId, int|string $userId): string
    {
        return self::NOTIF_PREFIX . "tenant:{$tenantId}:user:{$userId}";
    }

    private function broadcastKey(string $tenantId): string
    {
        return self::NOTIF_PREFIX . "tenant:{$tenantId}:broadcast";
    }

    private function countKey(string $tenantId, int|string $userId): string
    {
        return self::NOTIF_PREFIX . "tenant:{$tenantId}:user:{$userId}:count";
    }
}
