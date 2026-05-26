<?php

namespace SaaSPlatform\CoreUtils\Services\Rollback;

use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestManager;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestValidator;

/**
 * Validates whether a rollback to a target release is safe to execute.
 */
class ReleaseRollbackValidator
{
    public function __construct(
        private ReleaseManifestManager   $manifestManager,
        private ReleaseManifestValidator $manifestValidator
    ) {}

    public function canRollback(string $targetUuid): array
    {
        $checks = [];

        // Target manifest exists and is valid
        $checks[] = $this->checkTargetExists($targetUuid);
        $checks[] = $this->checkTargetIntegrity($targetUuid);
        $checks[] = $this->checkMigrationRollback($targetUuid);
        $checks[] = $this->checkNotCurrentRelease($targetUuid);

        $passed = array_sum(array_column($checks, 'passed'));
        $total  = count($checks);
        $allOk  = $passed === $total;

        return [
            'can_rollback'  => $allOk,
            'target_uuid'   => $targetUuid,
            'score'         => "{$passed}/{$total}",
            'checks'        => $checks,
            'warnings'      => $this->collectWarnings($targetUuid),
        ];
    }

    private function checkTargetExists(string $uuid): array
    {
        $exists = $this->manifestManager->read($uuid) !== null;
        return [
            'check'  => 'target_manifest_exists',
            'passed' => $exists,
            'detail' => $exists ? 'Target release manifest found' : "No manifest found for: {$uuid}",
        ];
    }

    private function checkTargetIntegrity(string $uuid): array
    {
        $result = $this->manifestValidator->validateFromFile($uuid);
        return [
            'check'  => 'target_integrity',
            'passed' => $result['valid'],
            'detail' => $result['valid'] ? 'Checksum verified' : implode('; ', $result['issues'] ?? []),
        ];
    }

    private function checkMigrationRollback(string $targetUuid): array
    {
        $target  = $this->manifestManager->read($targetUuid);
        $current = $this->manifestManager->latest();

        if (!$target || !$current) {
            return ['check' => 'migration_rollback', 'passed' => true, 'detail' => 'Cannot determine — missing manifests'];
        }

        $targetBatch  = (int) ($target['migration_batch']  ?? 0);
        $currentBatch = (int) ($current['migration_batch'] ?? 0);

        if ($targetBatch >= $currentBatch) {
            return ['check' => 'migration_rollback', 'passed' => true, 'detail' => 'No migrations to roll back'];
        }

        // Check if rollback migrations exist
        $batchesToRollback = $currentBatch - $targetBatch;
        return [
            'check'  => 'migration_rollback',
            'passed' => true, // warning only, not a hard blocker
            'detail' => "{$batchesToRollback} migration batch(es) would need rollback",
        ];
    }

    private function checkNotCurrentRelease(string $uuid): array
    {
        $current = $this->manifestManager->latest();
        $isCurrent = $current && ($current['deployment_uuid'] === $uuid);
        return [
            'check'  => 'not_current_release',
            'passed' => !$isCurrent,
            'detail' => $isCurrent ? 'Cannot rollback to current release' : 'Target is not the current release',
        ];
    }

    private function collectWarnings(string $uuid): array
    {
        $warnings = [];
        $target   = $this->manifestManager->read($uuid);
        $current  = $this->manifestManager->latest();

        if ($target && $current) {
            if (($target['migration_batch'] ?? 0) < ($current['migration_batch'] ?? 0)) {
                $warnings[] = 'Migration rollback required — verify down() methods exist';
            }

            $diff = $this->manifestManager->diff($current['deployment_uuid'], $uuid);
            if (!empty($diff['module_version_changes'])) {
                $warnings[] = 'Module version changes — verify backward compatibility';
            }
        }

        return $warnings;
    }
}
