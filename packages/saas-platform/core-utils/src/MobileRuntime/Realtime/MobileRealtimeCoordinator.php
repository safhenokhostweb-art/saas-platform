<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Realtime;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class MobileRealtimeCoordinator
{
    private const PREFIX = 'mobile:rt:';

    public function getConnectionConfig(string $tenantId): array
    {
        return [
            'host'      => Config::get('broadcasting.connections.reverb.options.host', '127.0.0.1'),
            'port'      => Config::get('broadcasting.connections.reverb.options.port', 8080),
            'key'       => Config::get('broadcasting.connections.reverb.key', ''),
            'cluster'   => Config::get('broadcasting.connections.reverb.options.cluster', 'mt1'),
            'transport' => $this->getPreferredTransport(),
            'tls'       => Config::get('broadcasting.connections.reverb.options.useTLS', false),
            'tenant_id' => $tenantId,
            'channels'  => [
                'presence' => "tenant.{$tenantId}",
                'private'  => 'mobile.user.{userId}',
            ],
        ];
    }

    public function broadcastToTenant(string $tenantId, string $event, array $data): void
    {
        try {
            Broadcast::channel("tenant.{$tenantId}", fn () => true);
            event(new \Illuminate\Broadcasting\PendingBroadcast(
                app(\Illuminate\Contracts\Broadcasting\Factory::class),
                new class ($event, $data, $tenantId) implements \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow {
                    public function __construct(
                        private string $event,
                        private array $data,
                        private string $tenantId,
                    ) {}
                    public function broadcastOn(): array { return [new \Illuminate\Broadcasting\PresenceChannel("tenant.{$this->tenantId}")]; }
                    public function broadcastAs(): string { return $this->event; }
                    public function broadcastWith(): array { return $this->data; }
                }
            ));
        } catch (\Throwable $e) {
            Log::warning('mobile.rt.broadcast_failed', ['tenant_id' => $tenantId, 'event' => $event, 'error' => $e->getMessage()]);
        }
    }

    public function broadcastToUser(string $tenantId, int|string $userId, string $event, array $data): void
    {
        try {
            Broadcast::channel("mobile.user.{$userId}", fn () => true);
        } catch (\Throwable $e) {
            Log::warning('mobile.rt.user_broadcast_failed', ['user_id' => $userId, 'event' => $event]);
        }
    }

    public function broadcastToConversation(string $conversationId, string $event, array $data): void
    {
        $count = Cache::increment(self::PREFIX . "bc_count:{$conversationId}");
        Log::debug('mobile.rt.conversation_broadcast', [
            'conversation_id' => $conversationId,
            'event'           => $event,
            'broadcast_count' => $count,
        ]);
    }

    public function getActiveConnections(string $tenantId): int
    {
        return (int) Cache::get(self::PREFIX . "connections:{$tenantId}", 0);
    }

    public function validateTransport(): bool
    {
        $key = Config::get('broadcasting.connections.reverb.key', '');
        return ! empty($key);
    }

    public function getRealtimeStats(string $tenantId): array
    {
        return [
            'active_connections' => $this->getActiveConnections($tenantId),
            'transport'          => $this->getPreferredTransport(),
            'reverb_configured'  => $this->validateTransport(),
            'tenant_id'          => $tenantId,
        ];
    }

    private function getPreferredTransport(): string
    {
        return $this->validateTransport() ? 'websocket' : 'polling';
    }
}
