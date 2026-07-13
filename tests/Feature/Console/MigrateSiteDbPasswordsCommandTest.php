<?php

namespace Tests\Feature\Console;

use App\Models\Customer;
use App\Models\Site;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 13-O12c — php artisan sites:migrate-db-passwords backfills
 * sites.db_password (legacy plaintext) into sites.db_password_encrypted
 * (CredentialVault), then nulls the legacy column, per the Phase 13-O12b
 * design (Option B). Never runs without a verified encrypt/decrypt
 * round-trip, and is safe to re-run (idempotent).
 *
 * Phase 13-O12e — sites.db_password has since been fully removed from the
 * live schema (see the ..._remove_db_password_from_sites_table.php
 * migration). The command remains schema-aware and safe to run against a
 * database that still has the column (e.g. one restored from a
 * pre-removal backup), so the tests that exercise that original backfill
 * behavior temporarily re-add the legacy column for the duration of a
 * single test and always drop it again in tearDown() — MySQL DDL is not
 * transactional, so this cleanup cannot rely on RefreshDatabase's per-test
 * transaction rollback.
 */
class MigrateSiteDbPasswordsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['installer.master_key' => base64_encode(random_bytes(32))]);
    }

    protected function tearDown(): void
    {
        if (Schema::hasColumn('sites', 'db_password')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropColumn('db_password');
            });
        }

        // The ALTER TABLE above (and in addLegacyColumn()) triggers an
        // implicit commit in MySQL, which ends RefreshDatabase's per-test
        // transaction early — so its rollback can no longer undo rows this
        // test inserted afterward. Clean up explicitly so later tests in
        // this file don't inherit leaked sites/customers.
        Site::query()->delete();
        Customer::query()->delete();

        parent::tearDown();
    }

    private function makeCustomer(): Customer
    {
        return Customer::create(['name' => 'Migrate Cmd Customer', 'mobile' => '09121234567']);
    }

    /** Simulates a database that still has the legacy column (e.g. a pre-O12e backup). */
    private function addLegacyColumn(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('db_password')->nullable();
        });
    }

    private function setLegacyPassword(Site $site, string $plaintext): void
    {
        DB::table('sites')->where('id', $site->id)->update(['db_password' => $plaintext]);
    }

    private function legacyPassword(Site $site): ?string
    {
        return DB::table('sites')->where('id', $site->id)->value('db_password');
    }

    // ─── Phase 13-O12e — --verify ───────────────────────────────────────────

    /** B) --verify fails when a plaintext legacy value still exists. */
    public function test_verify_command_fails_when_plaintext_exists(): void
    {
        $this->addLegacyColumn();
        $site = Site::create(['customer_id' => $this->makeCustomer()->id, 'title' => 'Still Legacy Site']);
        $this->setLegacyPassword($site, 'StillPlaintext');

        $this->artisan('sites:migrate-db-passwords', ['--verify' => true])->assertExitCode(1);
    }

    /**
     * C) --verify passes once no plaintext remains. This runs against the
     * real, current schema (legacy column already fully removed) — the
     * actual end state this whole phase exists to reach.
     */
    public function test_verify_command_passes_after_migration(): void
    {
        $this->assertFalse(Schema::hasColumn('sites', 'db_password'), 'Precondition: legacy column must already be gone in the current schema');

        $this->artisan('sites:migrate-db-passwords', ['--verify' => true])->assertExitCode(0);
    }

    /** --verify also passes against a schema that still has the column, as long as it's empty everywhere. */
    public function test_verify_command_passes_with_legacy_column_present_but_empty(): void
    {
        $this->addLegacyColumn();
        Site::create(['customer_id' => $this->makeCustomer()->id, 'title' => 'Clean Legacy Column Site']);

        $this->artisan('sites:migrate-db-passwords', ['--verify' => true])->assertExitCode(0);
    }

    // ─── Legacy backfill behavior (simulated pre-O12e schema) ──────────────

    /** D) legacy migration: db_password → db_password_encrypted → db_password = null. */
    public function test_migrates_legacy_plaintext_password_and_nulls_old_column(): void
    {
        $this->addLegacyColumn();
        $site = Site::create(['customer_id' => $this->makeCustomer()->id, 'title' => 'Legacy Site']);
        $this->setLegacyPassword($site, 'LegacyPlainPass123');

        $this->artisan('sites:migrate-db-passwords')->assertExitCode(0);

        $this->assertNull($this->legacyPassword($site));
        $this->assertNotEmpty($site->fresh()->db_password_encrypted);
        $this->assertSame('LegacyPlainPass123', $site->fresh()->getDecryptedDbPassword());
    }

    /** F) decrypt(encrypt(original)) === original, verified as part of the migrated result. */
    public function test_migrated_password_decrypts_back_to_the_exact_original_value(): void
    {
        $this->addLegacyColumn();
        $original = 'Exact-Original-Value-!@#$%^&*()_+';
        $site = Site::create(['customer_id' => $this->makeCustomer()->id, 'title' => 'Round Trip Site']);
        $this->setLegacyPassword($site, $original);

        $this->artisan('sites:migrate-db-passwords');

        $this->assertSame($original, $site->fresh()->getDecryptedDbPassword());
    }

    /** New-style sites (already encrypted, no legacy value) are left untouched. */
    public function test_skips_sites_that_have_no_legacy_plaintext_password(): void
    {
        $vault = new CredentialVault();
        $site = Site::create([
            'customer_id'           => $this->makeCustomer()->id,
            'title'                 => 'Already New Style Site',
            'db_password_encrypted' => $vault->encrypt('AlreadyEncrypted'),
        ]);

        $this->artisan('sites:migrate-db-passwords')->assertExitCode(0);

        $this->assertSame('AlreadyEncrypted', $site->fresh()->getDecryptedDbPassword());
    }

    /** Sites with neither column set are simply not touched or counted as failures. */
    public function test_sites_without_any_password_are_ignored(): void
    {
        $site = Site::create(['customer_id' => $this->makeCustomer()->id, 'title' => 'No Credential Site']);

        $this->artisan('sites:migrate-db-passwords')->assertExitCode(0);

        $this->assertNull($site->fresh()->db_password_encrypted);
    }

    /** --dry-run must never write anything. */
    public function test_dry_run_does_not_modify_any_data(): void
    {
        $this->addLegacyColumn();
        $site = Site::create(['customer_id' => $this->makeCustomer()->id, 'title' => 'Dry Run Site']);
        $this->setLegacyPassword($site, 'ShouldNotBeTouched');

        $this->artisan('sites:migrate-db-passwords', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame('ShouldNotBeTouched', $this->legacyPassword($site));
        $this->assertNull($site->fresh()->db_password_encrypted);
    }

    /** E) idempotency — running the command twice must not corrupt or duplicate the credential. */
    public function test_running_command_twice_is_idempotent(): void
    {
        $this->addLegacyColumn();
        $site = Site::create(['customer_id' => $this->makeCustomer()->id, 'title' => 'Idempotent Site']);
        $this->setLegacyPassword($site, 'IdempotentPass456');

        $this->artisan('sites:migrate-db-passwords');
        $firstEncrypted = $site->fresh()->db_password_encrypted;

        $this->artisan('sites:migrate-db-passwords')->assertExitCode(0);

        $this->assertNull($this->legacyPassword($site));
        // Untouched by the second run — same ciphertext, not re-encrypted.
        $this->assertSame($firstEncrypted, $site->fresh()->db_password_encrypted);
        $this->assertSame('IdempotentPass456', $site->fresh()->getDecryptedDbPassword());
        $this->assertSame(1, Site::count(), 'No duplicate site/credential rows must be created');
    }

    /** Multiple legacy sites are all migrated in a single run. */
    public function test_migrates_multiple_legacy_sites_in_one_run(): void
    {
        $this->addLegacyColumn();
        $customer = $this->makeCustomer();
        $siteA = Site::create(['customer_id' => $customer->id, 'title' => 'Bulk Site A']);
        $siteB = Site::create(['customer_id' => $customer->id, 'title' => 'Bulk Site B']);
        $this->setLegacyPassword($siteA, 'PassA');
        $this->setLegacyPassword($siteB, 'PassB');

        $this->artisan('sites:migrate-db-passwords')->assertExitCode(0);

        $this->assertSame('PassA', $siteA->fresh()->getDecryptedDbPassword());
        $this->assertSame('PassB', $siteB->fresh()->getDecryptedDbPassword());
        $this->assertNull($this->legacyPassword($siteA));
        $this->assertNull($this->legacyPassword($siteB));
    }
}
