<?php

namespace SaaSPlatform\CoreUtils\Realtime;

use Illuminate\Support\Facades\Cache;

class ReconnectionEngine
{
    public function handleFailure(string $channel): void
    {
        $key   = "realtime:reconnect:failures:{$channel}";
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addMinutes(5));
    }

    public function getBackoffMs(string $channel): int
    {
        $key     = "realtime:reconnect:failures:{$channel}";
        $failures = (int) Cache::get($key, 0);
        // Exponential backoff: 1s, 2s, 4s, 8s, 16s, max 30s
        return min((int) pow(2, $failures) * 1000, 30_000);
    }

    public function shouldAttemptReconnect(string $channel): bool
    {
        $key = "realtime:reconnect:failures:{$channel}";
        return (int) Cache::get($key, 0) < 10;
    }

    public function reset(string $channel): void
    {
        Cache::forget("realtime:reconnect:failures:{$channel}");
    }
}
