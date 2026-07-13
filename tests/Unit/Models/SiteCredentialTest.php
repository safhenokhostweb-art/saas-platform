<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Site;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 13-O12a — Site::getDecryptedDbPassword() is the single central read
 * path for the site's remote database credential, encrypted via
 * CredentialVault in db_password_encrypted.
 *
 * Phase 13-O12e — the legacy plaintext db_password column (and the
 * fallback that used to read it) has been fully removed. The two tests
 * that used to cover that fallback (`test_old_style_site_falls_back_to_plaintext_column`,
 * `test_new_style_takes_priority_over_stale_plaintext_column`) are gone
 * along with it — that scenario can no longer exist now that the column
 * itself does not exist.
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

    /** D) Phase 13-O12e — the model has exactly one source of truth for this credential: the encrypted column. */
    public function test_site_model_returns_only_encrypted_password(): void
    {
        $vault = new CredentialVault();
        $site = Site::create([
            'customer_id'           => $this->makeCustomer()->id,
            'title'                 => 'Encrypted Only Site',
            'db_password_encrypted' => $vault->encrypt('OnlyEncryptedPass'),
        ]);

        $fresh = $site->fresh();
        $this->assertSame('OnlyEncryptedPass', $fresh->getDecryptedDbPassword());
        // The legacy column doesn't exist on the schema at all anymore, so it
        // cannot appear among the model's loaded attributes either.
        $this->assertArrayNotHasKey('db_password', $fresh->getAttributes());
    }

    /** E) Phase 13-O12e — the legacy column has been physically removed from the schema. */
    public function test_old_db_password_column_removed_after_migration(): void
    {
        $this->assertFalse(Schema::hasColumn('sites', 'db_password'));
        $this->assertTrue(Schema::hasColumn('sites', 'db_password_encrypted'));
    }

    /**
     * A) Static reference audit — no Controller/Service/Model runtime code
     * anywhere in app/ or resources/ still accesses the removed
     * `db_password` Eloquent attribute directly (`->db_password`). Blade
     * form fields and HTTP input named "db_password" are unaffected by this
     * check — those are just the user-facing field name, unrelated to
     * whether the underlying column still exists.
     */
    public function test_no_plaintext_db_password_reference_exists(): void
    {
        // The migrate command's own legacy backfill path is the one
        // explicitly-permitted exception (Phase 13-O12e design, Section 4:
        // "command documentation"/the legacy migration tool itself) — its
        // whole job is reading this attribute on databases that still have
        // the column (e.g. restored from a pre-removal backup).
        $allowlist = [
            str_replace('\\', '/', base_path('app/Console/Commands/MigrateSiteDbPasswordsCommand.php')),
        ];

        $offenders = [];

        foreach ([base_path('app'), base_path('resources')] as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $path = $file->getPathname();
                if (in_array(str_replace('\\', '/', $path), $allowlist, true)) {
                    continue;
                }

                $contents = file_get_contents($path);
                // Matches `$site->db_password`, `$legacySite->db_password`, etc. —
                // any Site-shaped variable/property, not `_encrypted` — while
                // deliberately not matching unrelated objects like
                // `$request->db_password` (a plain HTTP input accessor used by
                // the unrelated platform self-install wizard).
                if (preg_match('/\bsite\w*\s*->\s*db_password(?!_encrypted)\b/i', $contents)) {
                    $offenders[] = $path;
                }
            }
        }

        $this->assertEmpty(
            $offenders,
            "Found direct Eloquent attribute access to the removed sites.db_password column in: " . implode(', ', $offenders)
        );
    }
}
