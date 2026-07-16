<?php

namespace Tests\Unit\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Contracts\InstallationStageInterface;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
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
use App\Services\CustomerInstallation\Stages\AbstractStage;
use App\Services\CustomerInstallation\Stages\ConfigureQueueStage;
use App\Services\CustomerInstallation\Stages\ConfigureSchedulerStage;
use App\Services\CustomerInstallation\Stages\ConfigureStorageStage;
use App\Services\CustomerInstallation\Stages\ConfigureWebServerStage;
use App\Services\CustomerInstallation\Stages\CreateDatabaseStage;
use App\Services\CustomerInstallation\Stages\UploadPackageStage;
use App\Services\CustomerInstallation\StageCapabilityGate;
use Tests\TestCase;

/**
 * Phase 14-E — StageCapabilityGate is the single source of truth for
 * "can this stage actually run against this driver?", consulted by
 * CustomerInstallationOrchestrator before every stage's execute(). These
 * tests exercise the gate directly (pure, no DB/queue needed) plus the
 * specific requiredCapabilities()/isCapabilityRequiredToProceed()
 * declarations added to the real Stage classes this phase.
 */
class StageCapabilityTest extends TestCase
{
    private function gate(): StageCapabilityGate
    {
        return new StageCapabilityGate();
    }

    /** capabilities() = DriverCapabilities::forVps() — everything true. */
    private function vpsDriver(): DeploymentDriverInterface
    {
        return new FakeRemoteDriver();
    }

    /** capabilities() = DriverCapabilities::forCpanel() — limited. */
    private function cpanelDriver(): DeploymentDriverInterface
    {
        $server = new CustomerRemoteServer([
            'type' => 'cpanel', 'host' => 'box.example.test',
            'username' => 'cust1', 'auth_method' => 'password',
        ]);

        return new CpanelDeploymentDriver($server, new CredentialVault());
    }

    private function cronProvisionerFactory(): CronProvisionerFactory
    {
        $uapi = new CpanelUapiClient(new CredentialVault());

        return new CronProvisionerFactory(new VpsCronProvisioner(), new CpanelCronProvisioner(new CpanelCronLine($uapi)));
    }

    private function queueProvisionerFactory(): QueueProvisionerFactory
    {
        $uapi = new CpanelUapiClient(new CredentialVault());

        return new QueueProvisionerFactory(new VpsQueueProvisioner(), new CpanelQueueProvisioner(new CpanelCronLine($uapi)));
    }

    private function fakeStage(array $requiredCapabilities): InstallationStageInterface
    {
        return new class($requiredCapabilities) extends AbstractStage {
            public function __construct(private readonly array $required) {}
            public function name(): string { return 'FAKE_STAGE'; }
            public function label(): string { return 'Fake'; }
            public function execute(InstallationContext $ctx): StageResult
            {
                return StageResult::success($this->name(), [], [], 0);
            }
            public function requiredCapabilities(): array { return $this->required; }
        };
    }

    // ─── ✓ VPS همه capability دارد ────────────────────────────────────────────

    public function test_vps_driver_satisfies_every_capability_used_in_this_pipeline(): void
    {
        $driver = $this->vpsDriver();
        $gate = $this->gate();

        foreach (['supportsDatabaseCreation', 'supportsWebServerConfiguration', 'supportsSupervisor', 'supportsCron', 'supportsQueueWorker', 'supportsStorageLink'] as $capability) {
            $stage = $this->fakeStage([$capability]);
            $this->assertTrue($gate->isSatisfied($stage, $driver), "VPS should satisfy {$capability}");
            $this->assertSame([], $gate->missingCapabilities($stage, $driver));
        }
    }

    // ─── ✓ cPanel capability محدود دارد ───────────────────────────────────────

    public function test_cpanel_driver_has_limited_capabilities(): void
    {
        $driver = $this->cpanelDriver();
        $gate = $this->gate();

        // These cPanel genuinely lacks in the current pipeline implementation.
        foreach (['supportsWebServerConfiguration', 'supportsSupervisor'] as $capability) {
            $stage = $this->fakeStage([$capability]);
            $this->assertFalse($gate->isSatisfied($stage, $driver), "cPanel should NOT satisfy {$capability}");
            $this->assertSame([$capability], $gate->missingCapabilities($stage, $driver));
        }

        // cPanel genuinely has these (its own cron/SSL mechanisms, real
        // database provisioning via UAPI as of Phase 14-F2, a cron-based
        // queue worker as of Phase 14-F3, and — since it connects over SSH
        // (Phase 14-C) — artisan storage:link as of Phase 14-F5).
        foreach (['supportsCron', 'supportsScheduler', 'supportsSSL', 'supportsDatabaseCreation', 'supportsQueueWorker', 'supportsStorageLink'] as $capability) {
            $stage = $this->fakeStage([$capability]);
            $this->assertTrue($gate->isSatisfied($stage, $driver), "cPanel should satisfy {$capability}");
        }
    }

    // ─── ✓ Optional stage skip می‌شود ─────────────────────────────────────────

    /**
     * Phase 14-F3 — gates on supportsQueueWorker (not supportsSupervisor),
     * which is now true for cPanel too (cron-based worker via
     * CpanelQueueProvisioner). This replaces the pre-14-F3 version of this
     * test, which asserted cPanel was missing the capability — it isn't,
     * anymore, even though supportsSupervisor itself correctly stays false.
     */
    public function test_configure_queue_stage_is_satisfied_on_both_vps_and_cpanel(): void
    {
        $stage = new ConfigureQueueStage($this->queueProvisionerFactory());

        $this->assertFalse($stage->isCapabilityRequiredToProceed(), 'Queue worker is optional infra — must skip, not fail if ever unsatisfied');
        $this->assertTrue($this->gate()->isSatisfied($stage, $this->cpanelDriver()), 'cPanel now runs a cron-based worker (Phase 14-F3)');
        $this->assertTrue($this->gate()->isSatisfied($stage, $this->vpsDriver()), 'VPS must still run this stage unchanged');
    }

    public function test_configure_scheduler_stage_is_optional(): void
    {
        $stage = new ConfigureSchedulerStage($this->cronProvisionerFactory());

        // supportsCron is true even for cPanel (its own Cron API) — the
        // gate lets it through; as of Phase 14-F3 CpanelCronProvisioner
        // genuinely installs the line via UAPI instead of the old
        // write-failure degrade.
        $this->assertTrue($this->gate()->isSatisfied($stage, $this->cpanelDriver()));
        $this->assertFalse($stage->isCapabilityRequiredToProceed());
    }

    public function test_configure_web_server_stage_is_missing_on_cpanel_and_optional(): void
    {
        $stage = new ConfigureWebServerStage(new NginxVhostWriter(new ArvanCdnDomainDecider(), new VpsSslProvisioner()));

        $this->assertSame(['supportsWebServerConfiguration'], $this->gate()->missingCapabilities($stage, $this->cpanelDriver()));
        $this->assertFalse($stage->isCapabilityRequiredToProceed(), 'cPanel/WHM manages vhosts automatically — must skip, not fail');
        $this->assertTrue($this->gate()->isSatisfied($stage, $this->vpsDriver()), 'VPS must still run this stage unchanged');
    }

    /**
     * Phase 14-F5 — ConfigureStorageStage had no capability gate at all
     * before this phase (always ran unconditionally). Now that
     * supportsStorageLink correctly reports true for cPanel too (it always
     * worked there via SSH — Phase 14-C — the flag was just wrong), the
     * gate is satisfied on both drivers and never blocks either.
     */
    public function test_configure_storage_stage_is_satisfied_on_both_vps_and_cpanel(): void
    {
        $stage = new ConfigureStorageStage();

        $this->assertSame(['supportsStorageLink'], $stage->requiredCapabilities());
        $this->assertFalse($stage->isCapabilityRequiredToProceed(), 'A missing storage symlink is not fatal — must skip, not fail if ever unsatisfied');
        $this->assertTrue($this->gate()->isSatisfied($stage, $this->cpanelDriver()), 'cPanel connects over SSH (Phase 14-C) — storage:link needs no root');
        $this->assertTrue($this->gate()->isSatisfied($stage, $this->vpsDriver()), 'VPS must still run this stage unchanged');
    }

    // ─── ✓ Required capability missing باعث fail می‌شود ──────────────────────

    public function test_a_required_and_missing_capability_is_reported_by_the_gate(): void
    {
        // supportsSupervisor is still false for cPanel (Phase 14-F2 only
        // implemented database provisioning, not Supervisor — impossible on
        // shared hosting regardless). fakeStage()'s isCapabilityRequiredToProceed()
        // defaults to true (AbstractStage), so this demonstrates the generic
        // "required + missing" gate outcome without depending on any one
        // real Stage's current capability status.
        $stage = $this->fakeStage(['supportsSupervisor']);

        $this->assertSame(['supportsSupervisor'], $this->gate()->missingCapabilities($stage, $this->cpanelDriver()));
        $this->assertTrue($stage->isCapabilityRequiredToProceed());
        $this->assertFalse($this->gate()->isSatisfied($stage, $this->cpanelDriver()));
    }

    /**
     * Phase 14-F2 — CreateDatabaseStage now resolves a real
     * CpanelDatabaseProvisioner (cPanel UAPI), so it is no longer an example
     * of "required + missing" on cPanel — it is now satisfied there too,
     * same as on VPS. This replaces the pre-14-F2 version of this test,
     * which asserted the opposite.
     */
    public function test_create_database_stage_is_now_satisfied_on_cpanel(): void
    {
        $stage = new CreateDatabaseStage(new DatabaseProvisionerFactory(
            new VpsDatabaseProvisioner(),
            new CpanelDatabaseProvisioner(new CpanelUapiClient(new CredentialVault())),
        ));

        $this->assertTrue($stage->isCapabilityRequiredToProceed(), 'A site cannot run without a database — must fail, not skip, if this were ever unsatisfied');
        $this->assertTrue($this->gate()->isSatisfied($stage, $this->cpanelDriver()), 'cPanel now provisions databases via UAPI (Phase 14-F2)');
        $this->assertTrue($this->gate()->isSatisfied($stage, $this->vpsDriver()), 'VPS must still run this stage unchanged');
    }

    // ─── Backward compatibility (Section 5) ──────────────────────────────────

    public function test_stages_with_no_declared_requirements_are_never_gated(): void
    {
        // e.g. UploadPackageStage/VerifyPackageStage/ExtractPackageStage/
        // ConfigureDnsStage — unchanged this phase, must run on every driver.
        $stage = new UploadPackageStage($this->createStub(\App\Services\CustomerInstallation\Contracts\CustomerPackageInterface::class));

        $this->assertSame([], $stage->requiredCapabilities());
        $this->assertTrue($this->gate()->isSatisfied($stage, $this->cpanelDriver()));
        $this->assertTrue($this->gate()->isSatisfied($stage, $this->vpsDriver()));
    }

    public function test_default_is_capability_required_to_proceed_is_true(): void
    {
        // AbstractStage's safe default: a stage that declares a requirement
        // but doesn't override isCapabilityRequiredToProceed() fails rather
        // than silently skipping an operation nobody flagged as optional.
        $stage = $this->fakeStage(['supportsDatabaseCreation']);
        $this->assertTrue($stage->isCapabilityRequiredToProceed());
    }
}
