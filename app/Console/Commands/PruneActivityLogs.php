<?php
namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PruneActivityLogs extends Command
{
    protected $signature = 'activity-log:prune {--days= : Retention window in days (defaults to config audit.retention_days)}';

    protected $description = 'Remove activity_logs rows older than the retention window';

    public function handle(): int
    {
        if (! Schema::hasTable('activity_logs')) {
            $this->warn('Table activity_logs does not exist.');

            return self::SUCCESS;
        }

        $daysOption = $this->option('days');
        $days = $daysOption !== null && $daysOption !== ''
            ? max(1, (int) $daysOption)
            : max(1, (int) config('audit.retention_days', 365));

        $cutoff = now()->subDays($days);
        $dry = (bool) config('audit.prune_dry_run', false);

        $count = ActivityLog::query()->where('created_at', '<', $cutoff)->count();

        if ($dry) {
            $this->info("DRY RUN (AUDIT_PRUNE_DRY_RUN=true): would delete {$count} row(s) older than {$cutoff->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $deletedTotal = 0;
        do {
            $deleted = ActivityLog::query()
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();
            $deletedTotal += $deleted;
        } while ($deleted > 0);

        $this->info("Deleted {$deletedTotal} activity log row(s).");

        return self::SUCCESS;
    }
}
