<?php

namespace Tests\Unit\CustomerInstallation\Provisioning;

use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\Provisioning\CpanelCronLine;
use App\Services\CustomerInstallation\Provisioning\CpanelQueueProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Provisioning\QueueProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\VpsQueueProvisioner;
use RuntimeException;
use Tests\TestCase;

/** Phase 14-F3 — mirrors DatabaseProvisionerFactoryTest / CronProvisionerFactoryTest. */
class QueueProvisionerFactoryTest extends TestCase
{
    private function factory(): QueueProvisionerFactory
    {
        $uapi = new CpanelUapiClient(new CredentialVault());

        return new QueueProvisionerFactory(new VpsQueueProvisioner(), new CpanelQueueProvisioner(new CpanelCronLine($uapi)));
    }

    public function test_ssh_resolves_to_vps_provisioner(): void
    {
        $this->assertInstanceOf(VpsQueueProvisioner::class, $this->factory()->forDriverType(DriverType::SSH));
    }

    public function test_fake_resolves_to_vps_provisioner(): void
    {
        $this->assertInstanceOf(VpsQueueProvisioner::class, $this->factory()->forDriverType(DriverType::FAKE));
    }

    public function test_cpanel_resolves_to_cpanel_provisioner(): void
    {
        $this->assertInstanceOf(CpanelQueueProvisioner::class, $this->factory()->forDriverType(DriverType::CPANEL));
    }

    public function test_unregistered_type_throws_clear_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("No queue provisioner registered for driver type 'directadmin'");

        $this->factory()->forDriverType(DriverType::DIRECTADMIN);
    }
}
