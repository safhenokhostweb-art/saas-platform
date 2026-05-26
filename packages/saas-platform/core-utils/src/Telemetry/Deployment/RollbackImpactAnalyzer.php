<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Deployment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RollbackImpactAnalyzer
{
    public function analyze(string $currentUuid, string $targetUuid): array
    {
        $current = $this->loadManifest($currentUuid);
        $target  = $this->loadManifest($targetUuid);

        if (!$current || !$target) {
            return [
                'analyzable'  => false,
                'reason'      => 'One or both manifests not found',
                'current'     => $currentUuid,
                'target'      => $targetUuid,
            ];
        }

        return [
            'analyzable'          => true,
            'current_uuid'        => $currentUuid,
            'target_uuid'         => $targetUuid,
            'migration_impact'    => $this->analyzeMigrationImpact($current, $target),
            'asset_impact'        => $this->analyzeAssetImpact($current, $target),
            'version_delta'       => $this->analyzeVersionDelta($current, $target),
            'tenant_impact'       => $this->estimateTenantImpact(),
            'risk_level'          => $this->computeRiskLevel($current, $target),
            'analyzed_at'         => now()->toIso8601String(),
        ];
    }

    private function analyzeMigrationImpact(array $current, array $target): array
    {
        $currentBatch = $current['migration_batch'] ?? 0;
        $targetBatch  = $target['migration_batch'] ?? 0;

        return [
            'current_batch'    => $currentBatch,
            'target_batch'     => $targetBatch,
            'batches_to_revert' => max(0, $currentBatch - $targetBatch),
            'requires_migration_rollback' => $currentBatch > $targetBatch,
        ];
    }

    private function analyzeAssetImpact(array $current, array $target): array
    {
        $currentChecksum = $current['vite_manifest_checksum'] ?? null;
        $targetChecksum  = $target['vite_manifest_checksum'] ?? null;

        return [
            'assets_changed'  => $currentChecksum !== $targetChecksum,
            'current_checksum' => $currentChecksum,
            'target_checksum' => $targetChecksum,
        ];
    }

    private function analyzeVersionDelta(array $current, array $target): array
    {
        $currentVersions = $current['package_versions'] ?? [];
        $targetVersions  = $target['package_versions'] ?? [];

        $changed = [];
        foreach ($currentVersions as $pkg => $version) {
            if (($targetVersions[$pkg] ?? null) !== $version) {
                $changed[$pkg] = [
                    'current' => $version,
                    'target'  => $targetVersions[$pkg] ?? null,
                ];
            }
        }

        return [
            'packages_changed' => count($changed),
            'changes'          => $changed,
        ];
    }

    private function estimateTenantImpact(): array
    {
        try {
            $activeTenants = DB::getSchemaBuilder()->hasTable('sites')
                ? DB::table('sites')->where('status', 'active')->count()
                : 0;

            return [
                'active_tenants'     => $activeTenants,
                'maintenance_needed' => $activeTenants > 0,
            ];
        } catch (\Throwable) {
            return ['active_tenants' => 0, 'maintenance_needed' => false];
        }
    }

    private function computeRiskLevel(array $current, array $target): string
    {
        $currentBatch = $current['migration_batch'] ?? 0;
        $targetBatch  = $target['migration_batch'] ?? 0;

        if ($currentBatch - $targetBatch >= 3) {
            return 'HIGH';
        }

        if ($currentBatch > $targetBatch) {
            return 'MEDIUM';
        }

        return 'LOW';
    }

    private function loadManifest(string $uuid): ?array
    {
        $path = "releases/{$uuid}.json";

        if (!Storage::exists($path)) {
            return null;
        }

        $content = Storage::get($path);
        return $content ? json_decode($content, true) : null;
    }
}
