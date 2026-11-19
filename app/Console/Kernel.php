<?php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Scheduler heartbeat — written every minute so SCHEDULER_DEAD detection works accurately
        $schedule->call(fn() => Cache::put('scheduler_last_run', now(), 120))
            ->everyMinute()
            ->name('scheduler-heartbeat')
            ->withoutOverlapping();

        // اعلان خودکار انقضا: یک ماه قبل و هر ۱۰ روز — هر روز یک‌بار چک می‌شود
        $schedule->command('expiry:send-reminders')->daily()->at('08:00');

        // Phase 20 — Part 6: CRM task/opportunity reminders (deadline, overdue, stale deals) — only registered when the crm module is installed.
        if (class_exists(\SaaSPlatform\Modules\Crm\Console\Commands\SendCrmTaskRemindersCommand::class)) {
            $schedule->command('crm:send-task-reminders')->hourly();
        }

        // Phase 18-E — Part 8: SEO Advanced scheduled audit (rank drops, sitemap/technical errors, score drops) — only registered when the module is installed.
        if (class_exists(\SaaSPlatform\Modules\SeoAdvanced\Console\Commands\RunSeoAutomationCommand::class)) {
            $schedule->command('seo-advanced:run-automation')->daily()->at('07:00');
        }

        // Wave 8.0b — prune platform admin activity_logs (respect AUDIT_PRUNE_DRY_RUN)
        $schedule->command('activity-log:prune')->dailyAt('03:00');

        // INFRA-4: Daily database backup at 02:00 — 2 local copies (server
        // disk), 7 offsite copies on Arvan
        $schedule->command('db:backup --retention=2 --remote-retention=7')->dailyAt('02:00')
            ->appendOutputTo(storage_path('logs/db-backup.log'));

        // 8.3d: Education AI content generation — daily gap & deficit queue
        $schedule->command('education:generate-content')->dailyAt('04:00')
            ->withoutOverlapping();
        $schedule->command('education:generate-gap-content')->dailyAt('04:30')
            ->withoutOverlapping();
        $schedule->command('education:refresh-recommendations')->dailyAt('05:00')
            ->withoutOverlapping();

        // 8.4d: Export telemetry events to OTLP (Jaeger/Tempo) — only when endpoint configured
        if (config('services.otel.endpoint')) {
            $schedule->command('telemetry:export-otlp --limit=1000 --batch=100')
                ->everyThirtyMinutes()
                ->withoutOverlapping();
        }

        // MHC: Platform health snapshot every 2 minutes
        $schedule->command('monitor:platform')
            ->everyTwoMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/monitor-platform.log'));

        // INFRA-MONITORING-01: Health check all active sites every 5 minutes
        $schedule->command('monitor:sites')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/monitor-sites.log'));

        // FI-008: Scan runtime anomalies every 5 minutes (queue stalls, tenant isolation, security)
        $schedule->command('runtime:scan-anomalies')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/runtime-anomalies.log'));

        // Phase 2 DR Center: hourly backup health scan + Slack alerts (deduped 6h)
        $schedule->command('dr:scan-health')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/dr-health.log'));

        // LIFECYCLE: Check expired sites → GRACE_PERIOD (daily 08:05)
        $schedule->command('lifecycle:check-expired')
            ->dailyAt('08:05')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/lifecycle.log'));

        // LIFECYCLE: Process GRACE_PERIOD reminders + suspensions (daily 08:10)
        $schedule->command('lifecycle:check-grace-period')
            ->dailyAt('08:10')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/lifecycle.log'));

        // LIFECYCLE: Archive SUSPENDED sites past 90 days (daily 03:05)
        $schedule->command('lifecycle:check-suspended')
            ->dailyAt('03:05')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/lifecycle.log'));

        // LIFECYCLE: Delete ARCHIVED sites past 365 days (daily 04:05)
        $schedule->command('lifecycle:check-archived')
            ->dailyAt('04:05')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/lifecycle.log'));

        // RESOURCE GOVERNANCE: Calculate resource usage for all sites (daily 06:00)
        $schedule->command('resource:calculate --push')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/resource.log'));

        // RESOURCE GOVERNANCE: Purge old snapshots and bandwidth records (weekly Sunday 02:00)
        $schedule->command('resource:cleanup')
            ->weeklyOn(0, '02:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/resource.log'));

        // RESOURCE GOVERNANCE: Archive growth anomaly report (weekly Monday 07:00)
        $schedule->command('resource:archive-report')
            ->weeklyOn(1, '07:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/resource.log'));

        // RESOURCE GOVERNANCE: Monthly overage fee report (1st of each month 08:00)
        $schedule->command('resource:usage-report')
            ->monthlyOn(1, '08:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/resource.log'));

        // GAP-H04: Monthly overage fee billing — 2nd of each month 09:00 (after usage-report)
        $schedule->command('resource:bill-overage')
            ->monthlyOn(2, '09:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/resource-billing.log'))
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('resource:bill-overage scheduled run failed');
            });

        // DEVOPS: measure tenant web-root disk, classify danger zones, prune (every 6h).
        // Deletes only when RESOURCE_AUTO_PRUNE_ENABLED=true; otherwise records previews.
        $schedule->command('devops:govern-resources')
            ->everySixHours()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/resource.log'));

        // ARVAN-001 Phase 2: Weekly platform storage snapshot → ArvanCloud offsite (Sunday 03:30)
        $schedule->command('storage:backup')
            ->weeklyOn(0, '03:30')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/storage-backup.log'));

        // TENANT-BACKUP: Daily tenant database backup → ArvanCloud offsite (02:30 — after platform backup)
        $schedule->command('db:backup-tenants --retention=7')
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/tenant-backup.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
