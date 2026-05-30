<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\OTA;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\MobileRuntime\Versioning\MobileVersionPolicyManager;

class MobileVersionEnforcementService
{
    private const PREFIX = 'mobile:enforcement:';
    private const TTL    = 3600;

    public function __construct(
        private readonly MobileVersionPolicyManager $versionPolicy,
    ) {}

    public function enforce(string $appVersion, string $tenantId): array
    {
        if ($this->isBlocked($appVersion, $tenantId)) {
            return [
                'allowed' => false,
                'action'  => 'hard_update',
                'message' => 'This version is blocked. Please update the app immediately.',
            ];
        }

        if ($this->requiresMandatoryUpdate($appVersion, $tenantId)) {
            return [
                'allowed' => false,
                'action'  => 'hard_update',
                'message' => 'A mandatory update is required to continue using the app.',
            ];
        }

        if ($this->requiresSoftUpdate($appVersion, $tenantId)) {
            return [
                'allowed' => true,
                'action'  => 'soft_update',
                'message' => 'A new version is available. Please update for the best experience.',
            ];
        }

        return ['allowed' => true, 'action' => 'none', 'message' => ''];
    }

    public function isBlocked(string $appVersion, string $tenantId): bool
    {
        $blocked = $this->getBlockedVersions($tenantId);
        return in_array($appVersion, $blocked, true);
    }

    public function requiresMandatoryUpdate(string $appVersion, string $tenantId): bool
    {
        return $this->versionPolicy->isMandatory($appVersion, $tenantId);
    }

    public function requiresSoftUpdate(string $appVersion, string $tenantId): bool
    {
        return $this->versionPolicy->requiresUpdate($appVersion, $tenantId, 'stable');
    }

    public function getEnforcementPolicy(string $tenantId): array
    {
        return Cache::get(self::PREFIX . "policy:{$tenantId}", $this->getDefaultPolicy());
    }

    public function setEnforcementPolicy(string $tenantId, array $policy): void
    {
        Cache::put(self::PREFIX . "policy:{$tenantId}", array_merge($this->getDefaultPolicy(), $policy), self::TTL);
    }

    public function getBlockedVersions(string $tenantId): array
    {
        $policy = $this->getEnforcementPolicy($tenantId);
        return $policy['blocked_versions'] ?? [];
    }

    private function getDefaultPolicy(): array
    {
        return ['blocked_versions' => [], 'soft_update_threshold' => '1.0.0', 'hard_update_threshold' => '1.0.0'];
    }
}
