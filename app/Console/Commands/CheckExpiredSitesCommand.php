<?php
namespace App\Console\Commands;

use App\Models\Site;
use App\Services\Lifecycle\SiteLifecycleService;
use Illuminate\Console\Command;

/**
 * Checks ACTIVE sites whose hosting/support has expired and moves them to GRACE_PERIOD.
 * Scheduled: daily 08:00
 */
class CheckExpiredSitesCommand extends Command
{
    protected $signature   = 'lifecycle:check-expired {--dry-run : Preview without applying changes}';
    protected $description = 'Move expired ACTIVE sites into GRACE_PERIOD';

    public function __construct(private SiteLifecycleService $lifecycle)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $sites = $this->lifecycle->sitesEnteringGracePeriod();

        if ($sites->isEmpty()) {
            $this->info('No expired sites found.');
            return self::SUCCESS;
        }

        $this->info("Found {$sites->count()} expired site(s).");

        foreach ($sites as $site) {
            $this->line("  → [{$site->id}] {$site->title} ({$site->domain})");

            if (! $dry) {
                $this->lifecycle->startGracePeriod($site, 'انقضای اشتراک');
            }
        }

        if ($dry) {
            $this->warn('Dry-run — no changes applied.');
        } else {
            $this->info("Grace period started for {$sites->count()} site(s).");
        }

        return self::SUCCESS;
    }
}
