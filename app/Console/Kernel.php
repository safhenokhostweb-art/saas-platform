<?php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // اعلان خودکار انقضا: یک ماه قبل و هر ۱۰ روز — هر روز یک‌بار چک می‌شود
        $schedule->command('expiry:send-reminders')->daily()->at('08:00');

        // Wave 8.0b — prune platform admin activity_logs (respect AUDIT_PRUNE_DRY_RUN)
        $schedule->command('activity-log:prune')->dailyAt('03:00');

        // INFRA-4: Daily database backup at 02:00, retain last 7 backups
        $schedule->command('db:backup --retention=7')->dailyAt('02:00')
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
