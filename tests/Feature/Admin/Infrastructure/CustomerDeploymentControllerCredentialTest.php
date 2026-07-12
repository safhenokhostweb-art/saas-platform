<?php

namespace Tests\Feature\Admin\Infrastructure;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\InstallationCredential;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 13-O9a — CustomerDeploymentController::show() must keep displaying
 * the admin password exactly as before, but the source changes:
 * new installations resolve it via CredentialVault (InstallationCredential),
 * installations created before this phase fall back to the old plaintext
 * metadata.admin_password (no backfill/deletion of old data in this phase).
 */
class CustomerDeploymentControllerCredentialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function admin(): User
    {
        $user = User::create([
            'mobile' => '09141234567',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        $staffRole = Role::where('slug', Role::STAFF)->first();
        if ($staffRole) {
            $user->roles()->attach($staffRole->id);
        }

        return $user;
    }

    private function makeServer(): CustomerRemoteServer
    {
        return CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);
    }

    private function baseRequestPayload(CustomerRemoteServer $server, string $domain): array
    {
        return [
            'company' => 'Cred UI Co', 'owner_name' => 'Owner',
            'email' => 'creduii@example.com', 'phone' => '09123456789',
            'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
            'domain' => $domain, 'license_type' => 'basic',
            'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
            'smtp' => [], 'ssl_enabled' => false,
            'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
        ];
    }

    public function test_new_installation_show_reads_from_vault(): void
    {
        $server = $this->makeServer();
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'complete',
            'customer_company'  => 'Cred UI Co',
            'customer_email'    => 'creduii@example.com',
            'customer_domain'   => 'cred-new.example.com',
            'request_payload'   => $this->baseRequestPayload($server, 'cred-new.example.com'),
        ]);

        $credential = InstallationCredential::create([
            'installation_id' => $installation->id,
            'type'            => 'admin_password',
            'encrypted_value' => (new CredentialVault())->encrypt('BrandNewPass123'),
        ]);

        $installation->update([
            'metadata' => [
                'admin_email' => 'creduii@example.com',
                'admin_name'  => 'Owner',
                'credential_ids' => ['admin_password' => $credential->id],
            ],
        ]);

        $this->actingAs($this->admin());
        $response = $this->get(route('admin.infrastructure.customer-deployment.installations.show', $installation));

        $response->assertOk();
        $response->assertSee('BrandNewPass123');
    }

    public function test_old_installation_show_falls_back_to_metadata(): void
    {
        $server = $this->makeServer();
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'complete',
            'customer_company'  => 'Cred UI Co',
            'customer_email'    => 'creduii@example.com',
            'customer_domain'   => 'cred-old.example.com',
            'request_payload'   => $this->baseRequestPayload($server, 'cred-old.example.com'),
            // Pre-13-O9a shape: password directly in metadata, no credential_ids.
            'metadata' => [
                'admin_email'    => 'creduii@example.com',
                'admin_name'     => 'Owner',
                'admin_password' => 'OldPlainPass456',
            ],
        ]);

        $this->actingAs($this->admin());
        $response = $this->get(route('admin.infrastructure.customer-deployment.installations.show', $installation));

        $response->assertOk();
        $response->assertSee('OldPlainPass456');
        $this->assertDatabaseCount('installation_credentials', 0);
    }
}
