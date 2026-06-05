<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Education\Services\EducationSyncService;

class SyncCustomerEducationCommand extends Command
{
    protected $signature = 'education:sync-customer
                            {site_id : Numeric site ID (see education:list-sites for valid IDs)}
                            {--full    : Force full sync — ignore existing hashes}
                            {--dry-run : Show eligible features without persisting}';

    protected $description = 'Sync education content for one customer site. Use education:list-sites to find valid site IDs.';

    public function handle(EducationSyncService $syncService): int
    {
        $siteId       = (int) $this->argument('site_id');
        $differential = ! $this->option('full');
        $dryRun       = $this->option('dry-run');

        // ── Validate site_id ────────────────────────────────────────────────
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

        $this->info("Education sync for site #{$siteId}: {$site->title} [{$site->domain}] [" . ($differential ? 'differential' : 'full') . "]");

        if (! $site->is_active) {
            $this->warn("  ⚠  Site #{$siteId} is inactive. Syncing anyway (use --help to see options).");
        }

        // ── Dry run ──────────────────────────────────────────────────────────
        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be persisted.');
            $eligible = $syncService->discoverEligibleFeatures($siteId);
            $this->info("Eligible features: {$eligible->count()}");
            foreach ($eligible as $f) {
                $this->line("  ✓ {$f->module_slug}.{$f->feature_slug}  <fg=gray>({$f->route_name})</>");
            }
            return self::SUCCESS;
        }

        // ── Execute sync ─────────────────────────────────────────────────────
        $result = $syncService->syncForSite($siteId, $differential);

        $this->renderResult($result);

        return $result['status'] === 'failed' ? self::FAILURE : self::SUCCESS;
    }

    private function renderResult(array $r): void
    {
        $statusIcon = $r['status'] === 'success' ? '✅' : '⚠️';

        $this->line('');
        $this->info("{$statusIcon} Sync complete in {$r['duration_ms']}ms");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Articles synced',        $r['articles_synced']],
                ['Walkthroughs synced',    $r['walkthroughs_synced']],
                ['Learning paths synced',  $r['learning_paths_synced']],
                ['Articles archived',      $r['articles_archived']],
                ['Articles skipped (up-to-date)', $r['articles_skipped']],
            ]
        );

        if (! empty($r['details'])) {
            $this->line('');
            $this->comment('Details (first 20):');
            foreach (array_slice($r['details'], 0, 20) as $line) {
                $this->line("  {$line}");
            }
        }

        if (! empty($r['message'])) {
            $this->warn($r['message']);
        }
    }

    private function showSiteHint(): void
    {
        $this->line('');
        $this->comment('Run <fg=yellow>php artisan education:list-sites</> to see all valid site IDs.');
        $this->line('');

        // Show a quick inline preview
        $sites = DB::table('sites')->whereNull('deleted_at')->select('id', 'title', 'domain', 'is_active')->limit(5)->get();
        if ($sites->isNotEmpty()) {
            $this->table(
                ['ID', 'Title', 'Domain', 'Active'],
                $sites->map(fn($s) => [$s->id, $s->title, $s->domain ?? '—', $s->is_active ? 'yes' : 'no'])
            );
        }
    }
}
