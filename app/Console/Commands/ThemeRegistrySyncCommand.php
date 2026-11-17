<?php

namespace App\Console\Commands;

use App\Services\ThemeCatalog\ThemeRegistrySync;
use Illuminate\Console\Command;

/**
 * Post-deploy step: install and release the themes of the active registry
 * (ThemeRegistry, `auto_sync => true`) that are not on this platform yet.
 * Idempotent, never recreates a theme someone deleted, never touches the
 * purged legacy catalog. See ThemeRegistrySync.
 *
 * Exit code: 0 unless --strict is given and a theme failed, so one theme
 * that fails the publish gate never fails a whole deploy. The failure is
 * printed and logged.
 */
class ThemeRegistrySyncCommand extends Command
{
    protected $signature = 'themes:sync-registry
        {--dry-run : Show what would be installed or released, change nothing}
        {--strict : Exit non-zero when any theme failed}';

    protected $description = 'Install and release the active registry themes missing from this platform (post-deploy).';

    public function handle(ThemeRegistrySync $sync): int
    {
        $results = $sync->sync((bool) $this->option('dry-run'));

        if ($results === []) {
            $this->info('Active theme registry is empty; nothing to sync.');
            return self::SUCCESS;
        }

        $this->table(['slug', 'action', 'detail'], array_map(fn (array $r) => [$r['slug'], $r['action'], $r['detail']], $results));

        $failed = array_values(array_filter($results, fn (array $r) => $r['action'] === ThemeRegistrySync::FAILED));
        foreach ($failed as $r) {
            logger()->warning('themes:sync-registry failed', $r);
        }

        if ($failed !== [] && $this->option('strict')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
