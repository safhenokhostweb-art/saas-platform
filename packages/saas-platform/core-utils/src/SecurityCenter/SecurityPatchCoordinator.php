<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SecurityPatchCoordinator
{
    private const INSTALLED_KEY = 'security:patches:installed:';
    private const PENDING_KEY   = 'security:patches:pending:';
    private const HISTORY_KEY   = 'security:patches:history:';

    public function __construct(
        private readonly SecurityCenterTelemetryService $telemetry,
    ) {}

    public function getPendingCriticalCount(string $tenantId = 'global'): int
    {
        $pending = $this->getPendingPatches($tenantId);
        return count(array_filter($pending, fn ($p) => $p['channel'] === 'security' && $p['severity'] === 'critical'));
    }

    public function getPatchSummary(string $tenantId = 'global'): array
    {
        $installed = $this->getInstalledPatches($tenantId);
        $pending   = $this->getPendingPatches($tenantId);
        $critical  = array_filter($pending, fn ($p) => $p['severity'] === 'critical');

        return [
            'installed'        => count($installed),
            'pending_total'    => count($pending),
            'pending_critical' => count($critical),
            'confidence'       => $this->calculateOverallConfidence($installed),
            'last_installed_at'=> $installed ? max(array_column($installed, 'installed_at')) : null,
        ];
    }

    public function getMandatoryUpdates(): array
    {
        return array_filter(
            $this->getPendingPatches('global'),
            fn ($p) => $p['channel'] === 'security' || $p['mandatory'] ?? false
        );
    }

    public function getInstalledPatches(string $tenantId = 'global'): array
    {
        return Cache::get(self::INSTALLED_KEY . $tenantId, []);
    }

    public function getPendingPatches(string $tenantId = 'global'): array
    {
        return Cache::get(self::PENDING_KEY . $tenantId, $this->getBuiltInPendingPatches());
    }

    public function getPatchHistory(string $tenantId = 'global', int $limit = 50): array
    {
        $history = Cache::get(self::HISTORY_KEY . $tenantId, []);
        return array_slice(array_reverse($history), 0, $limit);
    }

    public function registerPatch(array $patch): string
    {
        $patch = array_merge([
            'id'           => uniqid('patch_', true),
            'title'        => '',
            'version'      => '1.0.0',
            'channel'      => 'security',
            'severity'     => 'medium',
            'mandatory'    => false,
            'requires_license' => false,
            'sha256'       => '',
            'signed'       => false,
            'rollback_safe'=> true,
            'published_at' => now()->toIso8601String(),
        ], $patch);

        $pending   = Cache::get(self::PENDING_KEY . 'global', []);
        $pending[$patch['id']] = $patch;
        Cache::put(self::PENDING_KEY . 'global', $pending, 86400 * 365);

        $this->telemetry->recordEvent('patch_registered', ['patch_id' => $patch['id'], 'channel' => $patch['channel']]);

        return $patch['id'];
    }

    public function markInstalled(string $patchId, string $tenantId = 'global'): void
    {
        $pending  = Cache::get(self::PENDING_KEY . $tenantId, []);
        $patch    = $pending[$patchId] ?? null;

        if (! $patch) {
            return;
        }

        $patch['installed_at'] = now()->toIso8601String();
        $patch['status']       = 'installed';

        $installed = Cache::get(self::INSTALLED_KEY . $tenantId, []);
        $installed[$patchId]   = $patch;
        Cache::put(self::INSTALLED_KEY . $tenantId, $installed, 86400 * 365);

        unset($pending[$patchId]);
        Cache::put(self::PENDING_KEY . $tenantId, $pending, 86400 * 365);

        $history   = Cache::get(self::HISTORY_KEY . $tenantId, []);
        $history[] = array_merge($patch, ['event' => 'installed', 'event_at' => now()->toIso8601String()]);
        Cache::put(self::HISTORY_KEY . $tenantId, $history, 86400 * 365);

        $this->telemetry->recordEvent('patch_installed', ['patch_id' => $patchId, 'tenant_id' => $tenantId]);
    }

    public function markRolledBack(string $patchId, string $tenantId = 'global', string $reason = ''): void
    {
        $installed = Cache::get(self::INSTALLED_KEY . $tenantId, []);
        $patch     = $installed[$patchId] ?? null;

        if (! $patch) {
            return;
        }

        unset($installed[$patchId]);
        Cache::put(self::INSTALLED_KEY . $tenantId, $installed, 86400 * 365);

        $history   = Cache::get(self::HISTORY_KEY . $tenantId, []);
        $history[] = array_merge($patch, ['event' => 'rolled_back', 'reason' => $reason, 'event_at' => now()->toIso8601String()]);
        Cache::put(self::HISTORY_KEY . $tenantId, $history, 86400 * 365);

        $this->telemetry->recordEvent('patch_rolled_back', ['patch_id' => $patchId, 'tenant_id' => $tenantId, 'reason' => $reason]);
    }

    private function calculateOverallConfidence(array $installed): int
    {
        if (empty($installed)) {
            return 87;
        }
        $scores = array_column($installed, 'confidence');
        return $scores ? (int)(array_sum($scores) / count($scores)) : 87;
    }

    private function getBuiltInPendingPatches(): array
    {
        return [];
    }
}
