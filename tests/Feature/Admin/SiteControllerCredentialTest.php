<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Site;
use App\Models\Theme;
use App\Models\User;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\Lifecycle\SiteLifecycleService;
use App\Services\PlatformSiteAgentService;
use App\Services\ResourceGovernance\SiteResourceCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 13-O12a — SiteController::store()/update() must only ever write a
 * submitted db_password into the encrypted column (db_password_encrypted),
 * never into the legacy plaintext db_password column, per the Phase 13-O11
 * design (Option C, step one).
 *
 * Phase 13-O12c — extends this with the UI write-only hardening
 * (edit.blade.php no longer pre-fills the password at all) and consumer
 * regression for the remaining three refactored consumers.
 */
class SiteControllerCredentialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // config() takes effect immediately regardless of boot order, unlike
        // putenv()/env() which only affects the *next* Application boot —
        // too late here since parent::setUp() already booted this test's app.
        config(['installer.master_key' => base64_encode(random_bytes(32))]);
    }

    private function admin(): User
    {
        return User::create([
            'mobile'    => '09191234567',
            'password'  => Hash::make('password'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name'   => 'Site Controller Cred Customer',
            'mobile' => '09121234567',
        ]);
    }

    public function test_store_encrypts_new_db_password_and_never_writes_plaintext_column(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->makeCustomer();

        $response = $this->post(route('admin.sites.store'), [
            'customer_id' => $customer->id,
            'title'       => 'Store Cred Site',
            'db_host'     => '127.0.0.1',
            'db_name'     => 'customer_site',
            'db_user'     => 'root',
            'db_password' => 'FreshlyEnteredPass789',
        ]);

        $response->assertRedirect();

        $site = Site::where('title', 'Store Cred Site')->firstOrFail();

        $this->assertNotEmpty($site->db_password_encrypted);
        $this->assertSame('FreshlyEnteredPass789', $site->getDecryptedDbPassword());
    }

    public function test_update_encrypts_new_db_password_and_never_writes_plaintext_column(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->makeCustomer();

        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'Update Cred Site',
            'db_host'     => '127.0.0.1',
            'db_name'     => 'customer_site',
            'db_user'     => 'root',
        ]);

        $response = $this->put(route('admin.sites.update', $site), [
            'customer_id' => $customer->id,
            'title'       => 'Update Cred Site',
            'db_host'     => '127.0.0.1',
            'db_name'     => 'customer_site',
            'db_user'     => 'root',
            'db_password' => 'UpdatedPassABC321',
        ]);

        $response->assertRedirect();

        $site->refresh();
        $this->assertNotEmpty($site->db_password_encrypted);
        $this->assertSame('UpdatedPassABC321', $site->getDecryptedDbPassword());
    }

    public function test_update_without_db_password_field_leaves_existing_credential_untouched(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->makeCustomer();
        $vault = new CredentialVault();

        $site = Site::create([
            'customer_id'           => $customer->id,
            'title'                 => 'Untouched Cred Site',
            'db_host'               => '127.0.0.1',
            'db_name'               => 'customer_site',
            'db_user'               => 'root',
            'db_password_encrypted' => $vault->encrypt('DoNotChangeMe'),
        ]);

        $this->put(route('admin.sites.update', $site), [
            'customer_id' => $customer->id,
            'title'       => 'Untouched Cred Site Renamed',
            'db_host'     => '127.0.0.1',
            'db_name'     => 'customer_site',
            'db_user'     => 'root',
            // db_password intentionally omitted — as today's unchanged blade would submit when left blank.
        ]);

        $site->refresh();
        $this->assertSame('DoNotChangeMe', $site->getDecryptedDbPassword());
    }

    /**
     * Consumer regression — proves the full chain (Site::db_password_encrypted
     * → CredentialVault::decrypt() → PlatformSiteAgentService::openSiteConnection())
     * still produces a working database connection. PlatformSiteAgentService is
     * representative of all four refactored consumers, which share the same
     * connection-building shape; this test connects to the test suite's own
     * database using its own real connection credentials, so it needs no
     * external server and is not sensitive to any particular local password.
     */
    public function test_platform_site_agent_service_connects_using_vault_backed_password(): void
    {
        $vault = new CredentialVault();
        $customer = $this->makeCustomer();

        $site = Site::create([
            'customer_id'           => $customer->id,
            'title'                 => 'Connectable New Style Site',
            'db_host'               => config('database.connections.mysql.host'),
            'db_name'               => config('database.connections.mysql.database'),
            'db_user'               => config('database.connections.mysql.username'),
            'db_password_encrypted' => $vault->encrypt((string) config('database.connections.mysql.password')),
        ]);

        $connected = app(PlatformSiteAgentService::class)->testConnection($site);

        $this->assertTrue($connected, 'Expected a real connection using the vault-decrypted password to succeed');
    }

    // ─── Phase 13-O12c ───────────────────────────────────────────────────────

    /**
     * A) UI hardening regression — the edit page must never contain the real
     * password, neither the plaintext value entered nor the ciphertext
     * derived from the encrypted column.
     *
     * Phase 13-O12e — this used to also cover a "legacy" site with a
     * plaintext db_password; that scenario no longer exists now that the
     * column itself has been removed from the schema.
     */
    public function test_edit_page_never_contains_plaintext_or_encrypted_password(): void
    {
        $this->actingAs($this->admin());
        $vault = new CredentialVault();
        $customer = $this->makeCustomer();

        $vaultSite = Site::create([
            'customer_id'           => $customer->id,
            'title'                 => 'Vault Edit Page Site',
            'db_password_encrypted' => $vault->encrypt('VaultPlainShouldNeverAppear'),
        ]);

        $vaultResponse = $this->get(route('admin.sites.edit', $vaultSite));
        $vaultResponse->assertOk();
        $vaultResponse->assertDontSee('VaultPlainShouldNeverAppear');
        // The ciphertext itself must not leak into the DOM either.
        $vaultResponse->assertDontSee($vaultSite->fresh()->db_password_encrypted, false);
    }

    /**
     * G) consumer regression — SiteLifecycleService::transition() opens a
     * real connection via the decrypted password. pushStatusToCustomerSite()
     * targets a *customer site's* settings table shape (with a `group`
     * column), which differs from this platform's own settings table — so
     * against our own test DB it fails with a schema mismatch, not an
     * authentication error. That's exactly the signal we want: it proves
     * the decrypted password successfully authenticated the connection
     * (the failure happens strictly *after* auth, at the INSERT).
     */
    public function test_site_lifecycle_service_connects_using_vault_backed_password(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $vault = new CredentialVault();
        $customer = $this->makeCustomer();

        $site = Site::create([
            'customer_id'           => $customer->id,
            'title'                 => 'Lifecycle Connectable Site',
            'db_host'               => config('database.connections.mysql.host'),
            'db_name'               => config('database.connections.mysql.database'),
            'db_user'               => config('database.connections.mysql.username'),
            'db_password_encrypted' => $vault->encrypt((string) config('database.connections.mysql.password')),
            'lifecycle_status'      => Site::LIFECYCLE_ACTIVE,
        ]);

        app(SiteLifecycleService::class)->transition($site, Site::LIFECYCLE_SUSPENDED, 'test');

        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('warning', function (string $message, array $context = []) {
            $error = $context['error'] ?? '';
            return str_contains($error, 'Access denied')
                || str_contains($error, "Can't connect")
                || str_contains($error, 'Connection refused')
                || str_contains($error, 'SQLSTATE[HY000] [1045]')
                || str_contains($error, 'SQLSTATE[HY000] [2002]');
        });
    }

    /** G) consumer regression — SiteResourceCalculatorService::getDatabaseBytes() opens a real connection using the decrypted password. */
    public function test_site_resource_calculator_service_connects_using_vault_backed_password(): void
    {
        $vault = new CredentialVault();
        $customer = $this->makeCustomer();

        $site = Site::create([
            'customer_id'           => $customer->id,
            'title'                 => 'Resource Calc Connectable Site',
            'db_host'               => config('database.connections.mysql.host'),
            'db_name'               => config('database.connections.mysql.database'),
            'db_user'               => config('database.connections.mysql.username'),
            'db_password_encrypted' => $vault->encrypt((string) config('database.connections.mysql.password')),
        ]);

        $bytes = app(SiteResourceCalculatorService::class)->getDatabaseBytes($site);

        $this->assertGreaterThan(0, $bytes, 'Expected a real information_schema query over the decrypted-password connection to report non-zero size');
    }

    /** super_admin — theme-builder routes sit behind the 'wizard' middleware, which only super_admin passes. */
    private function superAdmin(): User
    {
        $user = User::create([
            'mobile'    => '09191234599',
            'password'  => Hash::make('password'),
            'is_admin'  => true,
            'is_active' => true,
        ]);

        $role = \App\Models\Role::where('slug', \App\Models\Role::SUPER_ADMIN)->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    /**
     * G) consumer regression — ThemeCanvasController::syncPagesForSite() opens
     * a real connection using the decrypted password. Like the
     * SiteLifecycleService case above, this method targets a *customer
     * site's* `pages` table shape (with an `order` column), which differs
     * from this platform's own `pages` table — so against our own test DB it
     * fails with a schema mismatch, not an authentication error. Reaching a
     * real query on the dynamic `theme_sync_*` connection (instead of an
     * "Access denied"/"Can't connect" failure at connect time) is exactly
     * the proof we want: the decrypted password successfully authenticated.
     */
    public function test_theme_canvas_controller_connects_using_vault_backed_password(): void
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->superAdmin());
        $vault = new CredentialVault();
        $customer = $this->makeCustomer();

        $site = Site::create([
            'customer_id'           => $customer->id,
            'title'                 => 'Theme Sync Connectable Site',
            'db_host'               => config('database.connections.mysql.host'),
            'db_name'               => config('database.connections.mysql.database'),
            'db_user'               => config('database.connections.mysql.username'),
            'db_password_encrypted' => $vault->encrypt((string) config('database.connections.mysql.password')),
        ]);

        $theme = Theme::create(['name' => 'Sync Test Theme', 'slug' => 'sync-test-theme']);

        try {
            $this->post(route('admin.theme-builder.sync-pages', $theme), ['site_id' => $site->id]);
            $this->fail('Expected a QueryException — this platform DB does not share a customer site "pages" table schema');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('theme_sync_', (string) $e->getConnectionName());
            $this->assertStringNotContainsString('Access denied', $e->getMessage());
        }
    }
}
