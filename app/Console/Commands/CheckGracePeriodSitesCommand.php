<?php
namespace App\Console\Commands;

use App\Models\Site;
use App\Services\Lifecycle\SiteLifecycleService;
use Illuminate\Console\Command;

/**
 * Checks GRACE_PERIOD sites that have passed their deadline and suspends them.
 * Also sends reminder SMS if grace period ends within 3 days.
 * Scheduled: daily 08:00
 */
class CheckGracePeriodSitesCommand extends Command
{
    protected $signature   = 'lifecycle:check-grace-period {--dry-run : Preview without applying changes}';
    protected $description = 'Suspend GRACE_PERIOD sites whose deadline has passed; send reminders for expiring soon';

    public function __construct(private SiteLifecycleService $lifecycle)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        // Sites whose grace period has ended → SUSPEND
        $expiredSites = $this->lifecycle->sitesEndingGracePeriod();

        if ($expiredSites->isNotEmpty()) {
            $this->info("Found {$expiredSites->count()} grace-period-expired site(s).");
            foreach ($expiredSites as $site) {
                $this->line("  ✗ [{$site->id}] {$site->title} — suspending");
                if (! $dry) {
                    $this->lifecycle->suspend($site, 'پایان دوره اعتبار بدون تمدید');
                }
            }
        }

        // Sites within 3 days of grace period end → REMINDER
        $remindSites = Site::where('lifecycle_status', 'grace_period')
            ->whereNotNull('grace_period_ends_at')
            ->whereBetween('grace_period_ends_at', [now(), now()->addDays(3)])
            ->get();

        if ($remindSites->isNotEmpty()) {
            $this->info("Sending {$remindSites->count()} grace period reminder(s).");
            foreach ($remindSites as $site) {
                $this->line("  ! [{$site->id}] {$site->title} — reminder");
                if (! $dry) {
                    app(\App\Services\Lifecycle\SiteLifecycleNotificationService::class)
                        ->gracePeriodReminder($site);
                }
            }
        }

        if ($expiredSites->isEmpty() && $remindSites->isEmpty()) {
            $this->info('No grace period actions needed.');
        }

        if ($dry) {
            $this->warn('Dry-run — no changes applied.');
        }

        return self::SUCCESS;
    }
}
