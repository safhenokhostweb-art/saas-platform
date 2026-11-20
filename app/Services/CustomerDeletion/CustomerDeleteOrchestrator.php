<?php

namespace App\Services\CustomerDeletion;

use App\Models\CustomerDeletion;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Stages\FinalBackupStage;
use App\Services\CustomerDeletion\Stages\PreDeleteAuditStage;
use App\Services\CustomerDeletion\Stages\RemoveDatabaseStage;
use App\Services\CustomerDeletion\Stages\RemoveDatabaseUserStage;
use App\Services\CustomerDeletion\Stages\RemoveFilesStage;
use App\Services\CustomerDeletion\Stages\RemoveLicenseStage;
use App\Services\CustomerDeletion\Stages\RemovePlatformRecordsStage;
use App\Services\CustomerDeletion\Stages\RemoveQueueStage;
use App\Services\CustomerDeletion\Stages\RemoveRecoveryMetadataStage;
use App\Services\CustomerDeletion\Stages\RemoveSchedulerStage;
use App\Services\CustomerDeletion\Stages\RemoveSslCertificateStage;
use App\Services\CustomerDeletion\Stages\RemoveStorageStage;
use App\Services\CustomerDeletion\Stages\RemoveTenantMediaStage;
use App\Services\CustomerDeletion\Stages\RemoveWebServerStage;
use App\Services\CustomerDeletion\Stages\StopServicesStage;
use App\Services\CustomerDeletion\Stages\VerifyDeleteStage;
use App\Services\CustomerInstallation\RemoteConnectionService;
use Illuminate\Support\Facades\Cache;
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
     * - Critical stage failures abort and mark the deletion failed
     * - ADR-090:
     *   - one run per deletion at a time (cache lock), whatever the queue does;
     *   - a non-critical failure no longer ends "complete": the pipeline
     *     continues so every independent resource is attempted, but the
     *     platform records (REMOVE_PLATFORM_RECORDS, requiresCleanRun) are kept
     *     and the deletion ends `failed`, so the site stays visible and Retry
     *     resumes from the failed stages;
     *   - "manual" stage outcomes (unsafe to automate) do not block; the
     *     deletion ends `complete_with_warnings` with the list stored in
     *     manual_actions.
     */
    public function run(CustomerDeletion $deletion): void
    {
        $lock = Cache::lock("customer-deletion:{$deletion->id}", 1800);
        if (!$lock->get()) {
            Log::warning("CDE: deletion #{$deletion->id} is already running — duplicate run ignored");
            return;
        }

        try {
            $this->runLocked($deletion);
        } finally {
            $lock->release();
        }
    }

    private function runLocked(CustomerDeletion $deletion): void
    {
        $installation = $deletion->installation;
        $server       = CustomerRemoteServer::find($installation->remote_server_id);

        $this->state->markRunning($deletion);

        if (!$server) {
            $this->state->markFailed($deletion, 'سرور مقصد این نصب وجود ندارد؛ هیچ تغییری انجام نشد.');
            return;
        }

        // Connect to remote server — before anything is changed anywhere.
        try {
            $connection = $this->connectionService->buildDriver($server);
            $connection->connect();
        } catch (\Throwable $e) {
            $this->state->markFailed($deletion, 'Cannot connect to remote server: ' . $e->getMessage());
            return;
        }

        try {
            $ctx    = DeleteContext::fromDeletion($deletion, $connection);
            $failed = [];
            $manual = $deletion->manual_actions ?? [];

            foreach ($this->buildStageList() as $stage) {
                $stageName = $stage->name();

                // Resumability: skip already-completed stages
                if ($deletion->hasCompletedStage($stageName)) {
                    Log::info("CDE: Skipping completed stage {$stageName} for deletion #{$deletion->id}");
                    continue;
                }

                if ($stage->requiresCleanRun() && $failed !== []) {
                    $this->state->markFailed($deletion,
                        'این مراحل ناموفق بودند: ' . implode('، ', $failed)
                        . ' — رکوردهای سایت در پنل حفظ شد. پس از رفع مشکل، «تلاش مجدد» را بزنید.'
                    );
                    return;
                }

                $this->state->recordStageStart($deletion, $stageName);

                $startMs  = (int) round(microtime(true) * 1000);
                $result   = $stage->execute($ctx);
                $duration = (int) round(microtime(true) * 1000) - $startMs;

                if (!$result->success) {
                    $this->state->recordStageFailure($deletion, $stageName, $result->error ?? 'Unknown error', $duration);

                    if ($stage->isCritical()) {
                        $this->state->markFailed($deletion, "Critical stage {$stageName} failed: " . ($result->error ?? 'Unknown error'));
                        return;
                    }

                    Log::warning("CDE: Non-critical stage {$stageName} failed — continuing pipeline", [
                        'deletion_id' => $deletion->id,
                        'error'       => $result->error,
                    ]);
                    $failed[] = $stageName;
                    continue;
                }

                if ($result->manual) {
                    $manual[$stageName] = (string) ($result->output['manual_action'] ?? '');
                    $deletion->update(['manual_actions' => $manual]);
                }

                $ctx->recordOutput($stageName, $result->output);
                $this->state->recordStageComplete($deletion, $stageName, $result);
            }

            if ($failed !== []) {
                $this->state->markFailed($deletion, 'این مراحل ناموفق بودند: ' . implode('، ', $failed) . ' — «تلاش مجدد» را بزنید.');
                return;
            }

            $manual === []
                ? $this->state->markComplete($deletion)
                : $this->state->markCompleteWithWarnings($deletion, $manual);

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
     * CUSTOMER_DELETE_PIPELINE (ADR-090 order):
     *   PRE_DELETE_AUDIT
     *   FINAL_BACKUP (policy-controlled; skipped when backups are deleted)
     *   STOP_SERVICES
     *   REMOVE_QUEUE
     *   REMOVE_SCHEDULER
     *   REMOVE_WEBSERVER          (nginx -t guarded, move-then-delete)
     *   REMOVE_SSL                (certbot delete, ownership-checked)
     *   REMOVE_STORAGE
     *   REMOVE_FILES              (RemotePathGuard)
     *   REMOVE_DATABASE
     *   REMOVE_DATABASE_USER
     *   REMOVE_RECOVERY_METADATA  (all Arvan backup prefixes; critical)
     *   REMOVE_TENANT_MEDIA       (sites/{id}/, when selected)
     *   REMOVE_LICENSE
     *   REMOVE_PLATFORM_RECORDS   (only after a clean run; Customer kept)
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
            app(RemoveSslCertificateStage::class),
            app(RemoveStorageStage::class),
            app(RemoveFilesStage::class),
            app(RemoveDatabaseStage::class),
            app(RemoveDatabaseUserStage::class),
            app(RemoveRecoveryMetadataStage::class),
            app(RemoveTenantMediaStage::class),
            app(RemoveLicenseStage::class),
            app(RemovePlatformRecordsStage::class),
            app(VerifyDeleteStage::class),
        ];
    }
}
