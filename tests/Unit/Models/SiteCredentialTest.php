<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Site;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13-O12a — Site::getDecryptedDbPassword() is the single central read
 * path introduced to close the plaintext-storage gap audited in Phase
 * 13-O11: new/updated sites store db_password encrypted via CredentialVault
 * in db_password_encrypted; sites created before this phase still have it
 * as plaintext directly in db_password (fallback, no backfill in this step).
 */
class SiteCredentialTest extends TestCase
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

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name'   => 'Cred Site Customer',
            'mobile' => '09121234567',
        ]);
    }

    public function test_new_style_site_decrypts_via_vault(): void
    {
        $vault = new CredentialVault();
        $site = Site::create([
            'customer_id'           => $this->makeCustomer()->id,
            'title'                 => 'New Style Site',
            'db_host'               => '127.0.0.1',
            'db_name'               => 'customer_site',
            'db_user'               => 'root',
            'db_password_encrypted' => $vault->encrypt('VaultBackedPass123'),
        ]);

        $this->assertSame('VaultBackedPass123', $site->fresh()->getDecryptedDbPassword());
    }

    public function test_old_style_site_falls_back_to_plaintext_column(): void
    {
        $site = Site::create([
            'customer_id' => $this->makeCustomer()->id,
            'title'       => 'Old Style Site',
            'db_host'     => '127.0.0.1',
            'db_name'     => 'customer_site',
            'db_user'     => 'root',
            'db_password' => 'LegacyPlainPass456',
            // db_password_encrypted deliberately left unset — pre-13-O12a shape.
        ]);

        $this->assertSame('LegacyPlainPass456', $site->fresh()->getDecryptedDbPassword());
    }

    public function test_new_style_takes_priority_over_stale_plaintext_column(): void
    {
        $vault = new CredentialVault();
        $site = Site::create([
            'customer_id'           => $this->makeCustomer()->id,
            'title'                 => 'Mixed Site',
            'db_host'               => '127.0.0.1',
            'db_name'               => 'customer_site',
            'db_user'               => 'root',
            'db_password'           => 'StalePlainShouldNotBeUsed',
            'db_password_encrypted' => $vault->encrypt('FreshVaultPassword'),
        ]);

        $this->assertSame('FreshVaultPassword', $site->fresh()->getDecryptedDbPassword());
    }

    public function test_returns_null_when_neither_column_is_set(): void
    {
        $site = Site::create([
            'customer_id' => $this->makeCustomer()->id,
            'title'       => 'No Credential Site',
        ]);

        $this->assertNull($site->fresh()->getDecryptedDbPassword());
    }

    public function test_decrypt_failure_is_logged_and_returns_null_instead_of_throwing(): void
    {
        $site = Site::create([
            'customer_id'           => $this->makeCustomer()->id,
            'title'                 => 'Corrupted Ciphertext Site',
            'db_password_encrypted' => 'not-valid-ciphertext',
        ]);

        $this->assertNull($site->fresh()->getDecryptedDbPassword());
    }
}
