<?php

namespace SaaSPlatform\CoreUtils\ClientRuntime\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\ClientRuntime\Enums\ClientPlatform;
use SaaSPlatform\CoreUtils\ClientRuntime\Notifications\Contracts\NotificationProviderInterface;

class NotificationTransportCoordinator
{
    private const PREFIX = 'client:coordinator:';
    private const TTL    = 3600;

    public function __construct(
        private readonly NotificationProviderRegistry $registry,
    ) {}

    /**
     * Deliver a notification to a device, trying providers in priority order.
     *
     * Priority 1 — Reverb Realtime (WebSocket)
     * Priority 2 — Firebase Push (optional, mobile only)
     * Priority 3 — Local Notification Recovery (desktop)
     * Priority 4 — Offline Sync Recovery (all platforms, last resort)
     */
    public function deliver(string $deviceId, string $platform, array $notification): array
    {
        $providers = $this->registry->getAvailableForPlatform($platform);

        if (empty($providers)) {
            Log::warning('client.coordinator.no_providers', [
                'device_id' => $deviceId,
                'platform'  => $platform,
            ]);
            return $this->buildResult(false, null, 'no_providers_available');
        }

        foreach ($providers as $provider) {
            $success = $this->attemptDelivery($provider, $deviceId, $notification);

            if ($success) {
                $this->recordDelivery($deviceId, $provider->getName(), true);
                return $this->buildResult(true, $provider->getName());
            }

            Log::debug('client.coordinator.provider_failed', [
                'device_id' => $deviceId,
                'provider'  => $provider->getName(),
                'platform'  => $platform,
            ]);
        }

        $this->recordDelivery($deviceId, 'all_failed', false);
        return $this->buildResult(false, null, 'all_providers_failed');
    }

    /**
     * Deliver to multiple devices (broadcast to a user's device fleet).
     *
     * @param  array<string, string>  $devices  device_id => platform
     */
    public function deliverToDevices(array $devices, array $notification): array
    {
        $results = [];
        foreach ($devices as $deviceId => $platform) {
            $results[$deviceId] = $this->deliver($deviceId, $platform, $notification);
        }
        return $results;
    }

    public function getTransportHealth(): array
    {
        $report    = $this->registry->getHealthReport();
        $available = array_filter($report, fn ($p) => $p['available'] ?? false);

        return [
            'providers'          => $report,
            'available_count'    => count($available),
            'total_count'        => count($report),
            'firebase_optional'  => true,
            'reverb_configured'  => ($report['reverb_realtime']['available'] ?? false),
            'offline_capable'    => ($report['sync_recovery']['available'] ?? false),
        ];
    }

    public function getDeliveryStats(string $deviceId): array
    {
        return Cache::get(self::PREFIX . "stats:{$deviceId}", [
            'delivered' => 0,
            'failed'    => 0,
            'providers' => [],
        ]);
    }

    private function attemptDelivery(
        NotificationProviderInterface $provider,
        string $deviceId,
        array $notification,
    ): bool {
        try {
            return $provider->deliver($deviceId, $notification);
        } catch (\Throwable $e) {
            Log::error('client.coordinator.provider_exception', [
                'provider'  => $provider->getName(),
                'device_id' => $deviceId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function recordDelivery(string $deviceId, string $providerName, bool $success): void
    {
        $key   = self::PREFIX . "stats:{$deviceId}";
        $stats = Cache::get($key, ['delivered' => 0, 'failed' => 0, 'providers' => []]);

        if ($success) {
            $stats['delivered']++;
            $stats['providers'][$providerName] = ($stats['providers'][$providerName] ?? 0) + 1;
        } else {
            $stats['failed']++;
        }

        Cache::put($key, $stats, self::TTL);
    }

    private function buildResult(bool $success, ?string $provider, string $reason = 'ok'): array
    {
        return [
            'success'  => $success,
            'provider' => $provider,
            'reason'   => $reason,
        ];
    }
}
