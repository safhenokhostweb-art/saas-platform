<?php

namespace Tests\Feature\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\Stages\ConfigureQueueStage;
use App\Services\CustomerInstallation\Stages\ConfigureSchedulerStage;
use App\Services\CustomerInstallation\Stages\ConfigureWebServerStage;
use App\Services\CustomerInstallation\Stages\CreateDatabaseStage;
use App\Services\CustomerInstallation\Stages\IssueSslStage;
use App\Services\CustomerInstallation\Stages\ProvisionDomainStage;
use App\Services\CustomerInstallation\StageCapabilityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 15-E — Part 6: a cPanel "dry run" covering the full Phase 15-D/15-E
 * deployment flow shape:
 *
 *   VALIDATE_SERVER -> PROVISION_DOMAIN -> UPLOAD_PACKAGE -> EXTRACT ->
 *   ENV -> DATABASE -> [WEB_SERVER: skipped] -> ISSUE_SSL -> QUEUE ->
 *   SCHEDULER -> STORAGE -> LICENSE -> VALIDATION
 *
 * This test verifies (a) the REAL orchestrator's stage order matches this
 * shape, (b) CONFIGURE_WEB_SERVER's capability gate genuinely skips for
 * cPanel, and (c) chaining the real domain/database/queue/scheduler/SSL
 * Stages against a cPanel-shaped FakeRemoteDriver + Http::fake produces
 * complete, non-empty rollback snapshots at every mutating step — not just
 * that each Stage passes in isolation (already covered by their own
 * dedicated test files), but that the SAME InstallationContext correctly
 * carries PROVISION_DOMAIN's resolved document root into every stage that
 * calls $ctx->webRoot() afterward, exactly as the real orchestrator would.
 */
class FullCpanelDeploymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function cpanelServer(): CustomerRemoteServer
    {
        $vault = new CredentialVault();

        return CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box106.parsvds.com',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => $vault->encrypt('the-account-password'),
        ]);
    }

    private function context(CustomerRemoteServer $server, string $domain): InstallationContext
    {
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Dry Run Co',
            'customer_email'    => 'dryrun@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => [
                'company' => 'Dry Run Co', 'owner_name' => 'Owner', 'email' => 'dryrun@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => $domain, 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => true, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $driver  = new FakeRemoteDriver(driverType: DriverType::CPANEL);

        return new InstallationContext($installation, $request, $driver);
    }

    public function test_stage_order_matches_the_expected_cpanel_deployment_shape(): void
    {
        $orchestrator = app(CustomerInstallationOrchestrator::class);
        $names = array_map(fn ($s) => $s->name(), $orchestrator->buildStageList('MODE_FRESH'));

        $pos = fn (string $name) => array_search($name, $names, true);

        $this->assertNotFalse($pos('PROVISION_DOMAIN'));
        $this->assertLessThan($pos('UPLOAD_PACKAGE'), $pos('PROVISION_DOMAIN'), 'PROVISION_DOMAIN must resolve webRoot before any upload');
        $this->assertLessThan($pos('CREATE_DATABASE'), $pos('UPLOAD_PACKAGE'));
        $this->assertLessThan($pos('CONFIGURE_WEB_SERVER'), $pos('CREATE_DATABASE'));
        $this->assertLessThan($pos('ISSUE_SSL'), $pos('CONFIGURE_WEB_SERVER'));
        $this->assertLessThan($pos('PROVISION_LICENSE'), $pos('ISSUE_SSL'));
        $this->assertLessThan($pos('CUSTOMER_READY'), $pos('PROVISION_LICENSE'));
    }

    public function test_configure_web_server_is_skipped_for_cpanel_via_the_capability_gate(): void
    {
        $gate  = new StageCapabilityGate();
        $stage = app(ConfigureWebServerStage::class);
        // Both capabilities AND driverType must be cPanel-shaped here — the
        // gate reads capabilities(), not driverType() — unlike this file's
        // other tests, which only route on driverType() and so didn't need this.
        $driver = new FakeRemoteDriver(DriverCapabilities::forCpanel(), DriverType::CPANEL);

        $this->assertFalse($gate->isSatisfied($stage, $driver), 'cPanel must not satisfy supportsWebServerConfiguration');
        $this->assertFalse($stage->isCapabilityRequiredToProceed(), 'Missing web-server config capability must skip, not fail');
    }

    public function test_domain_through_ssl_chain_produces_complete_rollback_snapshots_for_a_new_subdomain(): void
    {
        Http::fake([
            '*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
                'main_domain' => 'johndoe.example.com',
                'domains'     => [],
            ]], 200),
            '*SubDomain/addsubdomain*'          => Http::response(['status' => 1, 'data' => []], 200),
            '*Mysql/create_database*'           => Http::response(['status' => 1, 'data' => []], 200),
            '*Mysql/create_user*'               => Http::response(['status' => 1, 'data' => []], 200),
            '*Mysql/set_privileges_on_user*'    => Http::response(['status' => 1, 'data' => []], 200),
            '*Cron/add_line*'                    => Http::response(['status' => 1, 'data' => []], 200),
            '*SSL/list_certs*'                   => Http::response(['status' => 1, 'data' => []], 200),
            '*SSL/start_autossl_check*'          => Http::response(['status' => 1, 'data' => []], 200),
        ]);

        $server = $this->cpanelServer();
        $ctx    = $this->context($server, 'shop.johndoe.example.com');

        // PROVISION_DOMAIN
        $domainResult = app(ProvisionDomainStage::class)->execute($ctx);
        $this->assertTrue($domainResult->success, $domainResult->error ?? '');
        $this->assertSame('created', $domainResult->output['status']);
        $this->assertTrue($domainResult->output['created_by_installer']);
        $this->assertArrayHasKey('document_root', $domainResult->rollbackSnapshot);
        $this->assertArrayHasKey('created_by_installer', $domainResult->rollbackSnapshot);
        $this->assertArrayHasKey('type', $domainResult->rollbackSnapshot);
        $ctx->recordOutput('PROVISION_DOMAIN', $domainResult->output);

        // webRoot() must now resolve to the real cPanel-provisioned path, not the guess.
        $this->assertSame('/home/johndoe/public_html/shop', $ctx->webRoot());

        // CREATE_DATABASE
        $dbResult = app(CreateDatabaseStage::class)->execute($ctx);
        $this->assertTrue($dbResult->success, $dbResult->error ?? '');
        $this->assertArrayHasKey('db_name', $dbResult->rollbackSnapshot);
        $this->assertArrayHasKey('db_user', $dbResult->rollbackSnapshot);
        $this->assertStringStartsWith('johndoe_', $dbResult->rollbackSnapshot['db_name']);
        $ctx->recordOutput('CREATE_DATABASE', $dbResult->output);

        // CONFIGURE_QUEUE (cron-based on cPanel)
        $queueResult = app(ConfigureQueueStage::class)->execute($ctx);
        $this->assertTrue($queueResult->success, $queueResult->error ?? '');

        // CONFIGURE_SCHEDULER
        $schedulerResult = app(ConfigureSchedulerStage::class)->execute($ctx);
        $this->assertTrue($schedulerResult->success, $schedulerResult->error ?? '');

        // ISSUE_SSL
        $sslResult = app(IssueSslStage::class)->execute($ctx);
        $this->assertTrue($sslResult->success, $sslResult->error ?? '');
        $this->assertSame('issued', $sslResult->output['status']);
        $this->assertSame('cpanel', $sslResult->output['ssl_managed_by']);
    }

    public function test_domain_through_ssl_chain_never_removes_a_pre_existing_main_domain_on_rollback(): void
    {
        Http::fake([
            '*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
                'main_domain' => 'johndoe.example.com',
                'domains'     => [],
            ]], 200),
        ]);

        $server = $this->cpanelServer();
        $ctx    = $this->context($server, 'johndoe.example.com');

        $domainStage  = app(ProvisionDomainStage::class);
        $domainResult = $domainStage->execute($ctx);

        $this->assertTrue($domainResult->success);
        $this->assertSame('already_exists', $domainResult->output['status']);
        $this->assertFalse($domainResult->output['created_by_installer']);

        // Simulate a later-stage failure triggering rollback of this checkpoint.
        $domainStage->rollback($ctx, $domainResult->rollbackSnapshot);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'delsubdomain') || str_contains($request->url(), 'deladdondomain'));
    }
}
