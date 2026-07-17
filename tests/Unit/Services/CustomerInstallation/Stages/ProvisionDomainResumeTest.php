<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Domain\CpanelDomainProvisioner;
use App\Services\CustomerInstallation\Domain\DomainProvisionerFactory;
use App\Services\CustomerInstallation\Domain\VpsDomainProvisioner;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Stages\ProvisionDomainStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 15-F — Part 4/7: originally documented a real edge case (reported,
 * NOT fixed — Phase 15-F was audit-only per its own instructions).
 *
 * Scenario: PROVISION_DOMAIN's remote UAPI call (SubDomain::addsubdomain)
 * succeeds, but the process crashes/times out BEFORE
 * CustomerInstallationOrchestrator records the stage as complete (a narrow
 * but non-zero window — see recordStageComplete()'s position at the very
 * end of the per-stage loop). On resume, hasCompletedStage('PROVISION_DOMAIN')
 * is false, so the stage re-runs. CpanelDomainProvisioner::createDomain()'s
 * own idempotency check correctly finds the domain now exists — but reports
 * it as DomainResult::alreadyExists(), which without the fix below
 * ProvisionDomainStage would map to created_by_installer=false.
 *
 * Phase 15-G — Part 3: fixed via an ownership marker
 * (installation.metadata['provision_domain_created_by_installer']) written
 * immediately after a successful create — before any crash-prone
 * bookkeeping — so a resumed run can tell "the live query finds this domain
 * because I created it moments ago" apart from "the customer already had
 * this domain". This test is updated in place (per its own prior comment)
 * to run BOTH attempts against the SAME context/installation and verify the
 * fix: the second (resumed) attempt now correctly reports
 * created_by_installer=true, and rollback removes it.
 */
class ProvisionDomainResumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
        // Phase 15-G — Part 1: any URL left unmocked in a test's Http::fake()
        // array falls through to a REAL network attempt (Laravel's stray-
        // request default), which the new retry loop then retries against a
        // real timeout — keep that cheap here regardless.
        config(['customer_installation.cpanel.uapi_retry_delay' => 0]);
    }

    public function test_a_domain_created_by_an_earlier_crashed_attempt_is_correctly_attributed_to_the_installer_on_resume(): void
    {
        // Attempt 1: domain does not exist yet -> created via addsubdomain.
        // Attempt 2 (resume): the live query now finds it, since attempt 1's
        // remote call genuinely succeeded before the simulated crash.
        Http::fake([
            '*DomainInfo/domains_data*' => Http::sequence()
                ->push(['status' => 1, 'data' => [
                    'main_domain' => 'johndoe.example.com',
                    'domains'     => [],
                ]], 200)
                ->push(['status' => 1, 'data' => [
                    'main_domain' => 'johndoe.example.com',
                    'domains'     => [
                        ['domain' => 'shop.johndoe.example.com', 'domain_type' => 'sub', 'documentroot' => '/home/johndoe/public_html/shop'],
                    ],
                ]], 200),
            '*SubDomain/addsubdomain*' => Http::response(['status' => 1, 'data' => []], 200),
            '*SubDomain/delsubdomain*' => Http::response(['status' => 1, 'data' => []], 200),
        ]);

        $vault  = new CredentialVault();
        $server = CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box106.parsvds.com',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => $vault->encrypt('the-account-password'),
        ]);
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Resume Co',
            'customer_email'    => 'resume@example.com',
            'customer_domain'   => 'shop.johndoe.example.com',
            'request_payload'   => [
                'company' => 'Resume Co', 'owner_name' => 'Owner', 'email' => 'resume@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'shop.johndoe.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => true, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $driver  = new CpanelDeploymentDriver($server, $vault);
        $ctx     = new InstallationContext($installation, $request, $driver);

        $stage = new ProvisionDomainStage(new DomainProvisionerFactory(
            new VpsDomainProvisioner(),
            new CpanelDomainProvisioner(new CpanelUapiClient($vault)),
        ));

        // Attempt 1 — the domain does not exist yet: created for real.
        $first = $stage->execute($ctx);
        $this->assertTrue($first->success);
        $this->assertSame('created', $first->output['status']);
        $this->assertTrue($first->output['created_by_installer']);

        // Attempt 2 (resume) — a fresh execute() call on the SAME
        // installation/context, simulating the orchestrator re-running this
        // stage because the crash happened after attempt 1's remote call
        // succeeded but before recordStageComplete() ran. The live query now
        // finds the domain; the fix in ProvisionDomainStage must recognize
        // it as this installer's own doing via the metadata marker written
        // during attempt 1.
        $second = $stage->execute($ctx);

        $this->assertTrue($second->success);
        $this->assertSame('already_exists', $second->output['status']);
        $this->assertTrue(
            $second->output['created_by_installer'],
            'Phase 15-G Part 3 fix: a domain created by an earlier attempt of THIS SAME '
            . 'installer must still be attributed to the installer on resume, via the '
            . 'ownership marker persisted in installation.metadata immediately after creation.'
        );

        // Consequence of the fix: rollback now correctly removes it.
        $stage->rollback($ctx, $second->rollbackSnapshot);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'delsubdomain'));
    }

    /**
     * Phase 15-G — Part 3: the fix must not create a false positive. A
     * domain that genuinely pre-dates this installation (no earlier
     * successful "created" attempt by THIS installer, ever) must still be
     * reported as created_by_installer=false and must never be rolled back —
     * unchanged from the pre-existing behavior this file's other test always
     * verified. Covers the main-domain case; the analogous sub/addon case is
     * already covered by FullCpanelDeploymentFlowTest.
     */
    public function test_a_genuinely_pre_existing_domain_is_never_misattributed_to_the_installer(): void
    {
        Http::fake(['*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
            'main_domain' => 'johndoe.example.com',
            'domains'     => [],
        ]], 200)]);

        $vault  = new CredentialVault();
        $server = CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box106.parsvds.com',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => $vault->encrypt('the-account-password'),
        ]);
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Pre Existing Co',
            'customer_email'    => 'preexisting@example.com',
            'customer_domain'   => 'johndoe.example.com',
            'request_payload'   => [
                'company' => 'Pre Existing Co', 'owner_name' => 'Owner', 'email' => 'preexisting@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'johndoe.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => true, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $driver  = new CpanelDeploymentDriver($server, $vault);
        $ctx     = new InstallationContext($installation, $request, $driver);

        $stage = new ProvisionDomainStage(new DomainProvisionerFactory(
            new VpsDomainProvisioner(),
            new CpanelDomainProvisioner(new CpanelUapiClient($vault)),
        ));

        $result = $stage->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame('already_exists', $result->output['status']);
        $this->assertFalse($result->output['created_by_installer']);

        $stage->rollback($ctx, $result->rollbackSnapshot);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'delsubdomain') || str_contains($request->url(), 'deladdondomain'));
    }
}
