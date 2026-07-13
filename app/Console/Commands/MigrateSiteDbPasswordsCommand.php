<?php
namespace App\Console\Commands;

use App\Models\Site;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13-O12c — one-time backfill of sites.db_password (legacy plaintext)
 * into sites.db_password_encrypted (CredentialVault), per the Phase 13-O12b
 * design (Option B). The legacy column is only ever nulled after a verified
 * encrypt/decrypt round-trip; nothing is written if that check fails.
 *
 * Idempotent: sites whose db_password_encrypted is already populated are
 * skipped, so this can be safely re-run any number of times (e.g. resuming
 * after a partial failure) without re-encrypting or duplicating anything.
 *
 * Phase 13-O12e — adds `--verify`, a read-only safety gate that must report
 * remaining_plaintext_count = 0 before the legacy column is ever dropped
 * (see database/migrations/*_remove_db_password_from_sites_table.php).
 * Both `--verify` and the original migrate/--dry-run path are schema-aware:
 * once the legacy column has actually been dropped, they report that
 * migration is already complete instead of erroring on a missing column —
 * this command remains safe to run at any point in the app's lifecycle,
 * including against a database restored from a pre-removal backup that
 * still has the column.
 */
class MigrateSiteDbPasswordsCommand extends Command
{
    protected $signature = 'sites:migrate-db-passwords
                            {--dry-run : Show what would be migrated without changing anything}
                            {--verify : Report-only safety check; does not change any data}';

    protected $description = 'Backfill sites.db_password into sites.db_password_encrypted via CredentialVault, then null the legacy column';

    public function handle(CredentialVault $vault): int
    {
        if ($this->option('verify')) {
            return $this->verify();
        }

        if (!Schema::hasColumn('sites', 'db_password')) {
            $this->info('Legacy column sites.db_password no longer exists — nothing to migrate.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $legacyCount = 0;
        $migrated    = 0;
        $skipped     = 0;
        $failed      = 0;

        Site::whereNotNull('db_password')
            ->where('db_password', '!=', '')
            ->chunkById(100, function ($sites) use ($vault, $dryRun, &$legacyCount, &$migrated, &$skipped, &$failed) {
                foreach ($sites as $site) {
                    $legacyCount++;

                    if (!empty($site->db_password_encrypted)) {
                        // Already migrated (or created after O12a with both columns
                        // somehow populated) — nothing to do, never re-encrypt.
                        $skipped++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("[dry-run] Would migrate site #{$site->id} ({$site->title})");
                        $migrated++;
                        continue;
                    }

                    try {
                        DB::transaction(function () use ($site, $vault) {
                            $original  = $site->db_password;
                            $encrypted = $vault->encrypt($original);

                            // Safety check — never null the legacy column unless the
                            // ciphertext provably round-trips back to the exact
                            // original value.
                            if ($vault->decrypt($encrypted) !== $original) {
                                throw new \RuntimeException('Encrypt/decrypt round-trip mismatch — refusing to migrate.');
                            }

                            // Phase 13-O12e — db_password is no longer fillable (it's a
                            // deprecated column kept only for this one-time legacy path),
                            // so a raw query update is used here instead of $site->update()
                            // to actually null it out.
                            DB::table('sites')->where('id', $site->id)->update([
                                'db_password_encrypted' => $encrypted,
                                'db_password'           => null,
                            ]);
                        });

                        $migrated++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('sites:migrate-db-passwords failed for site', [
                            'site_id' => $site->id,
                            'error'   => $e->getMessage(),
                        ]);
                        $this->error("Site #{$site->id} failed: {$e->getMessage()}");
                    }
                }
            });

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '') . 'Legacy credentials found: ' . $legacyCount);
        $this->info(($dryRun ? '[dry-run] Would migrate' : 'Migrated') . ': ' . $migrated);
        $this->info('Skipped (already encrypted): ' . $skipped);
        $this->info('Failed: ' . $failed);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Phase 13-O12e — read-only safety gate. Must report
     * remaining_plaintext_count = 0 before the legacy sites.db_password
     * column is ever dropped. Never writes anything.
     */
    private function verify(): int
    {
        if (!Schema::hasColumn('sites', 'db_password')) {
            $this->info('total_sites: ' . Site::count());
            $this->info('encrypted_credentials_count: ' . Site::whereNotNull('db_password_encrypted')->where('db_password_encrypted', '!=', '')->count());
            $this->info('remaining_plaintext_count: 0 (legacy column already removed)');
            $this->info('missing_credentials: ' . Site::whereNull('db_password_encrypted')->orWhere('db_password_encrypted', '')->count());
            $this->info('Verification PASSED: legacy column no longer exists.');
            return self::SUCCESS;
        }

        $totalSites = Site::count();
        $encryptedCount = Site::whereNotNull('db_password_encrypted')->where('db_password_encrypted', '!=', '')->count();
        $remainingPlaintext = Site::whereNotNull('db_password')->where('db_password', '!=', '')->count();
        $missingCredentials = Site::where(function ($q) {
                $q->whereNull('db_password_encrypted')->orWhere('db_password_encrypted', '');
            })
            ->where(function ($q) {
                $q->whereNull('db_password')->orWhere('db_password', '');
            })
            ->count();

        $this->info('total_sites: ' . $totalSites);
        $this->info('encrypted_credentials_count: ' . $encryptedCount);
        $this->info('remaining_plaintext_count: ' . $remainingPlaintext);
        $this->info('missing_credentials: ' . $missingCredentials);

        if ($remainingPlaintext > 0) {
            $this->error("Verification FAILED: {$remainingPlaintext} site(s) still have a plaintext db_password. Run \`sites:migrate-db-passwords\` before removing the legacy column.");
            return self::FAILURE;
        }

        $this->info('Verification PASSED: no remaining plaintext db_password values.');
        return self::SUCCESS;
    }
}
