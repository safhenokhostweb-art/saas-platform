<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance;

use Illuminate\Support\Facades\Cache;

class CapabilityGrantManager
{
    private const GRANT_TTL    = 86400; // 24h
    private const GRANT_PREFIX = 'plugin.capability.grant';

    public function __construct(
        private ModuleCapabilityRegistry $registry
    ) {}

    public function grant(string $moduleId, string $capability, string $grantedBy = 'system'): bool
    {
        if (!$this->registry->has($capability)) {
            return false;
        }

        $grants = $this->getGrants($moduleId);
        $grants[$capability] = [
            'capability'  => $capability,
            'granted_by'  => $grantedBy,
            'granted_at'  => now()->toIso8601String(),
        ];

        Cache::put($this->key($moduleId), $grants, self::GRANT_TTL);
        return true;
    }

    public function revoke(string $moduleId, string $capability): void
    {
        $grants = $this->getGrants($moduleId);
        unset($grants[$capability]);
        Cache::put($this->key($moduleId), $grants, self::GRANT_TTL);
    }

    public function revokeAll(string $moduleId): void
    {
        Cache::forget($this->key($moduleId));
    }

    public function hasGrant(string $moduleId, string $capability): bool
    {
        $definition = $this->registry->get($capability);

        if ($definition === null) {
            return false;
        }

        if (!$definition->requiresExplicitGrant()) {
            return true;
        }

        return isset($this->getGrants($moduleId)[$capability]);
    }

    public function getGrants(string $moduleId): array
    {
        return Cache::get($this->key($moduleId), []);
    }

    public function grantedCapabilities(string $moduleId): array
    {
        return array_keys($this->getGrants($moduleId));
    }

    private function key(string $moduleId): string
    {
        return self::GRANT_PREFIX . '.' . $moduleId;
    }
}
