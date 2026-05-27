<?php

namespace SaaSPlatform\CoreUtils\Realtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class LiveActivityBroadcastService
{
    private const FEED_PREFIX  = 'activity:feed:';
    private const MAX_FEED_SIZE = 50;

    public function broadcast(string $tenantId, string $eventType, array $payload): void
    {
        $event = [
            'id'         => uniqid('evt_', true),
            'type'       => $eventType,
            'tenant_id'  => $tenantId,
            'payload'    => $payload,
            'occurred_at'=> now()->toIso8601String(),
        ];

        $this->appendToFeed($tenantId, $event);

        // Dispatch Laravel event for listeners / queue-based broadcasting
        Event::dispatch('realtime.' . $eventType, $event);
    }

    public function broadcastToUser(string $tenantId, int|string $userId, string $eventType, array $payload): void
    {
        $event = [
            'id'          => uniqid('evt_', true),
            'type'        => $eventType,
            'tenant_id'   => $tenantId,
            'target_user' => $userId,
            'payload'     => $payload,
            'occurred_at' => now()->toIso8601String(),
        ];

        $userKey = self::FEED_PREFIX . "tenant:{$tenantId}:user:{$userId}";
        $this->appendToKey($userKey, $event);
    }

    public function getFeed(string $tenantId, int $limit = 20, int $offset = 0): array
    {
        $key  = self::FEED_PREFIX . "tenant:{$tenantId}:global";
        $feed = Cache::get($key, []);
        return array_slice(array_reverse($feed), $offset, $limit);
    }

    public function getUserFeed(string $tenantId, int|string $userId, int $limit = 20): array
    {
        $key  = self::FEED_PREFIX . "tenant:{$tenantId}:user:{$userId}";
        $feed = Cache::get($key, []);
        return array_slice(array_reverse($feed), 0, $limit);
    }

    public function getLatestSince(string $tenantId, string $sinceId): array
    {
        $feed = $this->getFeed($tenantId, self::MAX_FEED_SIZE);
        $result = [];
        foreach ($feed as $event) {
            if ($event['id'] === $sinceId) {
                break;
            }
            $result[] = $event;
        }
        return $result;
    }

    private function appendToFeed(string $tenantId, array $event): void
    {
        $key = self::FEED_PREFIX . "tenant:{$tenantId}:global";
        $this->appendToKey($key, $event);
    }

    private function appendToKey(string $key, array $event): void
    {
        $feed   = Cache::get($key, []);
        $feed[] = $event;
        if (count($feed) > self::MAX_FEED_SIZE) {
            $feed = array_slice($feed, -self::MAX_FEED_SIZE);
        }
        Cache::put($key, $feed, 3600);
    }

    public function clearFeed(string $tenantId): void
    {
        Cache::forget(self::FEED_PREFIX . "tenant:{$tenantId}:global");
    }
}
