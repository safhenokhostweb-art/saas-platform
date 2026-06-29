<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Contracts\InstallationStateInterface;
use App\Services\CustomerInstallation\Contracts\InstallationStageInterface;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Stages\ValidateServerStage;
use App\Services\CustomerInstallation\Stages\ValidatePhpStage;
use App\Services\CustomerInstallation\Stages\ValidateExtensionsStage;
use App\Services\CustomerInstallation\Stages\ValidateDiskStage;
use App\Services\CustomerInstallation\Stages\ValidatePermissionsStage;
use App\Services\CustomerInstallation\Stages\UploadPackageStage;
use App\Services\CustomerInstallation\Stages\VerifyPackageStage;
use App\Services\CustomerInstallation\Stages\ExtractPackageStage;
use App\Services\CustomerInstallation\Stages\GenerateEnvironmentStage;
use App\Services\CustomerInstallation\Stages\CreateDatabaseStage;
use App\Services\CustomerInstallation\Stages\RunMigrationsStage;
use App\Services\CustomerInstallation\Stages\RunSeederStage;
use App\Services\CustomerInstallation\Stages\ConfigureStorageStage;
use App\Services\CustomerInstallation\Stages\ConfigureQueueStage;
use App\Services\CustomerInstallation\Stages\ConfigureSchedulerStage;
use App\Services\CustomerInstallation\Stages\ConfigureWebServerStage;
use App\Services\CustomerInstallation\Stages\ProvisionLicenseStage;
use App\Services\CustomerInstallation\Stages\InitializeBackupStage;
use App\Services\CustomerInstallation\Stages\ConfigureMonitoringStage;
use App\Services\CustomerInstallation\Stages\InstallThemeStage;
use App\Services\CustomerInstallation\Stages\CustomerReadyStage;
use App\Jobs\CustomerInstallationRollbackJob;
use Illuminate\Support\Facades\Log;

class CustomerInstallationOrchestrator
{
    public function __construct(
        private readonly InstallationStateInterface    $state,
        private readonly RemoteConnectionService       $connectionService,
        private readonly CustomerInstallationRollbackService $rollback,
    ) {}

    /**
     * Execute the full installation pipeline for a given installation record.
     * Called from CustomerInstallationPipelineJob.
     */
    public function run(CustomerInstallation $installation): void
    {
        $request = CustomerInstallRequest::fromArray($installation->request_payload ?? []);
        $server = CustomerRemoteServer::findOrFail($installation->remote_server_id);
        $stages = $this->buildStageList($request->installMode);

        $this->state->markRunning($installation);

        $connection = $this->connectionService->buildDriver($server);

        try {
            $connection->connect();
        } catch (\Throwable $e) {
            $this->state->markFailed($installation, 'Cannot connect to remote server: ' . $e->getMessage());
            return;
        }

        $ctx = new InstallationContext($installation, $request, $connection);

        try {
            foreach ($stages as $stage) {
                $stageName = $stage->name();

                // Resume: skip already-completed stages
                if ($installation->hasCompletedStage($stageName)) {
                    Log::info("CIE: Skipping completed stage {$stageName}");
                    continue;
                }

                $this->state->recordStageStart($installation, $stageName);

                // Build rollback snapshot BEFORE execution wherever possible.
                // Stages override buildPreSnapshot() to return their resource paths so
                // that rollback has actionable data even when the stage fails mid-way.
                if ($stage->canRollback()) {
                    $preSnapshot = $stage->buildPreSnapshot($ctx);
                    $this->state->recordRollbackCheckpoint($installation, $stageName, $preSnapshot);
                }

                $startMs = (int) round(microtime(true) * 1000);
                $result = $stage->execute($ctx);
                $durationMs = (int) round(microtime(true) * 1000) - $startMs;

                if (!$result->success) {
                    $this->state->recordStageFailure($installation, $stageName, $result->error ?? 'Unknown error', $durationMs);
                    $this->state->markFailed($installation, "Stage {$stageName} failed: " . ($result->error ?? 'Unknown error'));
                    $this->dispatchRollback($installation);
                    return;
                }

                // Update the checkpoint with the actual snapshot from the stage result.
                // This upserts — no second record is created (see InstallationStateManager).
                if ($stage->canRollback() && !empty($result->rollbackSnapshot)) {
                    $ctx->pushRollback($stageName, $result->rollbackSnapshot);
                    $this->state->recordRollbackCheckpoint($installation, $stageName, $result->rollbackSnapshot);
                }

                $ctx->recordOutput($stageName, $result->output);
                $this->state->recordStageComplete($installation, $stageName, $result->output, $durationMs);
            }

        } catch (\Throwable $e) {
            Log::error("CIE: Unexpected error in pipeline", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->state->markFailed($installation, 'Unexpected pipeline error: ' . $e->getMessage());
            $this->dispatchRollback($installation);
        } finally {
            try {
                $connection->disconnect();
            } catch (\Throwable) {}
        }
    }

    /**
     * Build ordered stage list for the given installation mode.
     */
    public function buildStageList(string $mode): array
    {
        $stages = $this->resolveAllStages();

        return match($mode) {
            'MODE_RESTORE' => $this->modeRestoreStages($stages),
            default        => $stages,
        };
    }

    private function resolveAllStages(): array
    {
        return [
            app(ValidateServerStage::class),
            app(ValidatePhpStage::class),
            app(ValidateExtensionsStage::class),
            app(ValidateDiskStage::class),
            app(ValidatePermissionsStage::class),
            app(UploadPackageStage::class),
            app(VerifyPackageStage::class),
            app(ExtractPackageStage::class),
            app(GenerateEnvironmentStage::class),
            app(CreateDatabaseStage::class),
            app(RunMigrationsStage::class),
            app(RunSeederStage::class),
            app(ConfigureStorageStage::class),
            app(ConfigureQueueStage::class),
            app(ConfigureSchedulerStage::class),
            app(ConfigureWebServerStage::class),
            app(ProvisionLicenseStage::class),
            app(InitializeBackupStage::class),
            app(ConfigureMonitoringStage::class),
            app(InstallThemeStage::class),
            app(CustomerReadyStage::class),
        ];
    }

    private function modeRestoreStages(array $allStages): array
    {
        // For restore mode: skip CREATE_DATABASE, RUN_MIGRATIONS, RUN_SEEDER
        $skipInRestore = ['CREATE_DATABASE', 'RUN_MIGRATIONS', 'RUN_SEEDER'];

        return array_values(array_filter(
            $allStages,
            fn(InstallationStageInterface $s) => !in_array($s->name(), $skipInRestore)
        ));
    }

    private function dispatchRollback(CustomerInstallation $installation): void
    {
        try {
            CustomerInstallationRollbackJob::dispatch($installation->id)
                ->onConnection('customer-install')
                ->onQueue('customer-install');
        } catch (\Throwable $e) {
            Log::error("CIE: Failed to dispatch rollback job", ['error' => $e->getMessage()]);
        }
    }
}
