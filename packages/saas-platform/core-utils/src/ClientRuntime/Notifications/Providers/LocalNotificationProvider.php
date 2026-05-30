<?php

namespace SaaSPlatform\CoreUtils\ClientRuntime\Notifications\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\ClientRuntime\Enums\ClientPlatform;
use SaaSPlatform\CoreUtils\ClientRuntime\Notifications\Contracts\NotificationProviderInterface;

class LocalNotificationProvider implements NotificationProviderInterface
{
    private const PRIORITY = 3;
    private const PREFIX   = 'client:transport:local:';
    private const TTL      = 3600;

    public function getName(): string
    {
        return 'local_notification';
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function isAvailable(): bool
    {
        // Local notification delivery is always structurally available —
        // the client app polls and renders these notifications natively.
        return true;
    }

    public function canDeliver(string $platform): bool
    {
        try {
            $clientPlatform = ClientPlatform::fromString($platform);
            return $clientPlatform->supportsLocalNotifications();
        } catch (\Throwable) {
            return false;
        }
    }

    public function deliver(string $deviceId, array $notification): bool
    {
        $notificationId = $notification['id'] ?? (string) Str::uuid();
        $key = self::PREFIX . "pending:{$deviceId}";

        try {
            $pending   = Cache::get($key, []);
            $pending[] = array_merge($notification, [
                'id'          => $notificationId,
                'queued_at'   => now()->toIso8601String(),
                'transport'   => 'local',
            ]);

            // Keep only the last 100 pending notifications per device
            if (count($pending) > 100) {
                $pending = array_slice($pending, -100);
            }

            Cache::put($key, $pending, self::TTL);
            Cache::increment(self::PREFIX . "count:{$deviceId}");

            Log::debug('client.transport.local.queued', [
                'device_id'       => $deviceId,
                'notification_id' => $notificationId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('client.transport.local.failed', [
                'device_id' => $deviceId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getPendingNotifications(string $deviceId): array
    {
        return Cache::get(self::PREFIX . "pending:{$deviceId}", []);
    }

    public function acknowledgePending(string $deviceId, array $notificationIds): void
    {
        $key     = self::PREFIX . "pending:{$deviceId}";
        $pending = Cache::get($key, []);
        $pending = array_values(array_filter(
            $pending,
            fn ($n) => ! in_array($n['id'] ?? '', $notificationIds, true),
        ));
        Cache::put($key, $pending, self::TTL);
    }

    public function getHealthStatus(): array
    {
        return [
            'provider'  => $this->getName(),
            'priority'  => $this->getPriority(),
            'available' => $this->isAvailable(),
            'transport' => 'local_cache',
            'platforms' => ['windows', 'linux', 'macos'],
        ];
    }
}
