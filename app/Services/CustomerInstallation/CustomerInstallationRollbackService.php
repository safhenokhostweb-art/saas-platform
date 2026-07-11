<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallRollback;
use App\Services\CustomerInstallation\Contracts\InstallationStateInterface;
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
use App\Services\CustomerInstallation\Stages\ConfigureDnsStage;
use App\Services\CustomerInstallation\Stages\ConfigureWebServerStage;
use App\Services\CustomerInstallation\Stages\ProvisionLicenseStage;
use App\Services\CustomerInstallation\Stages\InitializeBackupStage;
use App\Services\CustomerInstallation\Stages\ConfigureMonitoringStage;
use App\Services\CustomerInstallation\Stages\InstallThemeStage;
use App\Services\CustomerInstallation\Stages\CustomerReadyStage;
use App\Services\CustomerInstallation\Stages\BackupModuleUpgradeStage;
use App\Services\CustomerInstallation\Stages\ExtractModulePackageStage;
use App\Services\CustomerInstallation\Stages\CompleteModuleUpgradeStage;
use App\Services\CustomerInstallation\Stages\LockTenantStage;
use App\Services\CustomerInstallation\Stages\UnlockTenantStage;
use Illuminate\Support\Facades\Log;

class CustomerInstallationRollbackService
{
    private array $stageMap = [];

    public function __construct(
        private readonly InstallationStateInterface $state,
        private readonly RemoteConnectionService $connectionService,
    ) {}

    public function rollback(CustomerInstallation $installation): void
    {
        $this->buildStageMap();
        $this->state->markRollingBack($installation);

        $request = CustomerInstallRequest::fromArray($installation->request_payload ?? []);
        $server = $installation->remoteServer;

        if (!$server) {
            $this->state->markRollbackFailed($installation, 'Remote server record not found');
            return;
        }

        $connection = $this->connectionService->buildDriver($server);

        try {
            $connection->connect();
        } catch (\Throwable $e) {
            $this->state->markRollbackFailed($installation, 'Cannot connect for rollback: ' . $e->getMessage());
            return;
        }

        $ctx = new InstallationContext($installation, $request, $connection);

        // Load pending rollback checkpoints in reverse order (LIFO)
        $checkpoints = CustomerInstallRollback::where('installation_id', $installation->id)
            ->whereNull('executed_at')
            ->orderByDesc('id')
            ->get();

        $allSucceeded = true;
        $haltReason = null;

        foreach ($checkpoints as $checkpoint) {
            $stageName = $checkpoint->stage;

            if (!isset($this->stageMap[$stageName])) {
                $checkpoint->markExecuted();
                continue;
            }

            $stage = $this->stageMap[$stageName];

            if (!$stage->canRollback()) {
                $checkpoint->markExecuted();
                continue;
            }

            try {
                $stage->rollback($ctx, $checkpoint->snapshot);
                $checkpoint->markExecuted();

                $this->state->recordWarning($installation, $stageName, "Rolled back stage: {$stageName}");

            } catch (\Throwable $e) {
                Log::error("CIE Rollback: Failed to rollback stage {$stageName}", ['error' => $e->getMessage()]);
                $allSucceeded = false;

                if ($stage->isCriticalForSafety()) {
                    // Phase 13-M3b — a critical recovery stage (e.g. the database
                    // restore) failed. Replaying the remaining, older checkpoints
                    // — which includes LOCK_TENANT's unlock — would expose a
                    // tenant whose database was never actually restored. Stop
                    // here and leave the tenant locked; that requires manual
                    // recovery, which is safer than serving broken traffic.
                    $haltReason = "Critical rollback stage {$stageName} failed — halting replay to avoid unlocking an inconsistent tenant: " . $e->getMessage();
                    break;
                }
                // Non-critical stage — continue attempting remaining rollbacks
            }
        }

        try {
            $connection->disconnect();
        } catch (\Throwable) {}

        if ($allSucceeded) {
            $this->state->markRolledBack($installation);
        } else {
            $this->state->markRollbackFailed($installation, $haltReason ?? 'One or more rollback steps failed');
        }
    }

    private function buildStageMap(): void
    {
        $stages = [
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
            app(ConfigureDnsStage::class),
            app(ConfigureWebServerStage::class),
            app(ProvisionLicenseStage::class),
            app(InitializeBackupStage::class),
            app(ConfigureMonitoringStage::class),
            app(InstallThemeStage::class),
            app(CustomerReadyStage::class),
            // Phase 13-I — MODE_MODULE_UPDATE stages. Registered here so their
            // rollback checkpoints are actually replayed rather than silently
            // marked executed (see the Phase 13-C finding this guards against).
            app(BackupModuleUpgradeStage::class),
            app(ExtractModulePackageStage::class),
            app(CompleteModuleUpgradeStage::class),
            // Phase 13-J — LockTenantStage's rollback() is the failure-path
            // unlock safety net; registering both keeps the map complete.
            app(LockTenantStage::class),
            app(UnlockTenantStage::class),
        ];

        foreach ($stages as $stage) {
            $this->stageMap[$stage->name()] = $stage;
        }
    }
}
