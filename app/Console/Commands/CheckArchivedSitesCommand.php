<?php
namespace App\Console\Commands;

use App\Services\Lifecycle\SiteLifecycleService;
use Illuminate\Console\Command;

/**
 * Checks ARCHIVED sites that have passed the permanent deletion threshold.
 * Default: 365 days after archival, no restore request, no payment.
 * Scheduled: daily 04:00
 */
class CheckArchivedSitesCommand extends Command
{
    protected $signature   = 'lifecycle:check-archived {--dry-run : Preview without applying changes}';
    protected $description = 'Permanently delete ARCHIVED sites whose retention period has expired';

    public function __construct(private SiteLifecycleService $lifecycle)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry   = $this->option('dry-run');
        $sites = $this->lifecycle->sitesReadyToDelete();

        if ($sites->isEmpty()) {
            $this->info('No archived sites ready for deletion.');
            return self::SUCCESS;
        }

        $this->info("Found {$sites->count()} site(s) eligible for permanent deletion.");

        foreach ($sites as $site) {
            $days = $site->daysSinceArchival();
            $this->line("  ✗ [{$site->id}] {$site->title} — archived {$days} days ago");

            if (! $dry) {
                $this->lifecycle->delete($site, "حذف خودکار پس از {$days} روز آرشیو");
            }
        }

        if ($dry) {
            $this->warn('Dry-run — no changes applied.');
        } else {
            $this->info("Marked {$sites->count()} site(s) as DELETED.");
        }

        return self::SUCCESS;
    }
}
