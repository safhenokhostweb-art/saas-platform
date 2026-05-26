<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Logging;

use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;
use SaaSPlatform\CoreUtils\Telemetry\TelemetrySanitizer;

/**
 * Wraps log writes with mandatory tenant_id injection.
 * Prevents cross-tenant log queries by ensuring tenant scope is always present.
 */
class TenantAwareLogChannel
{
    public function __construct(
        private TelemetryContext   $context,
        private TelemetrySanitizer $sanitizer
    ) {}

    public function log(string $level, string $message, array $context = []): void
    {
        $tenantId = $this->context->getTenantId();

        $entry = array_merge($this->sanitizer->sanitize($context), [
            'tenant_id'   => $tenantId,
            'trace_id'    => $this->context->getTraceId(),
            'request_id'  => $this->context->getRequestId(),
            'module'      => $this->context->getModule(),
            'environment' => config('app.env', 'production'),
            'timestamp'   => now()->toIso8601String(),
        ]);

        $channel = $this->resolveChannel($tenantId);
        Log::channel($channel)->$level($message, $entry);
    }

    public function forTenant(int $tenantId): self
    {
        $clone = clone $this;
        return $clone;
    }

    private function resolveChannel(?int $tenantId): string
    {
        // All tenant logs go to the same channel; tenant_id field enables per-tenant filtering
        return config('saas-platform.telemetry.tenant_log_channel', 'daily');
    }
}
