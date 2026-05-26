<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Security;

use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;

/**
 * Immutable audit stream for sensitive operations.
 * Writes to telemetry_events (if available) and log — never to a mutable store.
 * Sensitive data has already been sanitized before reaching this class.
 */
class SensitiveOperationAuditStream
{
    private const AUDITED_OPERATIONS = [
        'tenant.delete', 'tenant.transfer', 'tenant.disable',
        'user.permission.grant', 'user.permission.revoke',
        'license.assign', 'license.revoke',
        'deployment.lock', 'deployment.unlock.force',
        'admin.impersonate',
    ];

    public function __construct(
        private TelemetryManager $telemetry,
        private TelemetryContext $context
    ) {}

    public function record(string $operation, int $actorId, array $safeContext = []): void
    {
        $isAudited = in_array($operation, self::AUDITED_OPERATIONS) ||
                     str_starts_with($operation, 'admin.');

        $severity = $isAudited ? TelemetryManager::SEVERITY_WARNING : TelemetryManager::SEVERITY_INFO;

        $this->telemetry->emit('security', $severity, 'security.operation.audit', array_merge([
            'operation'  => $operation,
            'actor_id'   => $actorId,
            'tenant_id'  => $this->context->getTenantId(),
            'audited'    => $isAudited,
        ], $safeContext));
    }

    public function getAuditedOperations(): array
    {
        return self::AUDITED_OPERATIONS;
    }

    public function queryAuditLog(array $filters = [], int $limit = 50): array
    {
        return $this->telemetry->query(array_merge($filters, [
            'module' => 'security',
        ]), $limit);
    }
}
