<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\OTA;

use Illuminate\Support\Facades\Cache;

class MobileSecurityPatchRuntime
{
    private const PREFIX = 'mobile:ota_patches:';
    private const TTL    = 86400;

    public function applyPatch(string $tenantId, string $patchId, array $patchData): void
    {
        $state           = $this->getPatchedState($tenantId);
        $state[$patchId] = array_merge($patchData, [
            'id'         => $patchId,
            'applied_at' => now()->toIso8601String(),
        ]);
        Cache::put(self::PREFIX . $tenantId, $state, self::TTL);
    }

    public function getPatchedState(string $tenantId): array
    {
        return Cache::get(self::PREFIX . $tenantId, []);
    }

    public function requiresPatchUpdate(string $appVersion, string $tenantId): bool
    {
        $critical = $this->getCriticalPatches($appVersion);
        if (empty($critical)) return false;
        $applied = array_keys($this->getPatchedState($tenantId));
        foreach ($critical as $patch) {
            if (! in_array($patch['id'], $applied, true)) return true;
        }
        return false;
    }

    public function getCriticalPatches(string $appVersion): array
    {
        $all = Cache::get(self::PREFIX . 'registry', []);
        return array_values(array_filter($all, function ($p) use ($appVersion) {
            $isCritical = ($p['severity'] ?? 'low') === 'critical';
            $applies    = empty($p['applies_to_versions'] ?? [])
                || in_array($appVersion, $p['applies_to_versions'], true);
            return $isCritical && $applies;
        }));
    }

    public function generatePatchManifest(string $tenantId, string $appVersion): array
    {
        $applied   = $this->getPatchedState($tenantId);
        $critical  = $this->getCriticalPatches($appVersion);
        $lastApplied = ! empty($applied) ? max(array_column($applied, 'applied_at')) : null;

        return [
            'patches'           => array_values($applied),
            'critical_count'    => count($critical),
            'last_applied'      => $lastApplied,
            'compliance_status' => $this->requiresPatchUpdate($appVersion, $tenantId) ? 'non_compliant' : 'compliant',
        ];
    }

    public function markPatchApplied(string $tenantId, string $patchId): void
    {
        $this->applyPatch($tenantId, $patchId, ['source' => 'ota']);
    }

    public function rollbackPatch(string $tenantId, string $patchId): void
    {
        $state = $this->getPatchedState($tenantId);
        unset($state[$patchId]);
        Cache::put(self::PREFIX . $tenantId, $state, self::TTL);
    }
}
