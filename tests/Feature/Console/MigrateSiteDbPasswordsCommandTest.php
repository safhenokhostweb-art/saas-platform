<?php

namespace Tests\Feature\Console;

use App\Models\Customer;
use App\Models\Site;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13-O12c — php artisan sites:migrate-db-passwords backfills
 * sites.db_password (legacy plaintext) into sites.db_password_encrypted
 * (CredentialVault), then nulls the legacy column, per the Phase 13-O12b
 * design (Option B). Never runs without a verified encrypt/decrypt
 * round-trip, and is safe to re-run (idempotent).
 */
class MigrateSiteDbPasswordsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['installer.master_key' => base64_encode(random_bytes(32))]);
    }

    private function makeCustomer(): Customer
    {
        return Customer::create(['name' => 'Migrate Cmd Customer', 'mobile' => '09121234567']);
    }

    /** D) legacy migration: db_password → db_password_encrypted → db_password = null. */
    public function test_migrates_legacy_plaintext_password_and_nulls_old_column(): void
    {
        $customer = $this->makeCustomer();
        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'Legacy Site',
            'db_password' => 'LegacyPlainPass123',
        ]);

        $this->artisan('sites:migrate-db-passwords')->assertExitCode(0);

        $site->refresh();
        $this->assertNull($site->db_password);
        $this->assertNotEmpty($site->db_password_encrypted);
        $this->assertSame('LegacyPlainPass123', $site->getDecryptedDbPassword());
    }

    /** F) decrypt(encrypt(original)) === original, verified as part of the migrated result. */
    public function test_migrated_password_decrypts_back_to_the_exact_original_value(): void
    {
        $customer = $this->makeCustomer();
        $original = 'Exact-Original-Value-!@#$%^&*()_+';
        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'Round Trip Site',
            'db_password' => $original,
        ]);

        $this->artisan('sites:migrate-db-passwords');

        $this->assertSame($original, $site->fresh()->getDecryptedDbPassword());
    }

    /** New-style sites (already encrypted, no legacy value) are left untouched. */
    public function test_skips_sites_that_have_no_legacy_plaintext_password(): void
    {
        $customer = $this->makeCustomer();
        $vault = new CredentialVault();
        $site = Site::create([
            'customer_id'           => $customer->id,
            'title'                 => 'Already New Style Site',
            'db_password_encrypted' => $vault->encrypt('AlreadyEncrypted'),
        ]);

        $this->artisan('sites:migrate-db-passwords')->assertExitCode(0);

        $site->refresh();
        $this->assertNull($site->db_password);
        $this->assertSame('AlreadyEncrypted', $site->getDecryptedDbPassword());
    }

    /** Sites with neither column set are simply not touched or counted as failures. */
    public function test_sites_without_any_password_are_ignored(): void
    {
        $customer = $this->makeCustomer();
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'No Credential Site']);

        $this->artisan('sites:migrate-db-passwords')->assertExitCode(0);

        $site->refresh();
        $this->assertNull($site->db_password);
        $this->assertNull($site->db_password_encrypted);
    }

    /** --dry-run must never write anything. */
    public function test_dry_run_does_not_modify_any_data(): void
    {
        $customer = $this->makeCustomer();
        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'Dry Run Site',
            'db_password' => 'ShouldNotBeTouched',
        ]);

        $this->artisan('sites:migrate-db-passwords', ['--dry-run' => true])->assertExitCode(0);

        $site->refresh();
        $this->assertSame('ShouldNotBeTouched', $site->db_password);
        $this->assertNull($site->db_password_encrypted);
    }

    /** E) idempotency — running the command twice must not corrupt or duplicate the credential. */
    public function test_running_command_twice_is_idempotent(): void
    {
        $customer = $this->makeCustomer();
        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'Idempotent Site',
            'db_password' => 'IdempotentPass456',
        ]);

        $this->artisan('sites:migrate-db-passwords');
        $firstEncrypted = $site->fresh()->db_password_encrypted;

        $this->artisan('sites:migrate-db-passwords')->assertExitCode(0);
        $site->refresh();

        $this->assertNull($site->db_password);
        // Untouched by the second run — same ciphertext, not re-encrypted.
        $this->assertSame($firstEncrypted, $site->db_password_encrypted);
        $this->assertSame('IdempotentPass456', $site->getDecryptedDbPassword());
        $this->assertSame(1, Site::count(), 'No duplicate site/credential rows must be created');
    }

    /** Multiple legacy sites are all migrated in a single run. */
    public function test_migrates_multiple_legacy_sites_in_one_run(): void
    {
        $customer = $this->makeCustomer();
        $siteA = Site::create(['customer_id' => $customer->id, 'title' => 'Bulk Site A', 'db_password' => 'PassA']);
        $siteB = Site::create(['customer_id' => $customer->id, 'title' => 'Bulk Site B', 'db_password' => 'PassB']);

        $this->artisan('sites:migrate-db-passwords')->assertExitCode(0);

        $this->assertSame('PassA', $siteA->fresh()->getDecryptedDbPassword());
        $this->assertSame('PassB', $siteB->fresh()->getDecryptedDbPassword());
        $this->assertNull($siteA->fresh()->db_password);
        $this->assertNull($siteB->fresh()->db_password);
    }
}
