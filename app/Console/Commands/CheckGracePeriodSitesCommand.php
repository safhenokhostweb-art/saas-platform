<?php
namespace App\Console\Commands;

use App\Models\Site;
use App\Models\SiteLifecycleLog;
use App\Services\Lifecycle\SiteLifecycleService;
use App\Services\Lifecycle\SiteLifecycleNotificationService;
use Illuminate\Console\Command;

/**
 * Checks GRACE_PERIOD sites that have passed their deadline and suspends them.
 * Also sends mid-period reminder (5–8 days left) and final reminder (≤3 days left).
 * Scheduled: daily 08:10
 */
class CheckGracePeriodSitesCommand extends Command
{
    protected $signature   = 'lifecycle:check-grace-period {--dry-run : Preview without applying changes}';
    protected $description = 'Suspend GRACE_PERIOD sites whose deadline has passed; send mid + final SMS reminders';

    public function __construct(
        private SiteLifecycleService $lifecycle,
        private SiteLifecycleNotificationService $notifications,
    ) {
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

        // Sites 5–8 days from end → MID-PERIOD REMINDER (once per grace period)
        $midRemindSites = Site::where('lifecycle_status', 'grace_period')
            ->whereNotNull('grace_period_ends_at')
            ->whereBetween('grace_period_ends_at', [now()->addDays(5), now()->addDays(8)])
            ->get()
            ->filter(function (Site $site) {
                // Only send if no mid-reminder log exists for this grace period
                return ! SiteLifecycleLog::where('site_id', $site->id)
                    ->where('from_status', 'grace_period')
                    ->where('to_status', 'grace_period')
                    ->where('reason', 'grace_period_mid_reminder')
                    ->where('created_at', '>=', $site->grace_period_started_at ?? now()->subDays(30))
                    ->exists();
            });

        if ($midRemindSites->isNotEmpty()) {
            $this->info("Sending {$midRemindSites->count()} mid-period reminder(s).");
            foreach ($midRemindSites as $site) {
                $this->line("  ~ [{$site->id}] {$site->title} — mid-period reminder");
                if (! $dry) {
                    $this->notifications->gracePeriodMidReminder($site);
                    SiteLifecycleLog::create([
                        'site_id'    => $site->id,
                        'from_status'=> 'grace_period',
                        'to_status'  => 'grace_period',
                        'reason'     => 'grace_period_mid_reminder',
                        'changed_by' => null,
                        'is_system'  => true,
                        'metadata'   => ['days_remaining' => $site->gracePeriodDaysRemaining(), 'timestamp' => now()->toIso8601String()],
                    ]);
                }
            }
        }

        // Sites within 3 days of grace period end → FINAL REMINDER
        $remindSites = Site::where('lifecycle_status', 'grace_period')
            ->whereNotNull('grace_period_ends_at')
            ->whereBetween('grace_period_ends_at', [now(), now()->addDays(3)])
            ->get();

        if ($remindSites->isNotEmpty()) {
            $this->info("Sending {$remindSites->count()} final grace period reminder(s).");
            foreach ($remindSites as $site) {
                $this->line("  ! [{$site->id}] {$site->title} — final reminder");
                if (! $dry) {
                    $this->notifications->gracePeriodReminder($site);
                }
            }
        }

        if ($expiredSites->isEmpty() && $midRemindSites->isEmpty() && $remindSites->isEmpty()) {
            $this->info('No grace period actions needed.');
        }

        if ($dry) {
            $this->warn('Dry-run — no changes applied.');
        }

        return self::SUCCESS;
    }
}
