<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Degradation;

/**
 * Defines what IS and IS NOT allowed at each degradation level.
 * Billing and admin access are unconditionally preserved at all levels.
 * Cross-tenant isolation is never relaxed.
 */
class TenantIsolationPolicy
{
    private const LEVEL_PERMISSIONS = [
        0 => ['read', 'write', 'reports', 'notifications', 'telemetry', 'exports'],
        1 => ['read', 'write', 'reports', 'notifications', 'exports'],
        2 => ['read', 'write', 'notifications', 'exports'],
        3 => ['read', 'write'],
        4 => ['read'],
        5 => [],
    ];

    // Unconditionally preserved at ALL levels (never degraded)
    private const PRESERVED_ALWAYS = ['billing', 'admin', 'health_bypass', 'support'];

    public function isPermitted(int $tenantId, string $capability, int $degradationLevel): bool
    {
        if (in_array($capability, self::PRESERVED_ALWAYS)) {
            return true;
        }

        $allowed = self::LEVEL_PERMISSIONS[$degradationLevel] ?? [];
        return in_array($capability, $allowed);
    }

    public function permittedCapabilities(int $degradationLevel): array
    {
        $level    = min(5, max(0, $degradationLevel));
        $standard = self::LEVEL_PERMISSIONS[$level] ?? [];

        return array_merge($standard, self::PRESERVED_ALWAYS);
    }

    public function restrictedCapabilities(int $degradationLevel): array
    {
        $allCapabilities = array_unique(array_merge(...array_values(self::LEVEL_PERMISSIONS)));
        $permitted       = $this->permittedCapabilities($degradationLevel);

        return array_values(array_diff($allCapabilities, $permitted));
    }

    public function isReversible(int $fromLevel, int $toLevel): bool
    {
        // Any degradation is reversible — isolation (level 5) can be lifted by recovery
        return $toLevel < $fromLevel;
    }

    public function describe(int $level): string
    {
        return match ($level) {
            0 => 'Tenant operating normally — all capabilities available',
            1 => 'Reduced telemetry verbosity — writes, reads, and reports available',
            2 => 'Heavy reports disabled — writes and reads available',
            3 => 'Notifications queue-only — read and write available',
            4 => 'Read-only mode — no writes permitted',
            5 => 'Tenant isolated — no standard operations permitted; billing and admin preserved',
            default => 'Unknown degradation level',
        };
    }
}
