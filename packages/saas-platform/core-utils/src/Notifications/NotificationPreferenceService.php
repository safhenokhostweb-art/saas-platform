<?php

namespace SaaSPlatform\CoreUtils\Notifications;

use Illuminate\Support\Facades\Cache;

class NotificationPreferenceService
{
    public function getFor(string $tenantId, int $userId): array
    {
        $key = "notif:prefs:{$tenantId}:{$userId}";
        return Cache::get($key, $this->defaults());
    }

    public function update(string $tenantId, int $userId, array $prefs): void
    {
        $key     = "notif:prefs:{$tenantId}:{$userId}";
        $current = $this->getFor($tenantId, $userId);
        Cache::put($key, array_merge($current, $prefs), now()->addDays(365));
    }

    public function isChannelEnabled(string $tenantId, int $userId, string $channel): bool
    {
        $prefs = $this->getFor($tenantId, $userId);
        return $prefs['channels'][$channel] ?? true;
    }

    public function isInQuietHours(string $tenantId, int $userId): bool
    {
        $prefs = $this->getFor($tenantId, $userId);
        if (! ($prefs['quiet_hours']['enabled'] ?? false)) {
            return false;
        }
        $from = $prefs['quiet_hours']['from'] ?? '22:00';
        $to   = $prefs['quiet_hours']['to']   ?? '08:00';
        $now  = now()->format('H:i');

        // Handle overnight ranges
        if ($from > $to) {
            return $now >= $from || $now < $to;
        }
        return $now >= $from && $now < $to;
    }

    public function getDigestMode(string $tenantId, int $userId): string
    {
        return $this->getFor($tenantId, $userId)['digest_mode'] ?? 'off';
    }

    private function defaults(): array
    {
        return [
            'channels'    => ['in_app' => true, 'email' => true, 'sms' => false],
            'quiet_hours' => ['enabled' => false, 'from' => '22:00', 'to' => '08:00'],
            'digest_mode' => 'off', // off | hourly | daily
            'severity_min' => 'info', // info | warning | critical
        ];
    }
}
