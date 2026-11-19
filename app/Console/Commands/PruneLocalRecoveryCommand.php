<?php

namespace App\Console\Commands;

use App\Services\Installer\LocalRecoveryRepository;
use Illuminate\Console\Command;

/**
 * Applies the Local Recovery Repository retention (RECOVERY_LOCAL_KEEP,
 * default 2 verified copies per scope) to copies already on disk. New
 * copies trigger it themselves; this clears the backlog. Dry run unless
 * --execute is given.
 */
class PruneLocalRecoveryCommand extends Command
{
    protected $signature = 'recovery:prune-local
                            {--execute : Delete the listed copies}
                            {--keep=   : Override RECOVERY_LOCAL_KEEP}';

    protected $description = 'Keep only the newest verified Local Recovery Repository copies per scope';

    public function handle(LocalRecoveryRepository $repo): int
    {
        $keep = $this->option('keep') !== null ? (int) $this->option('keep') : null;
        $result = $repo->prune($keep, dryRun: !$this->option('execute'));

        $this->info(($result['dry_run'] ? 'DRY RUN' : 'EXECUTE') . " — keep {$result['keep']} verified copies per scope ({$repo->getRepoPath()})");

        if ($result['items'] === []) {
            $this->info('Nothing to prune.');

            return self::SUCCESS;
        }

        $this->table(
            ['log', 'type', 'site', 'label', 'MB', 'action', 'path'],
            array_map(fn (array $item) => [
                $item['log_id'],
                $item['type'],
                $item['site_id'] ?? '-',
                $item['label'],
                number_format($item['size_bytes'] / 1048576, 1),
                $item['action'],
                $item['path'],
            ], $result['items'])
        );

        $bytes = array_sum(array_column($result['items'], 'size_bytes'));
        $this->info($result['dry_run']
            ? count($result['items']) . ' item(s), about ' . number_format($bytes / 1048576, 1) . ' MB. Nothing was deleted; re-run with --execute.'
            : 'Reclaimed ' . number_format($result['reclaimed_bytes'] / 1048576, 1) . ' MB.');

        return self::SUCCESS;
    }
}
