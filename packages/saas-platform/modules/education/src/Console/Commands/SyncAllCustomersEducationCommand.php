<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Education\Services\EducationSyncService;

class SyncAllCustomersEducationCommand extends Command
{
    protected $signature = 'education:sync-all-customers
                            {--full        : Force full sync for all sites (re-generate all, ignore hashes)}
                            {--active-only : Process only active sites (is_active = 1)}';

    protected $description = 'Sync education content for all customer sites. Run education:list-sites first to preview targets.';

    public function handle(EducationSyncService $syncService): int
    {
        $differential = ! $this->option('full');
        $activeOnly   = $this->option('active-only');

        // Use raw DB query to avoid SoftDeletes ambiguity (Site model does not use SoftDeletes)
        $query = DB::table('sites')->whereNull('deleted_at');
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $sites = $query->select('id', 'title', 'domain', 'is_active')->get();

        if ($sites->isEmpty()) {
            $this->warn('No sites found. Run education:list-sites to investigate.');
            return self::SUCCESS;
        }

        $mode = $differential ? 'differential' : 'full';
        $filter = $activeOnly ? ' [active-only]' : '';
        $this->info("Syncing {$sites->count()} sites [{$mode}]{$filter}...");
        $this->line('');

        $totalSynced   = 0;
        $totalArchived = 0;
        $totalSkipped  = 0;
        $totalFailed   = 0;
        $startedAll    = microtime(true);

        foreach ($sites as $site) {
            $statusTag = $site->is_active ? '' : ' <fg=gray>[inactive]</>';
            $this->line("  → Site #{$site->id}: {$site->title} [{$site->domain}]{$statusTag}");

            try {
                $result = $syncService->syncForSite($site->id, $differential);
                $totalSynced   += $result['articles_synced'];
                $totalArchived += $result['articles_archived'];
                $totalSkipped  += $result['articles_skipped'];

                $icon = $result['status'] === 'success' ? '    ✓' : '    ⚠';
                $this->line("{$icon} articles +{$result['articles_synced']}  archived {$result['articles_archived']}  skipped {$result['articles_skipped']}  walkthroughs +{$result['walkthroughs_synced']}  ({$result['duration_ms']}ms)");
            } catch (\Throwable $e) {
                $totalFailed++;
                $this->error("    ✗ FAILED: {$e->getMessage()}");
            }
        }

        $totalMs = (int) ((microtime(true) - $startedAll) * 1000);
        $this->line('');
        $this->info("All done in {$totalMs}ms.");
        $this->table(
            ['Metric', 'Total'],
            [
                ['Sites processed',  $sites->count()],
                ['Sites failed',     $totalFailed],
                ['Articles synced',  $totalSynced],
                ['Articles archived', $totalArchived],
                ['Articles skipped', $totalSkipped],
            ]
        );

        if ($totalFailed > 0) {
            $this->warn("⚠  {$totalFailed} site(s) failed. Run education:verify-sync <ID> on each to investigate.");
        }

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
