<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Telemetry;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MobileRuntimeTelemetryService
{
    private const PREFIX = 'mobile:telemetry:events:';
    private const TTL    = 3600;

    public function record(string $event, string $tenantId, array $data = []): void
    {
        $entry = array_merge($data, [
            'event'      => $event,
            'tenant_id'  => $tenantId,
            'recorded_at' => now()->toIso8601String(),
        ]);
        $this->appendEvent($tenantId, $entry);
        Cache::increment(self::PREFIX . "count:{$tenantId}:{$event}");
    }

    public function recordError(string $error, string $tenantId, array $context = []): void
    {
        $this->record('error', $tenantId, array_merge($context, ['error' => $error]));
        Cache::increment(self::PREFIX . "errors:{$tenantId}");
        Log::warning('mobile.runtime.error', array_merge(['tenant_id' => $tenantId, 'error' => $error], $context));
    }

    public function recordCrash(string $tenantId, string $userId, string $deviceId, array $crashData): void
    {
        $this->record('crash', $tenantId, array_merge($crashData, [
            'user_id'   => $userId,
            'device_id' => $deviceId,
        ]));
        Log::error('mobile.crash', array_merge($crashData, ['tenant_id' => $tenantId, 'user_id' => $userId]));
    }

    public function recordPerformance(string $tenantId, string $metric, float $value, array $tags = []): void
    {
        $this->record('performance', $tenantId, array_merge($tags, ['metric' => $metric, 'value' => $value]));
    }

    public function getMetrics(string $tenantId, int $minutes = 60): array
    {
        $events = Cache::get(self::PREFIX . $tenantId, []);
        $cutoff = now()->subMinutes($minutes);
        return array_values(array_filter($events, fn ($e) => isset($e['recorded_at']) && $e['recorded_at'] >= $cutoff->toIso8601String()));
    }

    public function getErrorRate(string $tenantId, int $minutes = 5): float
    {
        $errors = (int) Cache::get(self::PREFIX . "errors:{$tenantId}", 0);
        $total  = count($this->getMetrics($tenantId, $minutes));
        return $total > 0 ? round($errors / $total, 4) : 0.0;
    }

    public function flush(string $tenantId): void
    {
        Cache::forget(self::PREFIX . $tenantId);
        Cache::forget(self::PREFIX . "errors:{$tenantId}");
    }

    private function appendEvent(string $tenantId, array $event): void
    {
        $events   = Cache::get(self::PREFIX . $tenantId, []);
        $events[] = $event;
        if (count($events) > 1000) {
            $events = array_slice($events, -1000);
        }
        Cache::put(self::PREFIX . $tenantId, $events, self::TTL);
    }
}
