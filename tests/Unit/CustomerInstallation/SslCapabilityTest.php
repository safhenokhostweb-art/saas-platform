<?php

namespace Tests\Unit\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\DirectAdminDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\DockerDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Drivers\SftpDeploymentDriver;
use App\Services\CustomerInstallation\Ssl\ArvanCdnDomainDecider;
use App\Services\CustomerInstallation\Ssl\CpanelSslProvisioner;
use App\Services\CustomerInstallation\Ssl\NginxVhostWriter;
use App\Services\CustomerInstallation\Ssl\SslProvisionerFactory;
use App\Services\CustomerInstallation\Ssl\VpsSslProvisioner;
use App\Services\CustomerInstallation\Stages\IssueSslStage;
use App\Services\CustomerInstallation\StageCapabilityGate;
use Tests\TestCase;

/**
 * Phase 15-B1 — mirrors StageCapabilityTest's structure for the new
 * IssueSslStage: confirms StageCapabilityGate's real gating outcome per
 * driver for the supportsSSL capability (reused, not a new capability flag
 * — see IssueSslStage's docblock), and that SslProvisionerFactory throws
 * loudly rather than silently for any driver type it doesn't recognize.
 */
class SslCapabilityTest extends TestCase
{
    private function gate(): StageCapabilityGate
    {
        return new StageCapabilityGate();
    }

    private function issueSslStage(): IssueSslStage
    {
        return new IssueSslStage(
            new SslProvisionerFactory(new VpsSslProvisioner(), $this->createStub(CpanelSslProvisioner::class)),
            new ArvanCdnDomainDecider(),
            new NginxVhostWriter(new ArvanCdnDomainDecider(), new VpsSslProvisioner()),
        );
    }

    public function test_vps_and_fake_drivers_satisfy_supports_ssl(): void
    {
        $stage = $this->issueSslStage();
        $gate = $this->gate();

        $this->assertTrue($gate->isSatisfied($stage, new FakeRemoteDriver()));
    }

    public function test_cpanel_driver_satisfies_supports_ssl(): void
    {
        $server = new CustomerRemoteServer([
            'type' => 'cpanel', 'host' => 'box.example.test',
            'username' => 'cust1', 'auth_method' => 'password',
        ]);
        $driver = new CpanelDeploymentDriver($server, new CredentialVault());

        $this->assertTrue($this->gate()->isSatisfied($this->issueSslStage(), $driver));
    }

    public function test_docker_driver_does_not_satisfy_supports_ssl_and_stage_skips_not_fails(): void
    {
        $target = new CustomerRemoteServer(['type' => 'docker', 'host' => 'docker.example.test', 'username' => 'root']);
        $driver = new DockerDeploymentDriver($target, new CredentialVault());
        $stage = $this->issueSslStage();

        $this->assertSame(['supportsSSL'], $this->gate()->missingCapabilities($stage, $driver));
        $this->assertFalse($stage->isCapabilityRequiredToProceed());
    }

    public function test_sftp_driver_does_not_satisfy_supports_ssl(): void
    {
        $target = new CustomerRemoteServer(['type' => 'sftp', 'host' => 'sftp.example.test', 'username' => 'cust1']);
        $driver = new SftpDeploymentDriver($target, new CredentialVault());

        $this->assertSame(['supportsSSL'], $this->gate()->missingCapabilities($this->issueSslStage(), $driver));
    }

    /**
     * DirectAdmin declares supportsSSL=true at the metadata level (Part 1 of
     * the Phase 15-A audit), but the driver itself is an unimplemented
     * architecture stub (DriverType::isImplemented()=false) — it can never
     * reach a live connection at all, so this stage is unreachable for it in
     * practice regardless of the capability flag's value.
     */
    public function test_directadmin_driver_declares_supports_ssl_true_but_driver_is_unimplemented(): void
    {
        $target = new CustomerRemoteServer(['type' => 'directadmin', 'host' => 'da.example.test', 'username' => 'cust1']);
        $driver = new DirectAdminDeploymentDriver($target, new CredentialVault());

        $this->assertTrue($this->gate()->isSatisfied($this->issueSslStage(), $driver));
        $this->assertFalse(\App\Services\CustomerInstallation\Enums\DriverType::DIRECTADMIN->isImplemented());
    }

    public function test_ssl_provisioner_factory_throws_for_an_unregistered_driver_type(): void
    {
        $factory = new SslProvisionerFactory(new VpsSslProvisioner(), $this->createStub(CpanelSslProvisioner::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No SSL provisioner registered');

        $factory->forDriverType(\App\Services\CustomerInstallation\Enums\DriverType::DIRECTADMIN);
    }
}
