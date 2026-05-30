<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Notifications;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\MobileDeviceRegistry;

class NotificationRoutingService
{
    private const PREFIX = 'mobile:notif_routing:';
    private const TTL    = 3600;

    public function __construct(
        private readonly MobileDeviceRegistry $deviceRegistry,
    ) {}

    public function route(string $tenantId, string $userId, array $notification): array
    {
        $devices  = $this->resolveTargetDevices($userId, $tenantId);
        $channels = $this->resolveChannels($userId, $tenantId, $notification['type'] ?? 'general');
        return [
            'devices'  => $devices,
            'channels' => $channels,
        ];
    }

    public function getRoutingRules(string $tenantId): array
    {
        return Cache::get(self::PREFIX . $tenantId, $this->getDefaultRouting());
    }

    public function setRoutingRule(string $tenantId, string $notificationType, array $rule): void
    {
        $rules = $this->getRoutingRules($tenantId);
        $rules[$notificationType] = $rule;
        Cache::put(self::PREFIX . $tenantId, $rules, self::TTL);
    }

    public function getDefaultRouting(): array
    {
        return [
            'default' => ['channels' => ['push', 'in_app'], 'priority' => 'normal'],
        ];
    }

    public function resolveTargetDevices(string $userId, string $tenantId): array
    {
        return $this->deviceRegistry->getDevicesForUser($userId, $tenantId);
    }

    public function shouldDeliverToDevice(string $deviceId, array $notification): bool
    {
        // Check DND, preferences, etc.
        return true;
    }

    public function filterByPreferences(array $devices, string $userId, array $notification): array
    {
        return array_values(array_filter(
            $devices,
            fn ($device) => $this->shouldDeliverToDevice($device['id'] ?? '', $notification)
        ));
    }

    public function resolveChannels(string $userId, string $tenantId, string $notificationType): array
    {
        $rules   = $this->getRoutingRules($tenantId);
        $rule    = $rules[$notificationType] ?? $rules['default'] ?? [];
        return $rule['channels'] ?? ['push', 'in_app'];
    }
}
