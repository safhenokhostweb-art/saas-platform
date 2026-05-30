<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Notifications;

use Illuminate\Support\Facades\Cache;

class NotificationPriorityEngine
{
    public const CRITICAL = 'critical';
    public const HIGH     = 'high';
    public const NORMAL   = 'normal';
    public const LOW      = 'low';

    private const PREFIX = 'mobile:notif_priority:';
    private const TTL    = 3600;

    private array $defaultRules = [
        'security_alert'       => self::CRITICAL,
        'mandatory_update'     => self::CRITICAL,
        'ticket_assigned'      => self::HIGH,
        'new_message'          => self::HIGH,
        'mention'              => self::HIGH,
        'ticket_reply'         => self::NORMAL,
        'system_announcement'  => self::NORMAL,
        'digest'               => self::LOW,
        'marketing'            => self::LOW,
    ];

    public function getPriority(array $notification, string $tenantId, string $role): string
    {
        $type    = $notification['type'] ?? 'general';
        $rules   = $this->getRules($tenantId);
        return $rules[$type] ?? self::NORMAL;
    }

    public function shouldWakeDevice(array $notification): bool
    {
        $priority = $notification['priority'] ?? self::NORMAL;
        return in_array($priority, [self::CRITICAL, self::HIGH], true);
    }

    public function getPlatformPriority(string $priority, string $platform): string
    {
        return match ($platform) {
            'fcm'  => in_array($priority, [self::CRITICAL, self::HIGH], true) ? 'high' : 'normal',
            'apns' => match ($priority) {
                self::CRITICAL => '10',
                self::HIGH     => '10',
                default        => '5',
            },
            default => 'normal',
        };
    }

    public function applyPriorityRules(array $notification, string $tenantId): array
    {
        $priority = $this->getPriority($notification, $tenantId, 'any');
        return array_merge($notification, ['priority' => $priority, 'wake_device' => $this->shouldWakeDevice(array_merge($notification, ['priority' => $priority]))]);
    }

    public function getRules(string $tenantId): array
    {
        $tenantRules = Cache::get(self::PREFIX . $tenantId, []);
        return array_merge($this->defaultRules, $tenantRules);
    }

    public function setRule(string $tenantId, string $notificationType, array $rule): void
    {
        $rules = $this->getRules($tenantId);
        $rules[$notificationType] = $rule['priority'] ?? self::NORMAL;
        Cache::put(self::PREFIX . $tenantId, $rules, self::TTL);
    }
}
