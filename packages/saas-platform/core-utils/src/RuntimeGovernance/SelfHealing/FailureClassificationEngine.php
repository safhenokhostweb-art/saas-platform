<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing;

class FailureClassificationEngine
{
    public const CLASS_TRANSIENT         = 'transient';
    public const CLASS_RESOURCE          = 'resource';
    public const CLASS_CONFIGURATION     = 'configuration';
    public const CLASS_DEPENDENCY        = 'dependency';
    public const CLASS_SECURITY          = 'security';
    public const CLASS_DATA_CORRUPTION   = 'data_corruption';
    public const CLASS_INFRASTRUCTURE    = 'infrastructure';

    private const CLASSIFICATION_RULES = [
        'queue.failed_count_high'   => self::CLASS_TRANSIENT,
        'queue.stall_detected'      => self::CLASS_DEPENDENCY,
        'health.db_failed'          => self::CLASS_INFRASTRUCTURE,
        'health.cache_failed'       => self::CLASS_INFRASTRUCTURE,
        'health.storage_failed'     => self::CLASS_INFRASTRUCTURE,
        'security.auth_burst'       => self::CLASS_SECURITY,
        'security.boundary_violation' => self::CLASS_SECURITY,
        'deployment.stale_lock'     => self::CLASS_TRANSIENT,
        'deployment.failure'        => self::CLASS_CONFIGURATION,
        'runtime.memory_pressure'   => self::CLASS_RESOURCE,
        'tenant.error_rate_high'    => self::CLASS_TRANSIENT,
    ];

    private const RECOVERY_STRATEGIES = [
        self::CLASS_TRANSIENT      => ['queue_restart', 'cache_rebuild', 'retry'],
        self::CLASS_RESOURCE       => ['cache_clear', 'worker_recycle', 'throttle'],
        self::CLASS_CONFIGURATION  => ['deployment_rollback_trigger', 'alert'],
        self::CLASS_DEPENDENCY     => ['circuit_open', 'fallback', 'retry_with_backoff'],
        self::CLASS_SECURITY       => ['tenant_isolation', 'deployment_freeze', 'alert'],
        self::CLASS_DATA_CORRUPTION => ['maintenance_mode', 'alert'],
        self::CLASS_INFRASTRUCTURE => ['health_check_retry', 'alert'],
    ];

    public function classify(string $failureType, array $context = []): string
    {
        $class = self::CLASSIFICATION_RULES[$failureType] ?? self::CLASS_TRANSIENT;

        // Override for severe context
        if (!empty($context['is_security_event'])) {
            return self::CLASS_SECURITY;
        }

        return $class;
    }

    public function recoverabilityScore(string $class): int
    {
        return match ($class) {
            self::CLASS_TRANSIENT      => 90,
            self::CLASS_RESOURCE       => 80,
            self::CLASS_DEPENDENCY     => 70,
            self::CLASS_CONFIGURATION  => 50,
            self::CLASS_INFRASTRUCTURE => 40,
            self::CLASS_SECURITY       => 20,
            self::CLASS_DATA_CORRUPTION => 10,
            default                    => 50,
        };
    }

    public function suggestedStrategies(string $class): array
    {
        return self::RECOVERY_STRATEGIES[$class] ?? ['alert'];
    }

    public function isAutoRecoverable(string $class): bool
    {
        return in_array($class, [
            self::CLASS_TRANSIENT,
            self::CLASS_RESOURCE,
            self::CLASS_DEPENDENCY,
        ]);
    }
}
