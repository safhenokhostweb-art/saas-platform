<?php

namespace SaaSPlatform\CoreUtils\Services\Rollback;

use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseLockManager;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestManager;
use SaaSPlatform\CoreUtils\Services\Deployment\DeploymentTransactionLogger;

/**
 * Coordinates rolling back to a previous release.
 *
 * Performs validation, records a rollback audit trail,
 * and provides dry-run capability before any destructive action.
 */
class RollbackCoordinator
{
    public function __construct(
        private ReleaseLockManager          $lockManager,
        private ReleaseManifestManager      $manifestManager,
        private ReleaseRollbackValidator    $validator,
        private DeploymentTransactionLogger $logger,
    ) {}

    public function rollback(string $targetUuid, bool $dryRun = false): array
    {
        // Validate first
        $validation = $this->validator->canRollback($targetUuid);

        if (!$validation['can_rollback']) {
            return [
                'success'   => false,
                'dry_run'   => $dryRun,
                'reason'    => 'Rollback validation failed',
                'validation'=> $validation,
            ];
        }

        if ($dryRun) {
            return $this->dryRunResult($targetUuid, $validation);
        }

        // Acquire lock
        $rollbackUuid = \Illuminate\Support\Str::uuid()->toString();

        if (!$this->lockManager->acquire($rollbackUuid)) {
            return [
                'success' => false,
                'reason'  => 'Could not acquire deployment lock for rollback',
            ];
        }

        try {
            $this->logger->started($rollbackUuid, 'rollback', ['target' => $targetUuid]);

            $target  = $this->manifestManager->read($targetUuid);
            $current = $this->manifestManager->latest();

            $diff = $current
                ? $this->manifestManager->diff($current['deployment_uuid'], $targetUuid)
                : [];

            // Log the rollback action (actual migration/asset execution is caller's responsibility)
            $this->logger->completed($rollbackUuid, 'rollback',
                "Rollback to {$targetUuid} validated and initiated",
                ['diff' => $diff, 'warnings' => $validation['warnings']]
            );

            Log::warning('deployment.rollback.initiated', [
                'from'      => $current['deployment_uuid'] ?? null,
                'to'        => $targetUuid,
                'uuid'      => $rollbackUuid,
                'warnings'  => $validation['warnings'],
            ]);

            $this->lockManager->release($rollbackUuid);

            return [
                'success'         => true,
                'rollback_uuid'   => $rollbackUuid,
                'target_uuid'     => $targetUuid,
                'target_manifest' => $target,
                'diff'            => $diff,
                'warnings'        => $validation['warnings'],
                'next_steps'      => $this->nextSteps($target ?? []),
            ];

        } catch (\Throwable $e) {
            $this->logger->failed($rollbackUuid, 'rollback', $e->getMessage());
            $this->lockManager->forceUnlock($rollbackUuid);

            return [
                'success' => false,
                'reason'  => $e->getMessage(),
            ];
        }
    }

    private function dryRunResult(string $targetUuid, array $validation): array
    {
        $target  = $this->manifestManager->read($targetUuid);
        $current = $this->manifestManager->latest();

        return [
            'success'         => true,
            'dry_run'         => true,
            'target_uuid'     => $targetUuid,
            'target_manifest' => $target,
            'validation'      => $validation,
            'diff'            => $current
                ? $this->manifestManager->diff($current['deployment_uuid'], $targetUuid)
                : [],
            'next_steps'      => $this->nextSteps($target ?? []),
        ];
    }

    private function nextSteps(array $target): array
    {
        $steps = [];

        if (isset($target['migration_batch'])) {
            $steps[] = "Run: php artisan migrate:rollback --step=<batches>";
        }

        $steps[] = "Run: php artisan cache:clear";
        $steps[] = "Run: php artisan queue:restart";
        $steps[] = "Revert composer.lock to target release";
        $steps[] = "Run: npm run build (if assets changed)";

        return $steps;
    }
}
