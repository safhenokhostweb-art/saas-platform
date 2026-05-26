<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Logging;

use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;
use SaaSPlatform\CoreUtils\Telemetry\TelemetrySanitizer;

/**
 * Logs security-relevant events.
 * ALL context passes through TelemetrySanitizer before writing.
 * Authorization headers, tokens, and license keys are never written to log.
 */
class SecurityEventLogger
{
    public function __construct(
        private TelemetryContext   $context,
        private TelemetrySanitizer $sanitizer
    ) {}

    public function authFailure(string $reason, array $context = []): void
    {
        $this->write('security.auth.failure', 'warning', array_merge(['reason' => $reason], $context));
    }

    public function authSuccess(string $action, array $context = []): void
    {
        $this->write('security.auth.success', 'info', array_merge(['action' => $action], $context));
    }

    public function tenantBoundaryViolation(int $attemptedTenantId, array $context = []): void
    {
        $this->write('security.tenant.boundary_violation', 'critical', array_merge([
            'attempted_tenant_id' => $attemptedTenantId,
            'actual_tenant_id'    => $this->context->getTenantId(),
        ], $context));
    }

    public function unauthorizedAccess(string $resource, array $context = []): void
    {
        $this->write('security.access.unauthorized', 'warning', array_merge([
            'resource' => $resource,
        ], $context));
    }

    public function sensitiveOperation(string $operation, array $context = []): void
    {
        $this->write('security.operation.sensitive', 'info', array_merge([
            'operation' => $operation,
        ], $context));
    }

    public function rateLimitExceeded(string $route, array $context = []): void
    {
        $this->write('security.rate_limit.exceeded', 'warning', array_merge([
            'route' => $route,
        ], $context));
    }

    private function write(string $event, string $level, array $context): void
    {
        // Sanitize BEFORE any write — mandatory for security events
        $safe = $this->sanitizer->sanitize($context);

        Log::$level($event, array_merge($this->context->toArray(), [
            'event'       => $event,
            'environment' => config('app.env', 'production'),
            'timestamp'   => now()->toIso8601String(),
            'context'     => $safe,
        ]));
    }
}
