<?php

namespace Tests\Feature\Admin\Infrastructure;

use App\Models\ActivityLog;
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
 * Phase 13-O9e — Secure Reveal flow for CustomerDeploymentController.
 *
 * Prior to this phase, show() decrypted and auto-rendered the admin
 * password in the initial HTML for anyone passing the blanket
 * ['auth','admin'] middleware (see the 13-O9a version of this test file).
 * Phase 13-O9d audited that as a permission-boundary gap; this phase closes
 * it: show() now only tells the view whether a credential exists
 * (hasCredential, no decrypt), and the actual password is only ever
 * decrypted by the separate, permission-gated, audit-logged
 * revealCredential() endpoint.
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

    /** is_admin=true + staff role — seeded with tenant.credentials.view via the 13-O9e migration. */
    private function staffWithPermission(): User
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

    /** is_admin=true (passes the blanket ['auth','admin'] middleware) but no role at all, so no permission row. */
    private function adminWithoutCredentialPermission(): User
    {
        return User::create([
            'mobile' => '09141234568',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    /** super_admin, deliberately with no explicit role_has_permissions row for tenant.credentials.view. */
    private function superAdmin(): User
    {
        $user = User::create([
            'mobile' => '09141234570',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        $role = Role::where('slug', Role::SUPER_ADMIN)->first();
        if ($role) {
            $user->roles()->attach($role->id);
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

    /** New-style installation: password lives in InstallationCredential, encrypted via CredentialVault. */
    private function makeNewStyleInstallation(string $domain = 'cred-new.example.com', string $plainPassword = 'BrandNewPass123'): CustomerInstallation
    {
        $server = $this->makeServer();
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'complete',
            'site_id'           => 88,
            'customer_company'  => 'Cred UI Co',
            'customer_email'    => 'creduii@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => $this->baseRequestPayload($server, $domain),
        ]);

        $credential = InstallationCredential::create([
            'installation_id' => $installation->id,
            'type'            => 'admin_password',
            'encrypted_value' => (new CredentialVault())->encrypt($plainPassword),
        ]);

        $installation->update([
            'metadata' => [
                'admin_email' => 'creduii@example.com',
                'admin_name'  => 'Owner',
                'credential_ids' => ['admin_password' => $credential->id],
            ],
        ]);

        return $installation->fresh();
    }

    /** Old-style (pre-13-O9a) installation: password directly in metadata, no InstallationCredential row. */
    private function makeOldStyleInstallation(string $domain = 'cred-old.example.com', string $plainPassword = 'OldPlainPass456'): CustomerInstallation
    {
        $server = $this->makeServer();

        return CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'complete',
            'customer_company'  => 'Cred UI Co',
            'customer_email'    => 'creduii@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => $this->baseRequestPayload($server, $domain),
            'metadata' => [
                'admin_email'    => 'creduii@example.com',
                'admin_name'     => 'Owner',
                'admin_password' => $plainPassword,
            ],
        ]);
    }

    // ─── A) staff without tenant.credentials.view ──────────────────────────

    public function test_show_page_renders_for_user_without_credential_permission_but_hides_password(): void
    {
        $installation = $this->makeNewStyleInstallation();
        $this->actingAs($this->adminWithoutCredentialPermission());

        $response = $this->get(route('admin.infrastructure.customer-deployment.installations.show', $installation));

        $response->assertOk();
        $response->assertDontSee('BrandNewPass123');
    }

    public function test_reveal_denied_without_credential_permission(): void
    {
        $installation = $this->makeNewStyleInstallation();
        $this->actingAs($this->adminWithoutCredentialPermission());

        $response = $this->get(route('admin.infrastructure.customer-deployment.installations.credential', $installation));

        $response->assertForbidden();
    }

    // ─── B) staff with tenant.credentials.view ─────────────────────────────

    public function test_reveal_allowed_with_permission_returns_password_and_logs_activity(): void
    {
        $staff = $this->staffWithPermission();
        $this->actingAs($staff);
        $installation = $this->makeNewStyleInstallation();

        $response = $this->getJson(route('admin.infrastructure.customer-deployment.installations.credential', $installation));

        $response->assertOk();
        $response->assertJson(['password' => 'BrandNewPass123']);

        $this->assertDatabaseHas('activity_logs', [
            'action'       => 'installation.credential_viewed',
            'subject_type' => CustomerInstallation::class,
            'subject_id'   => $installation->id,
            'user_id'      => $staff->id,
        ]);

        $log = ActivityLog::where('action', 'installation.credential_viewed')
            ->where('subject_id', $installation->id)
            ->firstOrFail();

        $this->assertSame($installation->id, $log->properties['installation_id']);
        $this->assertSame($installation->site_id, $log->properties['site_id']);
        $this->assertSame('admin_password', $log->properties['credential_type']);
        $this->assertSame($staff->id, $log->properties['triggered_by']);
    }

    // ─── C) super_admin without an explicit permission row ─────────────────

    public function test_reveal_allowed_for_super_admin_without_permission_row(): void
    {
        $this->actingAs($this->superAdmin());
        $installation = $this->makeNewStyleInstallation();

        $response = $this->getJson(route('admin.infrastructure.customer-deployment.installations.credential', $installation));

        $response->assertOk();
        $response->assertJson(['password' => 'BrandNewPass123']);
    }

    // ─── D) new installation: InstallationCredential + Vault ───────────────

    public function test_reveal_decrypts_correctly_for_new_style_installation(): void
    {
        $this->actingAs($this->staffWithPermission());
        $installation = $this->makeNewStyleInstallation('cred-new2.example.com', 'AnotherVaultPass789');

        $response = $this->getJson(route('admin.infrastructure.customer-deployment.installations.credential', $installation));

        $response->assertOk();
        $response->assertJson(['password' => 'AnotherVaultPass789']);
    }

    // ─── E) old installation: metadata.admin_password fallback ─────────────

    public function test_reveal_falls_back_to_metadata_for_old_style_installation(): void
    {
        $this->actingAs($this->staffWithPermission());
        $installation = $this->makeOldStyleInstallation();

        $response = $this->getJson(route('admin.infrastructure.customer-deployment.installations.credential', $installation));

        $response->assertOk();
        $response->assertJson(['password' => 'OldPlainPass456']);
        $this->assertDatabaseCount('installation_credentials', 0);
    }

    public function test_show_page_hides_password_for_old_style_installation_too(): void
    {
        $this->actingAs($this->staffWithPermission());
        $installation = $this->makeOldStyleInstallation();

        $response = $this->get(route('admin.infrastructure.customer-deployment.installations.show', $installation));

        $response->assertOk();
        $response->assertDontSee('OldPlainPass456');
    }

    // ─── F) security regression: show() must never leak the secret ────────

    public function test_show_page_never_contains_plaintext_credential_for_new_style_installation(): void
    {
        $this->actingAs($this->staffWithPermission());
        $installation = $this->makeNewStyleInstallation('cred-new3.example.com', 'ShouldNeverAppear999');

        $response = $this->get(route('admin.infrastructure.customer-deployment.installations.show', $installation));

        $response->assertOk();
        $response->assertDontSee('ShouldNeverAppear999');
        $response->assertDontSee('admin_password');
    }

    public function test_show_page_never_contains_plaintext_credential_for_old_style_installation(): void
    {
        $this->actingAs($this->staffWithPermission());
        $installation = $this->makeOldStyleInstallation('cred-old2.example.com', 'ShouldNeverAppearEither000');

        $response = $this->get(route('admin.infrastructure.customer-deployment.installations.show', $installation));

        $response->assertOk();
        $response->assertDontSee('ShouldNeverAppearEither000');
    }
}
