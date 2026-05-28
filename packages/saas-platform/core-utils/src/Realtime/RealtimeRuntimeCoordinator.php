<?php

namespace SaaSPlatform\CoreUtils\Realtime;

class RealtimeRuntimeCoordinator
{
    public function __construct(
        private readonly WebSocketAbstractionLayer $wsLayer,
        private readonly GracefulPollingFallback   $polling,
        private readonly HeartbeatMonitor          $heartbeat,
        private readonly TenantChannelManager      $channelManager,
        private readonly ReconnectionEngine        $reconnection,
    ) {}

    public function broadcastTicketUpdate(int $ticketId, string $tenantId, array $data): bool
    {
        return $this->wsLayer->publishToTenant($tenantId, 'ticket.updated', array_merge($data, [
            'ticket_id' => $ticketId,
        ]));
    }

    public function broadcastFormActivity(int $formId, string $tenantId, array $data): bool
    {
        return $this->wsLayer->publishToTenant($tenantId, 'form.activity', array_merge($data, [
            'form_id' => $formId,
        ]));
    }

    public function broadcastAiResult(string $tenantId, int $userId, string $operation, array $result): bool
    {
        return $this->wsLayer->publishToUser($userId, $tenantId, 'ai.result', [
            'operation' => $operation,
            'result'    => $result,
        ]);
    }

    public function broadcastNotification(string $tenantId, int $userId, array $notification): bool
    {
        return $this->wsLayer->publishToUser($userId, $tenantId, 'notification.new', $notification);
    }

    public function broadcastGovernanceAlert(string $tenantId, string $type, array $payload): bool
    {
        return $this->wsLayer->publishToTenant($tenantId, 'governance.alert', array_merge($payload, [
            'alert_type' => $type,
        ]));
    }

    public function broadcastDeploymentStatus(string $status, array $payload = []): bool
    {
        // Platform-wide deployment broadcast
        return $this->wsLayer->publish('platform.deployment', 'deployment.status', array_merge($payload, [
            'status' => $status,
        ]));
    }

    public function pollForTenant(string $tenantId, ?string $since = null): array
    {
        return $this->polling->pollForTenant($tenantId, $since);
    }

    public function status(): array
    {
        return [
            'transport'    => $this->wsLayer->getTransport(),
            'websocket'    => $this->wsLayer->isWebSocketAvailable(),
            'fallback'     => 'polling',
            'health'       => $this->wsLayer->getConnectionHealth(),
        ];
    }
}
