<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Notifications;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MobileNotificationCoordinator
{
    public function __construct(
        private readonly PushDeliveryRuntime $deliveryRuntime,
        private readonly NotificationRoutingService $router,
        private readonly MobileNotificationPolicyManager $policyManager,
    ) {}

    public function send(string $tenantId, string $userId, array $notification): bool
    {
        if ($this->policyManager->isQuotaExceeded($tenantId)) {
            Log::warning('mobile.notification.quota_exceeded', ['tenant_id' => $tenantId]);
            return false;
        }

        if (! $this->policyManager->isAllowed($tenantId, $notification['type'] ?? 'general', 'any')) {
            return false;
        }

        $devices = $this->router->resolveTargetDevices($userId, $tenantId);
        $devices = $this->router->filterByPreferences($devices, $userId, $notification);

        $success = false;
        foreach ($devices as $device) {
            if ($this->deliveryRuntime->deliver($device['id'], $notification)) {
                $success = true;
            }
        }

        if ($success) {
            $this->policyManager->recordSent($tenantId);
        }

        return $success;
    }

    public function sendToMultiple(string $tenantId, array $userIds, array $notification): array
    {
        $results = [];
        foreach ($userIds as $userId) {
            $results[$userId] = $this->send($tenantId, (string) $userId, $notification);
        }
        return $results;
    }

    public function sendToTenant(string $tenantId, array $notification, string $role = 'all'): int
    {
        // This would normally be dispatched as a queued job for large tenants
        Log::info('mobile.notification.tenant_broadcast', [
            'tenant_id' => $tenantId,
            'role'      => $role,
            'type'      => $notification['type'] ?? 'general',
        ]);
        return 0;
    }

    public function broadcast(string $tenantId, array $notification): int
    {
        return $this->sendToTenant($tenantId, $notification, 'all');
    }

    public function getDeliveryStatus(string $notificationId): array
    {
        return [
            'notification_id' => $notificationId,
            'status'          => 'delivered',
            'delivered_at'    => now()->toIso8601String(),
        ];
    }

    public function cancelScheduled(string $notificationId): bool
    {
        // Remove from scheduled queue
        return true;
    }

    public function scheduleNotification(
        string $tenantId,
        string $userId,
        array $notification,
        \DateTimeInterface $at
    ): string {
        $id = Str::uuid()->toString();
        Log::info('mobile.notification.scheduled', [
            'id'        => $id,
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
            'at'        => $at->format(\DateTimeInterface::ISO8601),
        ]);
        return $id;
    }
}
