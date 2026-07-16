<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Domain\CpanelDomainProvisioner;
use App\Services\CustomerInstallation\Domain\Dto\DomainResult;
use App\Services\CustomerInstallation\Domain\DomainProvisionerFactory;
use App\Services\CustomerInstallation\Domain\VpsDomainProvisioner;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Stages\ProvisionDomainStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 15-D — ProvisionDomainStage is exercised against spy provisioners so
 * these tests prove the STAGE's own decision logic (capability requirement,
 * created_by_installer bookkeeping, rollback ownership guard) independent of
 * whether the real cPanel UAPI calls succeed — those mechanisms are covered
 * by CpanelDomainProvisionerTest.
 */
class ProvisionDomainStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function context(FakeRemoteDriver $driver, string $domain = 'example.com'): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Domain Stage Co',
            'customer_email'    => 'domain@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => [
                'company' => 'Domain Stage Co', 'owner_name' => 'Owner', 'email' => 'domain@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => $domain, 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => true, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    private function spyVpsProvisioner(DomainResult $toReturn, array &$calls): VpsDomainProvisioner
    {
        return new class($toReturn, $calls) extends VpsDomainProvisioner {
            public function __construct(private readonly DomainResult $result, private array &$calls) {}
            public function createDomain(InstallationContext $ctx, string $domain): DomainResult
            {
                $this->calls[] = ['createDomain', $domain];
                return $this->result;
            }
            public function removeDomain(InstallationContext $ctx, string $domain, array $snapshot): void
            {
                $this->calls[] = ['removeDomain', $domain, $snapshot];
            }
        };
    }

    private function spyCpanelProvisioner(DomainResult $toReturn, array &$calls): CpanelDomainProvisioner
    {
        return new class($toReturn, $calls) extends CpanelDomainProvisioner {
            public function __construct(private readonly DomainResult $result, private array &$calls) {}
            public function createDomain(InstallationContext $ctx, string $domain): DomainResult
            {
                $this->calls[] = ['createDomain', $domain];
                return $this->result;
            }
            public function removeDomain(InstallationContext $ctx, string $domain, array $snapshot): void
            {
                $this->calls[] = ['removeDomain', $domain, $snapshot];
            }
        };
    }

    public function test_required_capabilities_and_is_essential(): void
    {
        $calls = [];
        $stage = new ProvisionDomainStage(new DomainProvisionerFactory(
            $this->spyVpsProvisioner(DomainResult::skippedForDriver('n/a'), $calls),
            $this->createStub(CpanelDomainProvisioner::class),
        ));

        $this->assertSame(['supportsDomainCreation'], $stage->requiredCapabilities());
        $this->assertTrue($stage->isCapabilityRequiredToProceed(), 'No acceptable degraded state without a resolved domain — must fail, not skip, if unsatisfied');
    }

    public function test_vps_returns_skip_success_without_creating_anything(): void
    {
        $calls = [];
        $stage = new ProvisionDomainStage(new DomainProvisionerFactory(
            $this->spyVpsProvisioner(DomainResult::skippedForDriver('nginx vhost stage covers this'), $calls),
            $this->createStub(CpanelDomainProvisioner::class),
        ));

        $ctx = $this->context(new FakeRemoteDriver());
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame('skipped_success', $result->output['status']);
        $this->assertFalse($result->output['created_by_installer']);
        $this->assertSame([['createDomain', 'example.com']], $calls);
    }

    public function test_cpanel_created_domain_marks_created_by_installer_true(): void
    {
        $calls = [];
        $stage = new ProvisionDomainStage(new DomainProvisionerFactory(
            $this->createStub(VpsDomainProvisioner::class),
            $this->spyCpanelProvisioner(DomainResult::created('/home/johndoe/public_html/shop', 'subdomain', ['subdomain_prefix' => 'shop']), $calls),
        ));

        $ctx = $this->context(new FakeRemoteDriver(driverType: \App\Services\CustomerInstallation\Enums\DriverType::CPANEL), 'shop.example.com');
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame('created', $result->output['status']);
        $this->assertTrue($result->output['created_by_installer']);
        $this->assertSame('/home/johndoe/public_html/shop', $result->output['document_root']);
    }

    public function test_cpanel_pre_existing_domain_marks_created_by_installer_false(): void
    {
        $calls = [];
        $stage = new ProvisionDomainStage(new DomainProvisionerFactory(
            $this->createStub(VpsDomainProvisioner::class),
            $this->spyCpanelProvisioner(DomainResult::alreadyExists('/home/johndoe/public_html', 'main'), $calls),
        ));

        $ctx = $this->context(new FakeRemoteDriver(driverType: \App\Services\CustomerInstallation\Enums\DriverType::CPANEL));
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame('already_exists', $result->output['status']);
        $this->assertFalse($result->output['created_by_installer'], 'A domain this installer did not create must never be marked as installer-owned');
    }

    public function test_api_failure_returns_a_real_stage_failure(): void
    {
        $calls = [];
        $stage = new ProvisionDomainStage(new DomainProvisionerFactory(
            $this->createStub(VpsDomainProvisioner::class),
            $this->spyCpanelProvisioner(DomainResult::failed('addon domain limit reached'), $calls),
        ));

        $ctx = $this->context(new FakeRemoteDriver(driverType: \App\Services\CustomerInstallation\Enums\DriverType::CPANEL));
        $result = $stage->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('addon domain limit reached', $result->error);
    }

    public function test_rollback_removes_domain_only_when_created_by_installer(): void
    {
        $calls = [];
        $stage = new ProvisionDomainStage(new DomainProvisionerFactory(
            $this->createStub(VpsDomainProvisioner::class),
            $this->spyCpanelProvisioner(DomainResult::skippedForDriver('unused'), $calls),
        ));

        $ctx = $this->context(new FakeRemoteDriver(driverType: \App\Services\CustomerInstallation\Enums\DriverType::CPANEL));

        $stage->rollback($ctx, ['domain' => 'shop.example.com', 'created_by_installer' => true, 'type' => 'subdomain']);

        $this->assertSame([['removeDomain', 'shop.example.com', ['domain' => 'shop.example.com', 'created_by_installer' => true, 'type' => 'subdomain']]], $calls);
    }

    public function test_rollback_never_removes_a_pre_existing_domain(): void
    {
        $calls = [];
        $stage = new ProvisionDomainStage(new DomainProvisionerFactory(
            $this->createStub(VpsDomainProvisioner::class),
            $this->spyCpanelProvisioner(DomainResult::skippedForDriver('unused'), $calls),
        ));

        $ctx = $this->context(new FakeRemoteDriver(driverType: \App\Services\CustomerInstallation\Enums\DriverType::CPANEL));

        $stage->rollback($ctx, ['domain' => 'johndoe.example.com', 'created_by_installer' => false, 'type' => 'main']);

        $this->assertSame([], $calls, 'A domain not created by this installer must never be passed to removeDomain()');
    }

    public function test_pre_snapshot_defaults_to_not_installer_created(): void
    {
        $stage = new ProvisionDomainStage(new DomainProvisionerFactory(
            new VpsDomainProvisioner(),
            $this->createStub(CpanelDomainProvisioner::class),
        ));

        $ctx = $this->context(new FakeRemoteDriver(), 'safety-default.example.com');
        $snapshot = $stage->buildPreSnapshot($ctx);

        $this->assertFalse($snapshot['created_by_installer'], 'A crash before execute() completes must never default to "safe to delete"');
    }
}
