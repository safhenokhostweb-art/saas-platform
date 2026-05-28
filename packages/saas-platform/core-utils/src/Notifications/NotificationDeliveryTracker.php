<?php

namespace SaaSPlatform\CoreUtils\Notifications;

use Illuminate\Support\Facades\Cache;

class NotificationDeliveryTracker
{
    public function record(string $notificationId, string $channel, bool $success): void
    {
        $key  = "notif:delivery:{$notificationId}";
        $data = Cache::get($key, []);
        $data[$channel] = [
            'success' => $success,
            'at'      => now()->toISOString(),
        ];
        Cache::put($key, $data, now()->addDays(7));
    }

    public function getDeliveryStatus(string $notificationId): array
    {
        return Cache::get("notif:delivery:{$notificationId}", []);
    }

    public function getFailureAnalytics(string $tenantId, int $hours = 24): array
    {
        $key  = "notif:failures:{$tenantId}";
        $logs = Cache::get($key, []);
        $cutoff = now()->subHours($hours)->toISOString();
        return array_values(array_filter($logs, fn($l) => ($l['at'] ?? '') >= $cutoff));
    }

    public function recordFailure(string $tenantId, string $channel, string $reason): void
    {
        $key  = "notif:failures:{$tenantId}";
        $logs = Cache::get($key, []);
        $logs[] = ['channel' => $channel, 'reason' => $reason, 'at' => now()->toISOString()];
        Cache::put($key, array_slice($logs, -500), now()->addDays(7));
    }
}
