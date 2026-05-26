<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Security;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;
use SaaSPlatform\CoreUtils\Telemetry\TelemetrySanitizer;

/**
 * Central hub for all security telemetry.
 * ALL data passes through TelemetrySanitizer before emission.
 * Authorization headers, tokens, and license keys are never emitted.
 */
class SecurityTelemetryService
{
    public function __construct(
        private TelemetryManager   $telemetry,
        private TelemetryContext   $context,
        private TelemetrySanitizer $sanitizer,
        private AuthFailureTelemetry              $authFailure,
        private TenantBoundaryViolationTelemetry  $tenantBoundary,
        private SensitiveOperationAuditStream     $auditStream
    ) {}

    public function onAuthFailure(string $reason, array $context = []): void
    {
        $this->authFailure->record($reason, $this->sanitizer->sanitize($context));
    }

    public function onAuthSuccess(int $userId, string $guard = 'web'): void
    {
        $this->telemetry->info('security', 'security.auth.success', [
            'tenant_id' => $this->context->getTenantId(),
            'user_id'   => $userId,
            'guard'     => $guard,
        ]);
    }

    public function onTenantBoundaryViolation(int $attemptedTenantId, string $resource = ''): void
    {
        $this->tenantBoundary->record($attemptedTenantId, $resource);
    }

    public function onSensitiveOperation(string $operation, int $actorId, array $context = []): void
    {
        $this->auditStream->record($operation, $actorId, $this->sanitizer->sanitize($context));
    }

    public function onRateLimitExceeded(string $route): void
    {
        $this->telemetry->warning('security', 'security.rate_limit.exceeded', [
            'tenant_id' => $this->context->getTenantId(),
            'route'     => $route,
        ]);
    }

    public function onSuspiciousRequest(string $reason, array $context = []): void
    {
        $this->telemetry->warning('security', 'security.request.suspicious', array_merge(
            $this->context->toArray(),
            ['reason' => $reason],
            $this->sanitizer->sanitize($context)
        ));
    }
}
