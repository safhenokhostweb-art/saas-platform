<?php

namespace App\Console\Commands;

use App\Services\ResourceGovernance\SiteResourceReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupResourceDataCommand extends Command
{
    protected $signature = 'resource:cleanup
                            {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Purge old storage snapshots and stale bandwidth records';

    public function __construct(private SiteResourceReportService $reportService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->info('resource:cleanup' . ($dryRun ? ' [dry-run]' : ''));

        if ($dryRun) {
            $retentionDays = config('resource_governance.snapshot_retention_days', 90);
            $this->line("  اسنپ‌شات‌های قدیمی‌تر از {$retentionDays} روز حذف می‌شوند.");
            $this->line("  رکوردهای پهنای‌باند قدیمی‌تر از ۱۲ ماه حذف می‌شوند.");
            return self::SUCCESS;
        }

        $snapshots = $this->reportService->purgeOldSnapshots();
        $bandwidth = $this->reportService->purgeOldBandwidthRecords();

        $this->info("  {$snapshots} اسنپ‌شات قدیمی حذف شد.");
        $this->info("  {$bandwidth} رکورد پهنای‌باند قدیمی حذف شد.");

        Log::info("resource:cleanup", ['snapshots_deleted' => $snapshots, 'bandwidth_deleted' => $bandwidth]);

        return self::SUCCESS;
    }
}
