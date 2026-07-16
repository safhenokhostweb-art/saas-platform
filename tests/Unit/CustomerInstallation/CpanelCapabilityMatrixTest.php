<?php

namespace Tests\Unit\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Contracts\CustomerPackageInterface;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Contracts\InstallationStageInterface;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Provisioning\CpanelCronLine;
use App\Services\CustomerInstallation\Provisioning\CpanelCronProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelDatabaseProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelQueueProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Provisioning\CronProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\DatabaseProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\QueueProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\VpsCronProvisioner;
use App\Services\CustomerInstallation\Provisioning\VpsDatabaseProvisioner;
use App\Services\CustomerInstallation\Provisioning\VpsQueueProvisioner;
use App\Services\CustomerInstallation\Ssl\ArvanCdnDomainDecider;
use App\Services\CustomerInstallation\Ssl\NginxVhostWriter;
use App\Services\CustomerInstallation\Ssl\VpsSslProvisioner;
use App\Services\CustomerInstallation\Stages\ConfigureMonitoringStage;
use App\Services\CustomerInstallation\Stages\ConfigureQueueStage;
use App\Services\CustomerInstallation\Stages\ConfigureSchedulerStage;
use App\Services\CustomerInstallation\Stages\ConfigureStorageStage;
use App\Services\CustomerInstallation\Stages\ConfigureWebServerStage;
use App\Services\CustomerInstallation\Stages\CreateDatabaseStage;
use App\Services\CustomerInstallation\Stages\ExtractPackageStage;
use App\Services\CustomerInstallation\Stages\GenerateEnvironmentStage;
use App\Services\CustomerInstallation\Stages\UploadPackageStage;
use App\Services\CustomerInstallation\StageCapabilityGate;
use Tests\TestCase;

/**
 * Phase 14-F5 — Part 5: the complete cPanel capability matrix, verified
 * directly rather than asserted in prose. "PASS" means StageCapabilityGate
 * would let CustomerInstallationOrchestrator proceed to call execute();
 * "SKIP" means the gate reports the capability missing but the stage
 * declares isCapabilityRequiredToProceed()=false, so the orchestrator logs
 * a warning and continues rather than failing the installation.
 */
class CpanelCapabilityMatrixTest extends TestCase
{
    private function gate(): StageCapabilityGate
    {
        return new StageCapabilityGate();
    }

    private function cpanelDriver(): DeploymentDriverInterface
    {
        $server = new CustomerRemoteServer([
            'type' => 'cpanel', 'host' => 'box.example.test',
            'username' => 'cust1', 'auth_method' => 'password',
        ]);

        return new CpanelDeploymentDriver($server, new CredentialVault());
    }

    /** @return array<string, InstallationStageInterface> */
    private function allStages(): array
    {
        $vault = new CredentialVault();
        $uapi = new CpanelUapiClient($vault);

        $databaseFactory = new DatabaseProvisionerFactory(
            new VpsDatabaseProvisioner(),
            new CpanelDatabaseProvisioner($uapi),
        );
        $cronFactory = new CronProvisionerFactory(
            new VpsCronProvisioner(),
            new CpanelCronProvisioner(new CpanelCronLine($uapi)),
        );
        $queueFactory = new QueueProvisionerFactory(
            new VpsQueueProvisioner(),
            new CpanelQueueProvisioner(new CpanelCronLine($uapi)),
        );

        return [
            'UploadPackage'       => new UploadPackageStage($this->createStub(CustomerPackageInterface::class)),
            'ExtractPackage'      => new ExtractPackageStage(),
            'GenerateEnvironment' => new GenerateEnvironmentStage($vault),
            'CreateDatabase'      => new CreateDatabaseStage($databaseFactory),
            'ConfigureWebServer'  => new ConfigureWebServerStage(new NginxVhostWriter(new ArvanCdnDomainDecider(), new VpsSslProvisioner())),
            'ConfigureScheduler'  => new ConfigureSchedulerStage($cronFactory),
            'ConfigureQueue'      => new ConfigureQueueStage($queueFactory),
            'ConfigureStorage'    => new ConfigureStorageStage(),
            'ConfigureMonitoring' => new ConfigureMonitoringStage(),
        ];
    }

    public function test_complete_cpanel_capability_matrix(): void
    {
        $expected = [
            'UploadPackage'       => 'PASS',
            'ExtractPackage'      => 'PASS',
            'GenerateEnvironment' => 'PASS',
            'CreateDatabase'      => 'PASS',
            'ConfigureWebServer'  => 'SKIP',
            'ConfigureScheduler'  => 'PASS',
            'ConfigureQueue'      => 'PASS',
            'ConfigureStorage'    => 'PASS',
            'ConfigureMonitoring' => 'PASS',
        ];

        $driver = $this->cpanelDriver();
        $gate = $this->gate();
        $stages = $this->allStages();

        foreach ($expected as $name => $expectedOutcome) {
            $stage = $stages[$name];
            $missing = $gate->missingCapabilities($stage, $driver);

            if (empty($missing)) {
                $actual = 'PASS';
            } elseif (!$stage->isCapabilityRequiredToProceed()) {
                $actual = 'SKIP';
            } else {
                $actual = 'FAIL';
            }

            $this->assertSame(
                $expectedOutcome,
                $actual,
                "{$name} on cPanel: expected {$expectedOutcome}, got {$actual}"
                . (empty($missing) ? '' : ' (missing: ' . implode(',', $missing) . ')')
            );
        }
    }
}
