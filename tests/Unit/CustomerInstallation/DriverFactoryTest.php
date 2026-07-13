<?php

namespace Tests\Unit\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Models\DeploymentTarget;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\DriverFactory;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Drivers\SshRemoteDriver;
use App\Services\CustomerInstallation\Enums\DriverType;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 14-B1 — DriverFactory is now the single resolve point for every
 * deployment driver, replacing the two parallel registries
 * (customer_installation.drivers / .deployment_drivers) that disagreed
 * about which server types were registered — the root cause of the
 * "No driver registered for server type: cpanel" incident (Installation #44).
 */
class DriverFactoryTest extends TestCase
{
    private function factory(): DriverFactory
    {
        return new DriverFactory(new CredentialVault());
    }

    public function test_resolves_ssh_driver_class(): void
    {
        $this->assertSame(SshRemoteDriver::class, $this->factory()->resolve(DriverType::SSH));
    }

    public function test_resolves_fake_driver_class(): void
    {
        $this->assertSame(FakeRemoteDriver::class, $this->factory()->resolve(DriverType::FAKE));
    }

    public function test_existing_ssh_installations_still_resolve_correctly(): void
    {
        $server = new CustomerRemoteServer([
            'type' => 'ssh', 'host' => 'example.test', 'port' => 22,
            'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $driver = $this->factory()->buildForServer($server);

        $this->assertInstanceOf(SshRemoteDriver::class, $driver);
        $this->assertSame(DriverType::SSH, $driver->driverType());
    }

    public function test_build_for_server_with_fake_type_returns_fake_driver(): void
    {
        $server = new CustomerRemoteServer(['type' => 'fake']);

        $this->assertInstanceOf(FakeRemoteDriver::class, $this->factory()->buildForServer($server));
    }

    public function test_build_for_target_with_ssh_type_still_resolves(): void
    {
        // Phase 14-B1 — DeploymentTarget path (buildForTarget) has no
        // production caller, but must keep resolving through the same
        // unified registry as the legacy CustomerRemoteServer path.
        $target = new DeploymentTarget([
            'connection_driver' => 'ssh', 'host' => 'example.test', 'port' => 22,
            'username' => 'deploy', 'auth_method' => 'ssh_key',
        ]);

        $this->assertInstanceOf(SshRemoteDriver::class, $this->factory()->buildForTarget($target));
    }

    public function test_unknown_driver_type_throws_not_registered_exception(): void
    {
        config(['customer_installation.drivers' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No driver registered for server type: ssh');

        $this->factory()->resolve(DriverType::SSH);
    }

    public function test_implemented_false_driver_cannot_be_selected_via_resolve(): void
    {
        // Phase 14-C flipped cpanel to isImplemented()=true, so this test now
        // uses directadmin — still an architecture stub — as the "registered
        // but not implemented" example. resolve() must still reject it
        // because DriverType::isImplemented() is false, and the error must
        // say "not yet implemented", not "not registered".
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not yet implemented');

        $this->factory()->resolve(DriverType::DIRECTADMIN);
    }

    public function test_implemented_false_driver_cannot_be_selected_via_build_for_server(): void
    {
        $server = new CustomerRemoteServer(['type' => 'directadmin', 'host' => 'box.example.test']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not yet implemented');

        $this->factory()->buildForServer($server);
    }

    public function test_directadmin_class_is_registered_even_though_not_implemented(): void
    {
        // Confirms the registry itself is unified: directadmin appears in
        // the single 'drivers' config key with a real class mapping, so a
        // future implementation only needs DriverType::isImplemented()
        // flipped — not a second registry entry added anywhere.
        $this->assertSame(
            \App\Services\CustomerInstallation\Drivers\DirectAdminDeploymentDriver::class,
            config('customer_installation.drivers')['directadmin'] ?? null
        );
    }

    public function test_cpanel_is_implemented_and_registered_as_of_phase_14_c(): void
    {
        $this->assertTrue(DriverType::CPANEL->isImplemented());
        $this->assertSame(
            CpanelDeploymentDriver::class,
            config('customer_installation.drivers')['cpanel'] ?? null
        );
    }
}
