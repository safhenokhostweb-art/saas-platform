<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Queue;

use Illuminate\Support\Facades\Artisan;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing\RecoveryExecutionLog;
use Illuminate\Support\Str;

class QueueAutoRecoveryManager
{
    public function __construct(
        private QueueStallDetector       $stallDetector,
        private WorkerCrashAnalyzer      $crashAnalyzer,
        private DeadLetterGovernanceService $deadLetterService,
        private QueueRuntimeBalancer     $balancer,
        private RecoveryExecutionLog     $executionLog,
        private TelemetryManager         $telemetry
    ) {}

    public function runRecoveryCycle(): array
    {
        $recoveryId = (string) Str::uuid();
        $actions    = [];

        // Check for stalled queues
        $stalled = $this->stallDetector->stalledQueues();
        if (!empty($stalled)) {
            $actions[] = $this->recoverStalledQueues($recoveryId, $stalled);
        }

        // Check for poison jobs
        $poisonJobs = $this->deadLetterService->detectPoisonJobs();
        if (!empty($poisonJobs)) {
            $actions[] = $this->handlePoisonJobs($recoveryId, $poisonJobs);
        }

        // Check for imbalance
        $balance = $this->balancer->analyze();
        if (!($balance['balanced'] ?? true)) {
            $actions[] = [
                'type'            => 'imbalance_detected',
                'recommendations' => $this->balancer->recommendations($balance),
                'status'          => 'operator_action_required',
            ];
        }

        $this->executionLog->record($recoveryId, 'queue_recovery_cycle', 'success', 'queue.auto_recovery', [
            'stalled_queues' => $stalled,
            'poison_job_count' => count($poisonJobs),
            'actions'        => count($actions),
        ]);

        return [
            'recovery_id' => $recoveryId,
            'actions'     => $actions,
            'ran_at'      => now()->toIso8601String(),
        ];
    }

    private function recoverStalledQueues(string $recoveryId, array $queues): array
    {
        try {
            Artisan::call('queue:restart');

            $this->telemetry->warning('governance', 'governance.queue.stall_recovery', [
                'recovery_id'    => $recoveryId,
                'stalled_queues' => $queues,
            ]);

            return ['type' => 'stall_recovery', 'queues' => $queues, 'status' => 'queue_restarted'];
        } catch (\Throwable $e) {
            return ['type' => 'stall_recovery', 'queues' => $queues, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function handlePoisonJobs(string $recoveryId, array $poisonJobs): array
    {
        $this->telemetry->error('governance', 'governance.queue.poison_jobs_detected', [
            'recovery_id' => $recoveryId,
            'count'       => count($poisonJobs),
        ]);

        // Quarantine the default queue if poison jobs detected — do NOT auto-delete
        $this->deadLetterService->quarantineQueue('default', 'poison_job_detected:' . $recoveryId);

        return [
            'type'        => 'poison_job_handling',
            'count'       => count($poisonJobs),
            'status'      => 'queue_quarantined',
            'action'      => 'operator_must_review_failed_jobs',
        ];
    }
}
