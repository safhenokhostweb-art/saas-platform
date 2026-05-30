<?php

namespace SaaSPlatform\CoreUtils\ClientRuntime\Notifications\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\ClientRuntime\Notifications\Contracts\NotificationProviderInterface;
use SaaSPlatform\CoreUtils\MobileRuntime\Realtime\MobileRealtimeCoordinator;

class ReverbRealtimeProvider implements NotificationProviderInterface
{
    private const PRIORITY = 1;
    private const PREFIX   = 'client:transport:reverb:';

    public function __construct(
        private readonly MobileRealtimeCoordinator $realtimeCoordinator,
    ) {}

    public function getName(): string
    {
        return 'reverb_realtime';
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function isAvailable(): bool
    {
        return $this->realtimeCoordinator->validateTransport();
    }

    public function canDeliver(string $platform): bool
    {
        // Reverb WebSocket works on all platforms
        return true;
    }

    public function deliver(string $deviceId, array $notification): bool
    {
        $tenantId = $notification['tenant_id'] ?? '';
        $userId   = $notification['user_id'] ?? null;

        if (empty($tenantId)) {
            Log::debug('client.transport.reverb.no_tenant', ['device_id' => $deviceId]);
            return false;
        }

        try {
            if ($userId !== null) {
                $this->realtimeCoordinator->broadcastToUser(
                    $tenantId,
                    $userId,
                    $notification['event'] ?? 'notification',
                    $notification,
                );
            } else {
                $this->realtimeCoordinator->broadcastToTenant(
                    $tenantId,
                    $notification['event'] ?? 'notification',
                    $notification,
                );
            }

            Cache::increment(self::PREFIX . "delivered:{$tenantId}");
            return true;
        } catch (\Throwable $e) {
            Log::warning('client.transport.reverb.failed', [
                'device_id' => $deviceId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getHealthStatus(): array
    {
        $available = $this->isAvailable();
        return [
            'provider'  => $this->getName(),
            'priority'  => $this->getPriority(),
            'available' => $available,
            'transport' => 'websocket',
            'host'      => Config::get('broadcasting.connections.reverb.options.host', '127.0.0.1'),
            'port'      => Config::get('broadcasting.connections.reverb.options.port', 8080),
        ];
    }
}
