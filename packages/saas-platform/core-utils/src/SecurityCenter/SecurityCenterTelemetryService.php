<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SecurityCenterTelemetryService
{
    private const EVENT_KEY  = 'security:telemetry:events';
    private const MAX_EVENTS = 1000;

    public function recordEvent(string $event, array $context = []): void
    {
        $entry = array_merge($context, [
            'event'      => $event,
            'recorded_at'=> now()->toIso8601String(),
            'uuid'       => uniqid('sec_evt_', true),
        ]);

        Log::channel('security')->info("SecurityCenter: {$event}", $entry);

        $events   = Cache::get(self::EVENT_KEY, []);
        $events[] = $entry;

        if (count($events) > self::MAX_EVENTS) {
            $events = array_slice($events, -self::MAX_EVENTS);
        }

        Cache::put(self::EVENT_KEY, $events, 86400 * 30);
    }

    public function getEvents(int $limit = 50, string $eventFilter = ''): array
    {
        $events = Cache::get(self::EVENT_KEY, []);
        $events = array_reverse($events);

        if ($eventFilter) {
            $events = array_filter($events, fn ($e) => str_contains($e['event'], $eventFilter));
        }

        return array_slice(array_values($events), 0, $limit);
    }

    public function getSecurityTimeline(string $tenantId = 'global', int $limit = 20): array
    {
        $events = $this->getEvents(100);
        $filtered = array_filter($events, function ($e) use ($tenantId) {
            if ($tenantId === 'global') return true;
            return ($e['tenant_id'] ?? 'global') === $tenantId;
        });

        return array_slice(array_values($filtered), 0, $limit);
    }

    public function getMetrics(): array
    {
        $events = Cache::get(self::EVENT_KEY, []);
        $byType = [];

        foreach ($events as $event) {
            $type = $event['event'] ?? 'unknown';
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        return [
            'total_events'       => count($events),
            'by_type'            => $byType,
            'patches_installed'  => $byType['patch_installed'] ?? 0,
            'patches_rolled_back'=> $byType['patch_rolled_back'] ?? 0,
            'integrity_checks'   => $byType['integrity_verified'] ?? 0,
            'security_alerts'    => $byType['security_alert_raised'] ?? 0,
        ];
    }
}
