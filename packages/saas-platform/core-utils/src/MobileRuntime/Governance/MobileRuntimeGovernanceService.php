<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Governance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MobileRuntimeGovernanceService
{
    private const PREFIX = 'mobile:governance:';
    private const TTL    = 3600;

    public function __construct(
        private readonly MobileRuntimeRegistry $registry,
        private readonly MobileFeatureFlagService $flags,
        private readonly MobileRuntimeValidationService $validator,
    ) {}

    public function validateRuntime(string $tenantId): array
    {
        return $this->validator->validate($tenantId);
    }

    public function enforcePolicy(string $tenantId, string $policy, array $context = []): bool
    {
        $violations = $this->getPolicyViolations($tenantId);
        foreach ($violations as $v) {
            if ($v['policy'] === $policy) {
                Log::warning('mobile.governance.policy_blocked', [
                    'tenant_id' => $tenantId,
                    'policy'    => $policy,
                    'context'   => $context,
                ]);
                return false;
            }
        }
        return true;
    }

    public function getPolicyViolations(string $tenantId): array
    {
        return Cache::get(self::PREFIX . "violations:{$tenantId}", []);
    }

    public function recordViolation(
        string $tenantId,
        string $policy,
        string $severity,
        array $context = []
    ): void {
        $violations   = $this->getPolicyViolations($tenantId);
        $violations[] = [
            'policy'     => $policy,
            'severity'   => $severity,
            'context'    => $context,
            'recorded_at' => now()->toIso8601String(),
        ];
        // Keep last 100 violations per tenant
        if (count($violations) > 100) {
            $violations = array_slice($violations, -100);
        }
        Cache::put(self::PREFIX . "violations:{$tenantId}", $violations, self::TTL);
        Log::warning('mobile.governance.violation', [
            'tenant_id' => $tenantId,
            'policy'    => $policy,
            'severity'  => $severity,
        ]);
    }

    public function getGovernanceReport(string $tenantId): array
    {
        $validation = $this->validateRuntime($tenantId);
        $violations = $this->getPolicyViolations($tenantId);
        $isRegistered = $this->registry->isRegistered($tenantId);

        return [
            'tenant_id'        => $tenantId,
            'is_registered'    => $isRegistered,
            'validation'       => $validation,
            'violation_count'  => count($violations),
            'violations'       => $violations,
            'runtime_allowed'  => $this->isRuntimeAllowed($tenantId),
            'generated_at'     => now()->toIso8601String(),
        ];
    }

    public function isRuntimeAllowed(string $tenantId): bool
    {
        $violations = $this->getPolicyViolations($tenantId);
        $critical   = array_filter($violations, fn ($v) => $v['severity'] === 'critical');
        return count($critical) === 0;
    }

    public function clearViolations(string $tenantId): void
    {
        Cache::forget(self::PREFIX . "violations:{$tenantId}");
    }
}
