<?php

namespace Tests\Unit\CustomerDeletion;

use App\Models\CustomerDeletion;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Enums\DriverType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-G3 — DeleteContext::fromDeletion() resolves the web root via
 * WebRootResolver instead of hardcoding '/var/www/{slug}'. VPS behavior is
 * byte-identical (same default); cPanel resolves to the real document root
 * (explicit path override first, /home/{username}/public_html otherwise) —
 * so REMOVE_FILES/REMOVE_STORAGE target the directory the install actually
 * wrote to.
 */
class DeleteContextWebRootTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        parent::setUp();
    }

    private function makeDeletion(string $serverType, ?string $path = null): CustomerDeletion
    {
        $server = CustomerRemoteServer::create([
            'label' => 'S', 'type' => $serverType, 'host' => 'box.example.test',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => (new CredentialVault())->encrypt('the-password'),
            'path' => $path,
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'complete',
            'customer_company'  => 'Webroot Co',
            'customer_email'    => 'webroot@example.com',
            'customer_domain'   => 'webroot.example.com',
            'request_payload'   => [
                'company' => 'Webroot Co', 'owner_name' => 'Owner', 'email' => 'webroot@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'webroot.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        return CustomerDeletion::create([
            'installation_id' => $installation->id,
            'initiated_by'    => 1,
            'policy'          => 'never_delete_backup',
            'status'          => 'running',
        ]);
    }

    public function test_vps_web_root_is_unchanged(): void
    {
        $deletion = $this->makeDeletion('fake');
        $ctx = DeleteContext::fromDeletion($deletion, new FakeRemoteDriver());

        // The slug itself comes from resolveSlug() (unchanged by Phase
        // 14-G3 — copied verbatim), so this pins the web root FORMULA
        // (/var/www/{slug}, the pre-14-G3 hardcoded default), not the slug
        // algorithm's own quirks.
        $this->assertSame('/var/www/' . $ctx->slug, $ctx->webRoot);
        $this->assertStringEndsWith('_' . $deletion->installation->id, $ctx->slug);
        $this->assertStringStartsWith('cust_', $ctx->slug);
    }

    public function test_cpanel_web_root_resolves_to_the_account_document_root(): void
    {
        $deletion = $this->makeDeletion('cpanel');
        $ctx = DeleteContext::fromDeletion($deletion, new FakeRemoteDriver(driverType: DriverType::CPANEL));

        $this->assertSame('/home/johndoe/public_html', $ctx->webRoot);
    }

    public function test_explicit_path_override_wins_on_any_driver(): void
    {
        $deletion = $this->makeDeletion('cpanel', '/home/johndoe/domains/addon.example.com/');
        $ctx = DeleteContext::fromDeletion($deletion, new FakeRemoteDriver(driverType: DriverType::CPANEL));

        $this->assertSame('/home/johndoe/domains/addon.example.com', $ctx->webRoot);
    }

    public function test_installation_context_adapter_reuses_the_same_connection_and_slug(): void
    {
        $deletion = $this->makeDeletion('fake');
        $driver = new FakeRemoteDriver();
        $ctx = DeleteContext::fromDeletion($deletion, $driver);

        $ictx = $ctx->installationContext();

        $this->assertSame($driver, $ictx->connection);
        $this->assertSame($ctx->slug, $ictx->customerSlug(), 'delete slug and install slug algorithms must stay identical');
        $this->assertSame($ctx->webRoot, $ictx->webRoot(), 'both contexts must resolve the same web root');
        $this->assertSame($ictx, $ctx->installationContext(), 'adapter is memoized');
    }
}
