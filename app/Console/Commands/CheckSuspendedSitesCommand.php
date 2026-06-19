<?php
namespace App\Console\Commands;

use App\Services\Lifecycle\SiteLifecycleService;
use Illuminate\Console\Command;

/**
 * Checks SUSPENDED sites that have exceeded the archive threshold and archives them.
 * Default: 90 days after suspension.
 * Scheduled: daily 03:00
 */
class CheckSuspendedSitesCommand extends Command
{
    protected $signature   = 'lifecycle:check-suspended {--dry-run : Preview without applying changes}';
    protected $description = 'Archive SUSPENDED sites that have exceeded the archive threshold';

    public function __construct(private SiteLifecycleService $lifecycle)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry   = $this->option('dry-run');
        $sites = $this->lifecycle->sitesReadyToArchive();

        if ($sites->isEmpty()) {
            $this->info('No suspended sites ready for archival.');
            return self::SUCCESS;
        }

        $this->info("Found {$sites->count()} site(s) ready for archival.");

        foreach ($sites as $site) {
            $days = $site->daysSinceSuspension();
            $this->line("  → [{$site->id}] {$site->title} — suspended {$days} days ago");

            if (! $dry) {
                $this->lifecycle->archive($site, "آرشیو خودکار پس از {$days} روز تعلیق");
            }
        }

        if ($dry) {
            $this->warn('Dry-run — no changes applied.');
        } else {
            $this->info("Archived {$sites->count()} site(s).");
        }

        return self::SUCCESS;
    }
}
