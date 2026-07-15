<?php

namespace Tests\Unit\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\Provisioning\CpanelDatabaseProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Provisioning\DatabaseProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\VpsDatabaseProvisioner;
use App\Services\CustomerInstallation\CredentialVault;
use RuntimeException;
use Tests\TestCase;

/** Phase 14-F2 — mirrors DriverFactoryTest's structure for the new database-provisioner registry. */
class DatabaseProvisionerFactoryTest extends TestCase
{
    private function factory(): DatabaseProvisionerFactory
    {
        $vault = new CredentialVault();

        return new DatabaseProvisionerFactory(
            new VpsDatabaseProvisioner(),
            new CpanelDatabaseProvisioner(new CpanelUapiClient($vault)),
        );
    }

    public function test_ssh_resolves_to_vps_provisioner(): void
    {
        $this->assertInstanceOf(VpsDatabaseProvisioner::class, $this->factory()->forDriverType(DriverType::SSH));
    }

    public function test_fake_resolves_to_vps_provisioner(): void
    {
        // FakeRemoteDriver reports forVps() capabilities — treat it like VPS.
        $this->assertInstanceOf(VpsDatabaseProvisioner::class, $this->factory()->forDriverType(DriverType::FAKE));
    }

    public function test_cpanel_resolves_to_cpanel_provisioner(): void
    {
        $this->assertInstanceOf(CpanelDatabaseProvisioner::class, $this->factory()->forDriverType(DriverType::CPANEL));
    }

    public function test_unregistered_type_throws_clear_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("No database provisioner registered for driver type 'directadmin'");

        $this->factory()->forDriverType(DriverType::DIRECTADMIN);
    }
}
