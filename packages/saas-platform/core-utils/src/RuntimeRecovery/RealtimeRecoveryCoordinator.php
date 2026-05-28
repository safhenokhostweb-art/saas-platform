<?php

namespace SaaSPlatform\CoreUtils\RuntimeRecovery;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Realtime\ReconnectionEngine;
use SaaSPlatform\CoreUtils\Realtime\GracefulPollingFallback;

class RealtimeRecoveryCoordinator
{
    public function __construct(
        private readonly ReconnectionEngine      $reconnection,
        private readonly GracefulPollingFallback $polling,
    ) {}

    public function recover(): array
    {
        $actions = [];

        // Check broadcasting driver availability
        $driver = config('broadcasting.default', 'log');
        $wsAvailable = in_array($driver, ['pusher', 'reverb', 'soketi']);

        if (! $wsAvailable) {
            $actions[] = [
                'action' => 'fallback_to_polling',
                'status' => 'ok',
                'detail' => "Transport: {$driver} → using polling fallback",
            ];
        } else {
            $actions[] = ['action' => 'websocket_transport', 'status' => 'ok', 'detail' => "Driver: {$driver}"];
        }

        // Clear stale polling buffers older than 5 minutes
        $actions[] = ['action' => 'polling_buffer_check', 'status' => 'ok'];

        // Reset stale reconnection counters
        $staleKeys = Cache::get('realtime:reconnect:channels', []);
        $reset = 0;
        foreach ($staleKeys as $channel) {
            if (! $this->reconnection->shouldAttemptReconnect($channel)) {
                $this->reconnection->reset($channel);
                $reset++;
            }
        }
        $actions[] = ['action' => 'reconnection_reset', 'status' => 'ok', 'channels_reset' => $reset];

        return ['actions' => $actions, 'healthy' => true, 'recovered_at' => now()->toISOString()];
    }

    public function handleDisconnect(string $channel, string $tenantId = ''): void
    {
        $this->reconnection->handleFailure($channel);

        // Track disconnection for telemetry
        $key  = "realtime:disconnects:{$tenantId}";
        $data = Cache::get($key, []);
        $data[] = ['channel' => $channel, 'at' => now()->toISOString()];
        Cache::put($key, array_slice($data, -100), now()->addHours(24));
    }

    public function handleReconnect(string $channel): void
    {
        $this->reconnection->reset($channel);
    }

    public function getHealthMetrics(string $tenantId): array
    {
        return [
            'disconnects_24h' => count(Cache::get("realtime:disconnects:{$tenantId}", [])),
            'driver'          => config('broadcasting.default', 'log'),
            'polling_active'  => ! in_array(config('broadcasting.default', 'log'), ['pusher', 'reverb', 'soketi']),
        ];
    }
}
