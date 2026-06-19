<?php

namespace App\Console\Commands;

use App\Models\SiteResourceUsage;
use App\Services\ResourceGovernance\SiteResourceReportService;
use App\Services\ResourceGovernance\SiteResourceAlertService;
use App\Models\SiteStorageSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ArchiveReportCommand extends Command
{
    protected $signature = 'resource:archive-report
                            {--dry-run : Show results without sending alerts}';

    protected $description = 'Scan for oversized archives and abnormal archive growth, then notify admin';

    public function __construct(
        private SiteResourceReportService $reportService,
        private SiteResourceAlertService $alertService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->info('resource:archive-report' . ($dryRun ? ' [dry-run]' : ''));

        // Oversized archives report
        $result = $dryRun ? ['count' => 0] : $this->reportService->generateArchiveReport();
        $this->info("  {$result['count']} آرشیو بزرگ شناسایی شد.");

        // Check for abnormal growth (compare today vs yesterday)
        $threshold = config('resource_governance.archive_growth_anomaly_bytes', 104857600);
        $today     = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $growthCount = 0;
        $usages = SiteResourceUsage::with('site')->where('archive_bytes', '>', 0)->get();

        foreach ($usages as $usage) {
            $todaySnap = SiteStorageSnapshot::where('site_id', $usage->site_id)
                ->where('snapshot_date', $today)->first();
            $yesterdaySnap = SiteStorageSnapshot::where('site_id', $usage->site_id)
                ->where('snapshot_date', $yesterday)->first();

            if ($todaySnap && $yesterdaySnap) {
                $growth = $todaySnap->archive_bytes - $yesterdaySnap->archive_bytes;
                if ($growth > $threshold) {
                    $growthCount++;
                    $this->warn("  رشد غیرعادی آرشیو: [{$usage->site_id}] {$usage->site->title} — " . SiteResourceUsage::formatBytes($growth));
                    if (!$dryRun && $usage->site) {
                        $this->alertService->alertAbnormalArchiveGrowth($usage->site, $growth);
                    }
                }
            }
        }

        $this->info("  {$growthCount} رشد غیرعادی شناسایی شد.");
        Log::info("resource:archive-report", ['oversized' => $result['count'], 'abnormal_growth' => $growthCount]);

        return self::SUCCESS;
    }
}
