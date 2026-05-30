<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MobileSecurityPatchCoordinator
{
    private const PREFIX = 'mobile:security:patches:';
    private const TTL    = 86400;

    /** @var array<string, array> */
    private array $patchRegistry = [];

    public function getAvailablePatches(): array
    {
        return Cache::get(self::PREFIX . 'global', []);
    }

    public function registerPatch(string $patchId, array $metadata): void
    {
        $this->patchRegistry[$patchId] = array_merge($metadata, [
            'id'          => $patchId,
            'registered_at' => now()->toIso8601String(),
        ]);
        $globalPatches             = Cache::get(self::PREFIX . 'global', []);
        $globalPatches[$patchId]   = $this->patchRegistry[$patchId];
        Cache::put(self::PREFIX . 'global', $globalPatches, self::TTL);
    }

    public function applyPatch(string $tenantId, string $patchId): void
    {
        $status = $this->getPatchStatus($tenantId);
        if (! in_array($patchId, $status['applied'], true)) {
            $status['applied'][] = $patchId;
            Cache::put(self::PREFIX . "tenant:{$tenantId}", $status, self::TTL);
            Log::info('mobile.security.patch_applied', ['tenant_id' => $tenantId, 'patch_id' => $patchId]);
        }
    }

    public function getPatchStatus(string $tenantId): array
    {
        $stored   = Cache::get(self::PREFIX . "tenant:{$tenantId}", ['applied' => []]);
        $global   = Cache::get(self::PREFIX . 'global', []);
        $applied  = $stored['applied'] ?? [];
        $pending  = array_keys(array_filter($global, fn ($p) => ! in_array($p['id'], $applied, true)));
        $critical = array_filter($global, fn ($p) => ($p['severity'] ?? 'low') === 'critical' && ! in_array($p['id'], $applied, true));

        return [
            'applied'        => $applied,
            'pending'        => $pending,
            'critical_count' => count($critical),
        ];
    }

    public function isSecurityCompliant(string $tenantId, string $appVersion): bool
    {
        $critical = $this->getCriticalPatches($appVersion);
        if (empty($critical)) {
            return true;
        }
        $status  = $this->getPatchStatus($tenantId);
        $applied = $status['applied'];
        foreach ($critical as $patch) {
            if (! in_array($patch['id'], $applied, true)) {
                return false;
            }
        }
        return true;
    }

    public function getPendingPatches(string $tenantId, string $appVersion): array
    {
        $status  = $this->getPatchStatus($tenantId);
        $applied = $status['applied'];
        $global  = Cache::get(self::PREFIX . 'global', []);
        $pending = [];
        foreach ($global as $patch) {
            if (in_array($patchId = $patch['id'], $applied, true)) continue;
            $appVersions = $patch['applies_to_versions'] ?? [];
            if (empty($appVersions) || in_array($appVersion, $appVersions, true)) {
                $pending[] = $patch;
            }
        }
        return $pending;
    }

    public function getCriticalPatches(string $appVersion): array
    {
        $global = Cache::get(self::PREFIX . 'global', []);
        return array_values(array_filter($global, function ($p) use ($appVersion) {
            $isCritical  = ($p['severity'] ?? 'low') === 'critical';
            $appVersions = $p['applies_to_versions'] ?? [];
            $applies     = empty($appVersions) || in_array($appVersion, $appVersions, true);
            return $isCritical && $applies;
        }));
    }

    public function generateSecurityReport(string $tenantId): array
    {
        $status = $this->getPatchStatus($tenantId);
        $score  = $status['critical_count'] === 0
            ? (count($status['pending']) === 0 ? 100 : max(70, 100 - count($status['pending']) * 5))
            : max(0, 50 - $status['critical_count'] * 20);

        return [
            'tenant_id'       => $tenantId,
            'applied_count'   => count($status['applied']),
            'pending_count'   => count($status['pending']),
            'critical_count'  => $status['critical_count'],
            'security_score'  => $score,
            'compliant'       => $status['critical_count'] === 0,
            'generated_at'    => now()->toIso8601String(),
        ];
    }

    public function validateSecurityPosture(string $tenantId, string $appVersion): array
    {
        $compliant = $this->isSecurityCompliant($tenantId, $appVersion);
        $report    = $this->generateSecurityReport($tenantId);
        $issues    = [];

        if ($report['critical_count'] > 0) {
            $issues[] = "{$report['critical_count']} critical security patch(es) not applied";
        }
        if ($report['pending_count'] > 5) {
            $issues[] = "{$report['pending_count']} pending patches exceed threshold";
        }

        return [
            'compliant' => $compliant,
            'score'     => $report['security_score'],
            'issues'    => $issues,
        ];
    }
}
