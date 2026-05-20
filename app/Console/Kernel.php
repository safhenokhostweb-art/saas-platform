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
