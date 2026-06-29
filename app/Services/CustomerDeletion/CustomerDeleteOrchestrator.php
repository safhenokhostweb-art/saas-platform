<?php

namespace App\Services\CustomerDeletion;

use App\Models\CustomerDeletion;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeletePolicy;
use App\Services\CustomerDeletion\Stages\PreDeleteAuditStage;
use App\Services\CustomerDeletion\Stages\FinalBackupStage;
use App\Services\CustomerDeletion\Stages\StopServicesStage;
use App\Services\CustomerDeletion\Stages\RemoveQueueStage;
use App\Services\CustomerDeletion\Stages\RemoveSchedulerStage;
use App\Services\CustomerDeletion\Stages\RemoveWebServerStage;
use App\Services\CustomerDeletion\Stages\RemoveStorageStage;
use App\Services\CustomerDeletion\Stages\RemoveFilesStage;
use App\Services\CustomerDeletion\Stages\RemoveDatabaseStage;
use App\Services\CustomerDeletion\Stages\RemoveDatabaseUserStage;
use App\Services\CustomerDeletion\Stages\RemoveLicenseStage;
use App\Services\CustomerDeletion\Stages\RemovePlatformRecordsStage;
use App\Services\CustomerDeletion\Stages\RemoveRecoveryMetadataStage;
use App\Services\CustomerDeletion\Stages\VerifyDeleteStage;
use App\Services\CustomerInstallation\RemoteConnectionService;
use Illuminate\Support\Facades\Log;

class CustomerDeleteOrchestrator
{
    public function __construct(
        private readonly RemoteConnectionService     $connectionService,
        private readonly CustomerDeleteStateManager  $state,
    ) {}

    /**
     * Execute the full customer deletion pipeline.
     * Called from CustomerDeletionPipelineJob.
     *
     * Design properties:
     * - Resumable: already-completed stages are skipped on retry
     * - Idempotent: each stage uses IF EXISTS / rm -f patterns
     * - Non-critical stage failures are logged but do not abort the pipeline
     * - Critical stage failures abort and mark the deletion failed
     */
    public function run(CustomerDeletion $deletion): void
    {
        $installation = $deletion->installation;
        $server       = CustomerRemoteServer::find($installation->remote_server_id);

        $this->state->markRunning($deletion);

        // Connect to remote server
        try {
            $connection = $this->connectionService->buildDriver($server);
            $connection->connect();
        } catch (\Throwable $e) {
            $this->state->markFailed($deletion, 'Cannot connect to remote server: ' . $e->getMessage());
            return;
        }

        $ctx    = DeleteContext::fromDeletion($deletion, $connection);
        $stages = $this->buildStageList();

        try {
            foreach ($stages as $stage) {
                $stageName = $stage->name();

                // Resumability: skip already-completed stages
                if ($deletion->hasCompletedStage($stageName)) {
                    Log::info("CDE: Skipping completed stage {$stageName} for deletion #{$deletion->id}");
                    continue;
                }

                $this->state->recordStageStart($deletion, $stageName);

                $startMs = (int) round(microtime(true) * 1000);
                $result  = $stage->execute($ctx);
                $duration = (int) round(microtime(true) * 1000) - $startMs;

                if (!$result->success) {
                    $this->state->recordStageFailure($deletion, $stageName, $result->error ?? 'Unknown error', $duration);

                    if ($stage->isCritical()) {
                        $this->state->markFailed($deletion, "Critical stage {$stageName} failed: " . ($result->error ?? 'Unknown error'));
                        return;
                    }

                    // Non-critical failure: log and continue
                    Log::warning("CDE: Non-critical stage {$stageName} failed — continuing pipeline", [
                        'deletion_id' => $deletion->id,
                        'error'       => $result->error,
                    ]);
                    continue;
                }

                $ctx->recordOutput($stageName, $result->output);
                $this->state->recordStageComplete($deletion, $stageName, $result);
            }

            $this->state->markComplete($deletion);

        } catch (\Throwable $e) {
            Log::error("CDE: Unexpected error in delete pipeline", [
                'deletion_id' => $deletion->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            $this->state->markFailed($deletion, 'Unexpected pipeline error: ' . $e->getMessage());
        } finally {
            try {
                $connection->disconnect();
            } catch (\Throwable) {}
        }
    }

    /**
     * The delete pipeline — reverse order of the install pipeline.
     *
     * CUSTOMER_DELETE_PIPELINE:
     *   PRE_DELETE_AUDIT
     *   FINAL_BACKUP (policy-controlled)
     *   STOP_SERVICES
     *   REMOVE_QUEUE
     *   REMOVE_SCHEDULER
     *   REMOVE_WEBSERVER
     *   REMOVE_STORAGE
     *   REMOVE_FILES
     *   REMOVE_DATABASE
     *   REMOVE_DATABASE_USER
     *   REMOVE_LICENSE
     *   REMOVE_PLATFORM_RECORDS
     *   REMOVE_RECOVERY_METADATA (policy-controlled)
     *   VERIFY_DELETE
     */
    private function buildStageList(): array
    {
        return [
            app(PreDeleteAuditStage::class),
            app(FinalBackupStage::class),
            app(StopServicesStage::class),
            app(RemoveQueueStage::class),
            app(RemoveSchedulerStage::class),
            app(RemoveWebServerStage::class),
            app(RemoveStorageStage::class),
            app(RemoveFilesStage::class),
            app(RemoveDatabaseStage::class),
            app(RemoveDatabaseUserStage::class),
            app(RemoveLicenseStage::class),
            app(RemovePlatformRecordsStage::class),
            app(RemoveRecoveryMetadataStage::class),
            app(VerifyDeleteStage::class),
        ];
    }
}
