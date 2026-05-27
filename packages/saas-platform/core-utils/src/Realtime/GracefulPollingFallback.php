<?php

namespace SaaSPlatform\CoreUtils\Realtime;

use Illuminate\Support\Facades\Cache;

class GracefulPollingFallback
{
    private const TTL = 300; // seconds
    private const MAX_EVENTS_PER_CHANNEL = 50;

    public function queue(string $channel, array $event): bool
    {
        $key    = "realtime:poll:{$channel}";
        $events = Cache::get($key, []);
        $events[] = $event;

        // Keep only last N events
        if (count($events) > self::MAX_EVENTS_PER_CHANNEL) {
            $events = array_slice($events, -self::MAX_EVENTS_PER_CHANNEL);
        }

        Cache::put($key, $events, self::TTL);
        return true;
    }

    public function poll(string $channel, ?string $since = null): array
    {
        $key    = "realtime:poll:{$channel}";
        $events = Cache::get($key, []);

        if ($since === null) {
            return $events;
        }

        return array_values(array_filter($events, fn($e) => ($e['timestamp'] ?? '') > $since));
    }

    public function pollForTenant(string $tenantId, ?string $since = null): array
    {
        return $this->poll("tenant.{$tenantId}", $since);
    }

    public function clear(string $channel): void
    {
        Cache::forget("realtime:poll:{$channel}");
    }
}
