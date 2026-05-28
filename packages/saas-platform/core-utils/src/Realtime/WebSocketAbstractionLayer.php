<?php

namespace SaaSPlatform\CoreUtils\Realtime;

use Illuminate\Support\Facades\Cache;

class WebSocketAbstractionLayer
{
    private string $transport = 'polling'; // polling | websocket | sse

    public function __construct(
        private readonly ConnectionHealthMonitor $healthMonitor,
        private readonly GracefulPollingFallback $pollingFallback,
        private readonly ReconnectionEngine      $reconnectionEngine,
    ) {}

    public function publish(string $channel, string $event, array $data, string $tenantId = ''): bool
    {
        $normalized = $this->normalizeEvent($event, $data, $tenantId);

        if ($this->isWebSocketAvailable()) {
            return $this->publishViaWebSocket($channel, $normalized);
        }

        return $this->pollingFallback->queue($channel, $normalized);
    }

    public function publishToTenant(string $tenantId, string $event, array $data): bool
    {
        $channel = "tenant.{$tenantId}";
        return $this->publish($channel, $event, $data, $tenantId);
    }

    public function publishToUser(int $userId, string $tenantId, string $event, array $data): bool
    {
        $channel = "user.{$tenantId}.{$userId}";
        return $this->publish($channel, $event, $data, $tenantId);
    }

    public function getTransport(): string
    {
        return $this->transport;
    }

    public function isWebSocketAvailable(): bool
    {
        $driver = config('broadcasting.default', 'log');
        return in_array($driver, ['pusher', 'reverb', 'soketi']);
    }

    public function getConnectionHealth(): array
    {
        return $this->healthMonitor->getTransportCapabilities();
    }

    private function normalizeEvent(string $event, array $data, string $tenantId): array
    {
        return [
            'event'     => $event,
            'data'      => $data,
            'tenant_id' => $tenantId,
            'timestamp' => now()->toISOString(),
            'id'        => \Illuminate\Support\Str::uuid()->toString(),
        ];
    }

    private function publishViaWebSocket(string $channel, array $normalized): bool
    {
        try {
            event(new \SaaSPlatform\CoreUtils\Realtime\Events\RealtimeEvent($channel, $normalized));
            return true;
        } catch (\Throwable $e) {
            $this->reconnectionEngine->handleFailure($channel);
            return $this->pollingFallback->queue($channel, $normalized);
        }
    }
}
