<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Logging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;

class RuntimeDiagnosticLogger
{
    public function __construct(private TelemetryContext $context) {}

    public function logMemoryUsage(string $label = 'snapshot'): void
    {
        Log::debug('runtime.memory.' . $label, array_merge($this->context->toArray(), [
            'memory_mb'      => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'php_version'    => PHP_VERSION,
            'sapi'           => PHP_SAPI,
        ]));
    }

    public function logSlowQuery(string $sql, float $durationMs, array $bindings = []): void
    {
        Log::warning('runtime.slow_query', array_merge($this->context->toArray(), [
            'duration_ms' => $durationMs,
            'sql'         => $sql,
            // Bindings intentionally excluded — may contain sensitive values
        ]));
    }

    public function logBootTime(float $bootMs): void
    {
        Log::info('runtime.boot_time', array_merge($this->context->toArray(), [
            'boot_ms'     => $bootMs,
            'php_version' => PHP_VERSION,
            'environment' => config('app.env'),
        ]));
    }

    public function logDbConnectionCount(): void
    {
        try {
            $count = count(DB::getConnections());
            Log::debug('runtime.db_connections', array_merge($this->context->toArray(), [
                'connection_count' => $count,
            ]));
        } catch (\Throwable) {
            // Non-critical
        }
    }

    public function snapshot(): array
    {
        return [
            'memory_mb'      => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'php_version'    => PHP_VERSION,
            'sapi'           => PHP_SAPI,
            'hostname'       => gethostname() ?: 'unknown',
            'pid'            => getmypid(),
        ];
    }
}
