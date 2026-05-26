<?php

namespace SaaSPlatform\CoreUtils\Services\Rollback;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseLockManager;
use SaaSPlatform\CoreUtils\Services\Deployment\DeploymentTransactionLogger;
use SaaSPlatform\CoreUtils\Services\Maintenance\TenantMaintenanceManager;

/**
 * Recovers the platform from a failed or interrupted deployment.
 *
 * Run when:
 *  - A deployment process died mid-flight
 *  - A deployment lock is stale
 *  - Tenants are stuck in maintenance after a failed deploy
 *  - The system is in an unknown/inconsistent state
 */
class FailedDeploymentRecoveryService
{
    public function __construct(
        private ReleaseLockManager          $lockManager,
        private DeploymentTransactionLogger $logger,
        private TenantMaintenanceManager    $maintenanceManager
    ) {}

    public function recover(string $failedUuid, array $options = []): array
    {
        $actions = [];

        // Step 1 — Release stale lock
        if ($this->lockManager->hasActiveLock()) {
            $lock = $this->lockManager->getActiveLock();
            if ($lock && $lock->deployment_uuid === $failedUuid) {
                $this->lockManager->forceUnlock($failedUuid);
                $actions[] = ['action' => 'release_lock', 'uuid' => $failedUuid, 'status' => 'done'];
            }
        }

        // Step 2 — Lift tenant maintenance windows associated with failed deployment
        $lifted = $this->maintenanceManager->disableForDeployment($failedUuid);
        if ($lifted > 0) {
            $actions[] = ['action' => 'lift_maintenance', 'count' => $lifted, 'status' => 'done'];
        }

        // Step 3 — Log the recovery event
        $this->logger->rolledBack($failedUuid, 'recovery', 'Automated recovery from failed deployment');

        Log::warning('deployment.recovery.executed', [
            'failed_uuid' => $failedUuid,
            'actions'     => $actions,
            'recovered_at'=> now()->toIso8601String(),
        ]);

        return [
            'success'     => true,
            'failed_uuid' => $failedUuid,
            'actions'     => $actions,
            'next_steps'  => [
                'Verify: php artisan platform:health',
                'Verify: php artisan migration:detect-drift',
                'If health fails: run rollback per rollback-runbook.md',
            ],
        ];
    }

    public function audit(string $uuid): array
    {
        $logs   = $this->logger->getLogs($uuid);
        $lock   = DB::table('release_locks')->where('deployment_uuid', $uuid)->first();

        return [
            'deployment_uuid' => $uuid,
            'lock_record'     => $lock ? (array) $lock : null,
            'log_entries'     => $logs,
            'failed_phases'   => array_filter($logs, fn($l) => ($l['status'] ?? '') === 'failed'),
        ];
    }
}
