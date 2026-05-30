<?php

namespace SaaSPlatform\CoreUtils\ClientRuntime\Notifications\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\ClientRuntime\Notifications\Contracts\NotificationProviderInterface;

class SyncRecoveryProvider implements NotificationProviderInterface
{
    private const PRIORITY = 4;
    private const PREFIX   = 'client:transport:sync:';
    private const TTL      = 86400; // 24 hours — missed notifications expire after one day

    public function getName(): string
    {
        return 'sync_recovery';
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function isAvailable(): bool
    {
        // Sync recovery is always available — it is the last-resort offline fallback
        return true;
    }

    public function canDeliver(string $platform): bool
    {
        // Offline sync recovery applies to all platforms
        return true;
    }

    public function deliver(string $deviceId, array $notification): bool
    {
        $notificationId = $notification['id'] ?? (string) Str::uuid();
        $tenantId       = $notification['tenant_id'] ?? 'unknown';
        $key            = self::PREFIX . "missed:{$deviceId}";

        try {
            $missed   = Cache::get($key, []);
            $missed[] = [
                'id'          => $notificationId,
                'tenant_id'   => $tenantId,
                'payload'     => $notification,
                'missed_at'   => now()->toIso8601String(),
                'recovered'   => false,
            ];

            // Retain at most 200 missed notifications per device to bound memory
            if (count($missed) > 200) {
                $missed = array_slice($missed, -200);
            }

            Cache::put($key, $missed, self::TTL);

            Log::debug('client.transport.sync.queued', [
                'device_id'       => $deviceId,
                'notification_id' => $notificationId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('client.transport.sync.failed', [
                'device_id' => $deviceId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function recoverMissed(string $deviceId): array
    {
        $key    = self::PREFIX . "missed:{$deviceId}";
        $missed = Cache::get($key, []);
        $unrecovered = array_filter($missed, fn ($n) => ! $n['recovered']);

        // Mark all as recovered
        $missed = array_map(fn ($n) => array_merge($n, ['recovered' => true]), $missed);
        Cache::put($key, $missed, self::TTL);

        return array_values($unrecovered);
    }

    public function getMissedCount(string $deviceId): int
    {
        $missed = Cache::get(self::PREFIX . "missed:{$deviceId}", []);
        return count(array_filter($missed, fn ($n) => ! $n['recovered']));
    }

    public function getHealthStatus(): array
    {
        return [
            'provider'  => $this->getName(),
            'priority'  => $this->getPriority(),
            'available' => $this->isAvailable(),
            'transport' => 'offline_sync',
            'ttl_hours' => self::TTL / 3600,
            'platforms' => ['android', 'ios', 'windows', 'linux', 'macos', 'web'],
        ];
    }
}
