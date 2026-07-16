<?php

namespace Tests\Unit\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Domain\CpanelDomainProvisioner;
use App\Services\CustomerInstallation\Domain\DomainProvisionerFactory;
use App\Services\CustomerInstallation\Domain\VpsDomainProvisioner;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\WebRootResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 15-D — resolveForInstallation() is the domain-aware resolution path
 * (see WebRootResolver's docblock for the 3-tier priority). resolve() itself
 * is exercised unchanged by the pre-existing WebRootResolverTest — these
 * tests cover only the new tiers, including the resumed-pipeline case where
 * PROVISION_DOMAIN completed in a previous run and the in-memory context
 * output is empty this run.
 */
class WebRootResolverCpanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function cpanelContext(string $domain = 'shop.example.com'): array
    {
        $vault  = new CredentialVault();
        $server = CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box.example.test',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => $vault->encrypt('pw'),
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'WebRoot Co',
            'customer_email'    => 'webroot@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => [
                'company' => 'WebRoot Co', 'owner_name' => 'Owner', 'email' => 'webroot@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => $domain, 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => true, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $driver  = new CpanelDeploymentDriver($server, $vault);
        $ctx     = new InstallationContext($installation, $request, $driver);

        return [$ctx, $server];
    }

    public function test_tier1_prefers_current_run_context_output_over_everything(): void
    {
        [$ctx, $server] = $this->cpanelContext();
        $ctx->recordOutput('PROVISION_DOMAIN', ['document_root' => '/home/johndoe/public_html/shop']);

        $path = (new WebRootResolver())->resolveForInstallation($ctx, 'customer', $server);

        $this->assertSame('/home/johndoe/public_html/shop', $path);
    }

    public function test_tier1_falls_back_to_persisted_log_when_this_run_resumed_past_the_stage(): void
    {
        [$ctx, $server] = $this->cpanelContext();

        // No $ctx->recordOutput() this run (simulates CustomerInstallationOrchestrator's
        // hasCompletedStage() skip on a resumed pipeline) — only the persisted,
        // immutable log has the real document root.
        CustomerInstallationLog::create([
            'installation_id' => $ctx->installation->id,
            'stage'           => 'PROVISION_DOMAIN',
            'event'           => 'completed',
            'message'         => 'Stage PROVISION_DOMAIN completed',
            'output'          => ['document_root' => '/home/johndoe/public_html/shop-from-log'],
            'created_at'      => now(),
        ]);

        $path = (new WebRootResolver())->resolveForInstallation($ctx, 'customer', $server);

        $this->assertSame('/home/johndoe/public_html/shop-from-log', $path);
    }

    public function test_tier2_falls_back_to_live_cpanel_query_when_no_snapshot_exists_at_all(): void
    {
        [$ctx, $server] = $this->cpanelContext('legacy.example.com');

        $cpanelSpy = new class extends CpanelDomainProvisioner {
            public function __construct() {}
            public function resolveDocumentRoot(InstallationContext $ctx, string $domain): ?string
            {
                return '/home/johndoe/public_html/legacy-live-query';
            }
        };
        $factory = new DomainProvisionerFactory(new VpsDomainProvisioner(), $cpanelSpy);

        $path = (new WebRootResolver($factory))->resolveForInstallation($ctx, 'customer', $server);

        $this->assertSame('/home/johndoe/public_html/legacy-live-query', $path);
    }

    public function test_tier3_falls_back_to_existing_guess_when_live_query_also_returns_null(): void
    {
        [$ctx, $server] = $this->cpanelContext('legacy2.example.com');

        $cpanelSpy = new class extends CpanelDomainProvisioner {
            public function __construct() {}
            public function resolveDocumentRoot(InstallationContext $ctx, string $domain): ?string
            {
                return null;
            }
        };
        $factory = new DomainProvisionerFactory(new VpsDomainProvisioner(), $cpanelSpy);

        $path = (new WebRootResolver($factory))->resolveForInstallation($ctx, 'customer', $server);

        $this->assertSame('/home/johndoe/public_html', $path, 'Must fall back to the pre-15-D guess, never throw or return an empty path');
    }

    public function test_tier2_live_query_is_never_attempted_for_vps(): void
    {
        $server = CustomerRemoteServer::create([
            'label' => 'VPS', 'type' => 'ssh', 'host' => 'vps.example.test',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'VPS Co',
            'customer_email'    => 'vps@example.com',
            'customer_domain'   => 'vps-site.example.com',
            'request_payload'   => [
                'company' => 'VPS Co', 'owner_name' => 'Owner', 'email' => 'vps@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'vps-site.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $ctx     = new InstallationContext($installation, $request, new FakeRemoteDriver());

        $cpanelSpy = new class extends CpanelDomainProvisioner {
            public bool $called = false;
            public function __construct() {}
            public function resolveDocumentRoot(InstallationContext $ctx, string $domain): ?string
            {
                $this->called = true;
                return '/should/never/be/used';
            }
        };
        $factory = new DomainProvisionerFactory(new VpsDomainProvisioner(), $cpanelSpy);

        $path = (new WebRootResolver($factory))->resolveForInstallation($ctx, 'customer', $server);

        $this->assertSame('/var/www/customer', $path);
        $this->assertFalse($cpanelSpy->called);
    }
}
