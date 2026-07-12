<?php
namespace App\Console\Commands;

use App\Models\Site;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 13-O12c — one-time backfill of sites.db_password (legacy plaintext)
 * into sites.db_password_encrypted (CredentialVault), per the Phase 13-O12b
 * design (Option B). The legacy column is only ever nulled after a verified
 * encrypt/decrypt round-trip; nothing is written if that check fails.
 *
 * Idempotent: sites whose db_password_encrypted is already populated are
 * skipped, so this can be safely re-run any number of times (e.g. resuming
 * after a partial failure) without re-encrypting or duplicating anything.
 */
class MigrateSiteDbPasswordsCommand extends Command
{
    protected $signature = 'sites:migrate-db-passwords
                            {--dry-run : Show what would be migrated without changing anything}';

    protected $description = 'Backfill sites.db_password into sites.db_password_encrypted via CredentialVault, then null the legacy column';

    public function handle(CredentialVault $vault): int
    {
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

                            $site->update([
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
}
