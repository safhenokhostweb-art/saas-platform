<?php

namespace Tests\Unit\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\Provisioning\CpanelCronLine;
use App\Services\CustomerInstallation\Provisioning\CpanelCronProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Provisioning\CronProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\VpsCronProvisioner;
use RuntimeException;
use Tests\TestCase;

/** Phase 14-F3 — mirrors DatabaseProvisionerFactoryTest's structure. */
class CronProvisionerFactoryTest extends TestCase
{
    private function factory(): CronProvisionerFactory
    {
        $uapi = new CpanelUapiClient(new CredentialVault());

        return new CronProvisionerFactory(new VpsCronProvisioner(), new CpanelCronProvisioner(new CpanelCronLine($uapi)));
    }

    public function test_ssh_resolves_to_vps_provisioner(): void
    {
        $this->assertInstanceOf(VpsCronProvisioner::class, $this->factory()->forDriverType(DriverType::SSH));
    }

    public function test_fake_resolves_to_vps_provisioner(): void
    {
        $this->assertInstanceOf(VpsCronProvisioner::class, $this->factory()->forDriverType(DriverType::FAKE));
    }

    public function test_cpanel_resolves_to_cpanel_provisioner(): void
    {
        $this->assertInstanceOf(CpanelCronProvisioner::class, $this->factory()->forDriverType(DriverType::CPANEL));
    }

    public function test_unregistered_type_throws_clear_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("No cron provisioner registered for driver type 'directadmin'");

        $this->factory()->forDriverType(DriverType::DIRECTADMIN);
    }
}
