<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Education\Services\EducationSyncService;

class VerifyEducationSyncCommand extends Command
{
    protected $signature = 'education:verify-sync
                            {site_id : Numeric site ID (run education:list-sites to find valid IDs)}
                            {--fix   : Automatically run a full sync to repair all detected issues}';

    protected $description = 'Verify education sync integrity for a site — reports missing, stale, and orphaned content.

  Workflow:
    1.  php artisan education:list-sites
    2.  php artisan education:verify-sync <ID>
    3.  php artisan education:verify-sync <ID> --fix    (if issues found)

  Exit codes:
    0 = clean (no issues)
    1 = issues found (or site not found)';

    public function handle(EducationSyncService $syncService): int
    {
        $siteId = (int) $this->argument('site_id');
        $fix    = $this->option('fix');

        // ── Validate ─────────────────────────────────────────────────────────
        if ($siteId <= 0) {
            $this->error("site_id must be a positive integer.");
            $this->showSiteHint();
            return self::FAILURE;
        }

        $site = DB::table('sites')->where('id', $siteId)->whereNull('deleted_at')->first();

        if (! $site) {
            $this->error("Site #{$siteId} not found.");
            $this->showSiteHint();
            return self::FAILURE;
        }

        $this->info("Verifying education sync state for site #{$siteId}: {$site->title} [{$site->domain}]");

        // ── Report ────────────────────────────────────────────────────────────
        $report = $syncService->verifySyncState($siteId);

        $this->line('');
        $this->line("  Eligible features : <fg=cyan>{$report['eligible_features']}</>");
        $this->line("  ✅ OK              : <fg=green>{$report['ok']}</>");
        $this->line("  ⚠️  Stale          : <fg=yellow>{$report['stale']}</>");
        $this->line("  ❌ Missing         : <fg=red>{$report['missing']}</>");
        $this->line("  🗑️  Orphaned       : <fg=gray>{$report['orphaned']}</>");
        $this->line('');

        if (! empty($report['report'])) {
            $rows = array_map(
                fn($r) => [$r['feature'], $r['status'], $r['id'] ?? '—'],
                $report['report']
            );
            $this->table(['Feature', 'Status', 'Article ID'], $rows);
        }

        // ── Last sync log ─────────────────────────────────────────────────────
        $lastLog = DB::table('edu_sync_logs')
            ->where('site_id', $siteId)
            ->orderByDesc('ran_at')
            ->first();

        if ($lastLog) {
            $this->line("  Last sync  : {$lastLog->ran_at}  [{$lastLog->sync_type}]  status={$lastLog->status}  articles={$lastLog->articles_synced}  duration={$lastLog->duration_ms}ms");
        } else {
            $this->line("  Last sync  : <fg=yellow>never run</>  — run education:sync-customer {$siteId} first");
        }

        $this->line('');

        $hasIssues = $report['missing'] > 0 || $report['stale'] > 0 || $report['orphaned'] > 0;

        if ($hasIssues && $fix) {
            $this->info('Running full sync to repair issues...');
            $result = $syncService->syncForSite($siteId, false);
            $this->info("Fix complete: {$result['articles_synced']} synced, {$result['articles_archived']} archived, {$result['articles_skipped']} skipped in {$result['duration_ms']}ms.");
            return self::SUCCESS;
        }

        if ($hasIssues) {
            $this->warn('Issues detected. Options:');
            $this->line("  php artisan education:verify-sync {$siteId} --fix");
            $this->line("  php artisan education:sync-customer {$siteId} --full");
            return self::FAILURE;
        }

        $this->info('✅ Sync state is clean. No issues found.');
        return self::SUCCESS;
    }

    private function showSiteHint(): void
    {
        $this->line('');
        $this->comment('Run <fg=yellow>php artisan education:list-sites</> to see all valid site IDs.');

        $sites = DB::table('sites')->whereNull('deleted_at')->select('id', 'title', 'domain', 'is_active')->limit(5)->get();
        if ($sites->isNotEmpty()) {
            $this->table(
                ['ID', 'Title', 'Domain', 'Active'],
                $sites->map(fn($s) => [$s->id, $s->title, $s->domain ?? '—', $s->is_active ? 'yes' : 'no'])
            );
        }
    }
}
