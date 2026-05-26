<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * TASK 7 — Production Logging & Monitoring
 *
 * Centralized monitoring and structured logging for the Forms module.
 * Tracks all critical events:
 * - Lifecycle events (install, uninstall, activate, deactivate, update, rollback)
 * - Failed update events
 * - Entitlement denial events
 * - Override conflict events
 * - Drift detection alerts
 *
 * All events are logged with structured context for log aggregation tools
 * (e.g., ELK, Datadog, CloudWatch, Papertrail).
 *
 * This service provides a SINGLE source of truth for Forms module observability.
 * No controller, service, or middleware should log Forms events directly —
 * they must use this service.
 */
class FormsMonitoringService
{
    /**
     * Cache prefix for monitoring metrics.
     */
    protected const CACHE_PREFIX = 'forms_monitor_';

    /**
     * Cache TTL for metric counters (in minutes).
     */
    protected const METRICS_TTL_MINUTES = 60;

    /**
     * Log a lifecycle event.
     *
     * @param string $event install|uninstall|activate|deactivate|update|rollback
     * @param array<string, mixed> $context Additional context data
     */
    public function logLifecycle(string $event, array $context = []): void
    {
        $entry = array_merge([
            'event' => "forms.lifecycle.{$event}",
            'module' => 'forms',
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
        ], $context);

        Log::info("forms.lifecycle.{$event}", $entry);

        $this->incrementMetric("lifecycle.{$event}");
    }

    /**
     * Log a failed update event with full diagnostic context.
     *
     * @param string $fromVersion The version being updated from
     * @param string $toVersion The version being updated to
     * @param string $error The error message
     * @param array<string, mixed> $diagnostic Additional diagnostic data
     */
    public function logFailedUpdate(string $fromVersion, string $toVersion, string $error, array $diagnostic = []): void
    {
        $entry = array_merge([
            'event' => 'forms.update.failed',
            'module' => 'forms',
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'error' => $error,
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'host' => gethostname(),
            'php_version' => PHP_VERSION,
        ], $diagnostic);

        Log::error('forms.update.failed', $entry);

        $this->incrementMetric('update.failed');
        $this->setAlert('update_failed', [
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'error' => $error,
            'detected_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log an entitlement denial event.
     *
     * @param int $siteId The tenant site ID
     * @param string $reason The denial reason
     * @param string $context The context where denial occurred (e.g., route, middleware, controller)
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function logEntitlementDenial(int $siteId, string $reason, string $context, array $metadata = []): void
    {
        $entry = array_merge([
            'event' => 'forms.entitlement.denied',
            'module' => 'forms',
            'site_id' => $siteId,
            'reason' => $reason,
            'context' => $context,
            'timestamp' => now()->toIso8601String(),
            'ip' => app()->runningInConsole() ? null : request()?->ip(),
            'user_agent' => app()->runningInConsole() ? null : request()?->userAgent(),
            'url' => app()->runningInConsole() ? null : request()?->fullUrl(),
            'method' => app()->runningInConsole() ? null : request()?->method(),
        ], $metadata);

        Log::warning('forms.entitlement.denied', $entry);

        $this->incrementMetric('entitlement.denied');
    }

    /**
     * Log an override conflict event.
     *
     * @param string $file The override file path
     * @param string $type orphaned_override|modified_override|removed_section|hardcoded_layout
     * @param string $detail Description of the conflict
     */
    public function logOverrideConflict(string $file, string $type, string $detail): void
    {
        $entry = [
            'event' => 'forms.override.conflict',
            'module' => 'forms',
            'file' => $file,
            'conflict_type' => $type,
            'detail' => $detail,
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
        ];

        Log::warning('forms.override.conflict', $entry);

        $this->incrementMetric("override.conflict.{$type}");
    }

    /**
     * Log a drift detection alert.
     * Drift occurs when published overrides differ from package authority views.
     *
     * @param int $driftedFileCount Number of files that have drifted
     * @param array<int, array{file: string, type: string, detail: string}> $driftedFiles List of drifted files
     */
    public function logDriftAlert(int $driftedFileCount, array $driftedFiles = []): void
    {
        $entry = [
            'event' => 'forms.drift.detected',
            'module' => 'forms',
            'drifted_file_count' => $driftedFileCount,
            'drifted_files' => $driftedFiles,
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
        ];

        Log::warning('forms.drift.detected', $entry);

        $this->incrementMetric('drift.detected');
        $this->setAlert('drift_detected', [
            'file_count' => $driftedFileCount,
            'detected_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log a runtime safety violation.
     *
     * @param string $check The safety check that failed
     * @param string $detail Description of the violation
     */
    public function logSafetyViolation(string $check, string $detail): void
    {
        $entry = [
            'event' => 'forms.safety.violation',
            'module' => 'forms',
            'check' => $check,
            'detail' => $detail,
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
        ];

        Log::error('forms.safety.violation', $entry);

        $this->incrementMetric("safety.violation.{$check}");
        $this->setAlert('safety_violation', [
            'check' => $check,
            'detail' => $detail,
            'detected_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log a licensing error.
     *
     * @param string $operation The operation that failed (checkEntitlement, isAllowed, etc.)
     * @param string $error The error message
     * @param array<string, mixed> $context Additional context
     */
    public function logLicensingError(string $operation, string $error, array $context = []): void
    {
        $entry = array_merge([
            'event' => 'forms.licensing.error',
            'module' => 'forms',
            'operation' => $operation,
            'error' => $error,
            'timestamp' => now()->toIso8601String(),
        ], $context);

        Log::error('forms.licensing.error', $entry);

        $this->incrementMetric('licensing.error');
    }

    /**
     * Log a gate authorization event.
     *
     * @param bool $granted Whether access was granted
     * @param string $reason The reason (null if granted)
     * @param string $context The context (route, middleware, controller)
     */
    public function logGateEvent(bool $granted, ?string $reason, string $context): void
    {
        $entry = [
            'event' => $granted ? 'forms.gate.granted' : 'forms.gate.denied',
            'module' => 'forms',
            'granted' => $granted,
            'reason' => $reason,
            'context' => $context,
            'timestamp' => now()->toIso8601String(),
            'url' => app()->runningInConsole() ? null : request()?->fullUrl(),
            'method' => app()->runningInConsole() ? null : request()?->method(),
        ];

        if ($granted) {
            Log::info('forms.gate.granted', $entry);
        } else {
            Log::warning('forms.gate.denied', $entry);
        }

        $this->incrementMetric($granted ? 'gate.granted' : 'gate.denied');
    }

    /**
     * Get monitoring summary metrics.
     *
     * @return array<string, mixed>
     */
    public function getMetricsSummary(): array
    {
        $metrics = Cache::get(self::CACHE_PREFIX . 'metrics', []);

        return [
            'metrics' => $metrics,
            'alerts' => Cache::get(self::CACHE_PREFIX . 'alerts', []),
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * Clear all monitoring metrics and alerts.
     */
    public function clearMetrics(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'metrics');
        Cache::forget(self::CACHE_PREFIX . 'alerts');

        Log::info('forms.monitoring.metrics_cleared', [
            'event' => 'forms.monitoring.metrics_cleared',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Increment a metric counter in cache.
     */
    protected function incrementMetric(string $key): void
    {
        try {
            $metrics = Cache::get(self::CACHE_PREFIX . 'metrics', []);
            $metrics[$key] = ($metrics[$key] ?? 0) + 1;
            Cache::put(self::CACHE_PREFIX . 'metrics', $metrics, now()->addMinutes(self::METRICS_TTL_MINUTES));
        } catch (\Throwable $e) {
            // Cache failures should never break monitoring
            Log::warning('forms.monitoring.cache_write_failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Set an active alert in cache.
     */
    protected function setAlert(string $type, array $data): void
    {
        try {
            $alerts = Cache::get(self::CACHE_PREFIX . 'alerts', []);
            $alerts[$type] = $data;
            Cache::put(self::CACHE_PREFIX . 'alerts', $alerts, now()->addMinutes(self::METRICS_TTL_MINUTES));
        } catch (\Throwable $e) {
            // Cache failures should never break monitoring
            Log::warning('forms.monitoring.alert_cache_failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
