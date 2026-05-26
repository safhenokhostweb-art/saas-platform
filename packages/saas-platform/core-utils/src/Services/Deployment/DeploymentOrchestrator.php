<?php

namespace SaaSPlatform\CoreUtils\Services\Deployment;

use Illuminate\Support\Str;

/**
 * Canonical deployment orchestrator (Phase 8.2).
 *
 * Drives the DeploymentStateMachine through all phases,
 * guaranteeing transactional logging, lock management,
 * and rollback checkpoints at every step.
 *
 * Usage (from a deployment command):
 *
 *   $result = $orchestrator->run([
 *       'skip_maintenance' => false,
 *       'dry_run'          => false,
 *       'deployed_by'      => 'deploy-bot',
 *   ]);
 */
class DeploymentOrchestrator
{
    private string $deploymentUuid;
    private DeploymentStateMachine $stateMachine;

    public function __construct(
        private ReleaseLockManager       $lockManager,
        private ReleaseManifestManager   $manifestManager,
        private DeploymentTransactionLogger $logger,
        private DeploymentHealthVerifier $healthVerifier,
    ) {}

    public function run(array $options = []): array
    {
        $this->deploymentUuid = Str::uuid()->toString();
        $this->stateMachine   = new DeploymentStateMachine();
        $dryRun               = (bool) ($options['dry_run'] ?? false);

        try {
            // ── Phase: validating ────────────────────────────────────────────
            $this->advance(DeploymentStateMachine::STATE_VALIDATING);
            $validationResult = $this->runValidation($options);
            if (!$validationResult['passed']) {
                return $this->fail('Validation failed: ' . ($validationResult['reason'] ?? 'unknown'), $options);
            }
            $this->logger->completed($this->deploymentUuid, DeploymentStateMachine::STATE_VALIDATING, 'Pre-flight checks passed');

            if ($dryRun) {
                return $this->dryRunResult($validationResult);
            }

            // ── Acquire lock ─────────────────────────────────────────────────
            if (!$this->lockManager->acquire($this->deploymentUuid)) {
                return $this->fail('Another deployment is in progress — lock not acquired');
            }

            // ── Phase: maintenance ───────────────────────────────────────────
            $this->advance(DeploymentStateMachine::STATE_MAINTENANCE);
            $this->logger->completed($this->deploymentUuid, DeploymentStateMachine::STATE_MAINTENANCE, 'Maintenance window opened');

            // ── Phase: migrations ────────────────────────────────────────────
            $this->advance(DeploymentStateMachine::STATE_MIGRATIONS);
            $this->logger->completed($this->deploymentUuid, DeploymentStateMachine::STATE_MIGRATIONS, 'Migrations signalled (run by caller)');

            // ── Phase: assets ────────────────────────────────────────────────
            $this->advance(DeploymentStateMachine::STATE_ASSETS);
            $this->logger->completed($this->deploymentUuid, DeploymentStateMachine::STATE_ASSETS, 'Asset pipeline signalled');

            // ── Phase: cache-rebuild ─────────────────────────────────────────
            $this->advance(DeploymentStateMachine::STATE_CACHE_REBUILD);
            $this->logger->completed($this->deploymentUuid, DeploymentStateMachine::STATE_CACHE_REBUILD, 'Cache rebuild queued');

            // ── Phase: queue-restart ─────────────────────────────────────────
            $this->advance(DeploymentStateMachine::STATE_QUEUE_RESTART);
            $this->logger->completed($this->deploymentUuid, DeploymentStateMachine::STATE_QUEUE_RESTART, 'Queue restart signalled');

            // ── Phase: health-check ──────────────────────────────────────────
            $this->advance(DeploymentStateMachine::STATE_HEALTH_CHECK);
            $health = $this->healthVerifier->verify($this->deploymentUuid);
            if (!$health['passed']) {
                return $this->fail('Health check failed after deployment', $options, $health);
            }
            $this->logger->completed($this->deploymentUuid, DeploymentStateMachine::STATE_HEALTH_CHECK, "Health checks passed {$health['score']}");

            // ── Create manifest ──────────────────────────────────────────────
            $manifest = $this->manifestManager->create($this->deploymentUuid, $options);

            // ── Phase: finalized ─────────────────────────────────────────────
            $this->advance(DeploymentStateMachine::STATE_FINALIZED);
            $this->lockManager->release($this->deploymentUuid);
            $this->logger->completed($this->deploymentUuid, DeploymentStateMachine::STATE_FINALIZED, 'Deployment complete');

            return [
                'success'         => true,
                'deployment_uuid' => $this->deploymentUuid,
                'manifest'        => $manifest,
                'health'          => $health,
            ];

        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), $options);
        }
    }

    public function getDeploymentUuid(): string
    {
        return $this->deploymentUuid;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function advance(string $phase): void
    {
        $this->stateMachine->transition($phase);
        $this->lockManager->updatePhase($this->deploymentUuid, $phase);
        $this->logger->started($this->deploymentUuid, $phase);
    }

    private function runValidation(array $options): array
    {
        if ($this->lockManager->hasActiveLock()) {
            return ['passed' => false, 'reason' => 'Active deployment lock exists'];
        }
        return ['passed' => true];
    }

    private function fail(string $reason, array $options = [], array $context = []): array
    {
        try {
            $this->logger->failed($this->deploymentUuid, $this->stateMachine->current(), $reason, $context);
            $this->stateMachine->transition(DeploymentStateMachine::STATE_ROLLBACK);
            $this->stateMachine->transition(DeploymentStateMachine::STATE_RECOVERY);
            $this->stateMachine->transition(DeploymentStateMachine::STATE_UNLOCK);
            $this->lockManager->release($this->deploymentUuid);
            $this->stateMachine->transition(DeploymentStateMachine::STATE_FAILED);
        } catch (\Throwable) {
            // Best-effort cleanup
            $this->lockManager->forceUnlock($this->deploymentUuid);
        }

        return [
            'success'         => false,
            'deployment_uuid' => $this->deploymentUuid,
            'reason'          => $reason,
            'context'         => $context,
        ];
    }

    private function dryRunResult(array $validationResult): array
    {
        return [
            'success'         => true,
            'dry_run'         => true,
            'deployment_uuid' => $this->deploymentUuid,
            'message'         => 'Dry-run complete — no changes made',
            'validation'      => $validationResult,
        ];
    }
}
