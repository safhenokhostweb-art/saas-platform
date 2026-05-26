<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Incident;

use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceContext;

class IncidentSeverityClassifier
{
    public const SEV_INFO               = 'INFO';
    public const SEV_WARNING            = 'WARNING';
    public const SEV_DEGRADED           = 'DEGRADED';
    public const SEV_CRITICAL           = 'CRITICAL';
    public const SEV_PLATFORM_EMERGENCY = 'PLATFORM_EMERGENCY';

    private const ANOMALY_SEVERITY_MAP = [
        'QUEUE_FAILED_JOBS_HIGH'      => self::SEV_WARNING,
        'QUEUE_BACKLOG_HIGH'          => self::SEV_WARNING,
        'RUNTIME_MEMORY_HIGH'         => self::SEV_DEGRADED,
        'DEPLOYMENT_FAILURES_RECENT'  => self::SEV_CRITICAL,
        'DEPLOYMENT_STALE_LOCK'       => self::SEV_CRITICAL,
        'TENANT_ISOLATION_VIOLATIONS' => self::SEV_PLATFORM_EMERGENCY,
        'SECURITY_AUTH_FAILURE_BURST' => self::SEV_CRITICAL,
    ];

    public function classify(string $incidentType, array $context = []): string
    {
        // Map anomaly codes to severities
        if (isset(self::ANOMALY_SEVERITY_MAP[$incidentType])) {
            return self::ANOMALY_SEVERITY_MAP[$incidentType];
        }

        // Context-based classification
        if (!empty($context['is_security'])) {
            return self::SEV_CRITICAL;
        }

        if (!empty($context['tenant_isolation_breached'])) {
            return self::SEV_PLATFORM_EMERGENCY;
        }

        if (!empty($context['multiple_tenants_affected']) && $context['multiple_tenants_affected'] > 5) {
            return self::SEV_CRITICAL;
        }

        return self::SEV_WARNING;
    }

    public function platformState(string $severity): string
    {
        return match ($severity) {
            self::SEV_INFO               => GovernanceContext::STATE_HEALTHY,
            self::SEV_WARNING            => GovernanceContext::STATE_HEALTHY,
            self::SEV_DEGRADED           => GovernanceContext::STATE_DEGRADED,
            self::SEV_CRITICAL           => GovernanceContext::STATE_CRITICAL,
            self::SEV_PLATFORM_EMERGENCY => GovernanceContext::STATE_EMERGENCY,
            default                      => GovernanceContext::STATE_DEGRADED,
        };
    }

    public function requiresOperatorIntervention(string $severity): bool
    {
        return in_array($severity, [self::SEV_CRITICAL, self::SEV_PLATFORM_EMERGENCY]);
    }

    public function isAutoResolvable(string $severity): bool
    {
        return in_array($severity, [self::SEV_INFO, self::SEV_WARNING, self::SEV_DEGRADED]);
    }

    public function numericScore(string $severity): int
    {
        return match ($severity) {
            self::SEV_INFO               => 1,
            self::SEV_WARNING            => 2,
            self::SEV_DEGRADED           => 3,
            self::SEV_CRITICAL           => 4,
            self::SEV_PLATFORM_EMERGENCY => 5,
            default                      => 2,
        };
    }
}
