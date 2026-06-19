<?php

namespace App\Console\Commands;

use App\Services\ResourceGovernance\SiteResourceReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UsageReportCommand extends Command
{
    protected $signature = 'resource:usage-report
                            {--dry-run : Show report without sending notifications}';

    protected $description = 'Generate monthly resource usage and overage fee report for all sites';

    public function __construct(private SiteResourceReportService $reportService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->info('resource:usage-report' . ($dryRun ? ' [dry-run]' : ''));

        $summary = $this->reportService->platformSummary();
        $this->line("  کل سایت‌ها: {$summary['total_sites']}");
        $this->line("  مجموع فضا: {$summary['formatted_storage']}");
        $this->line("  مجموع دیتابیس: {$summary['formatted_db']}");
        $this->line("  مجموع آرشیو: {$summary['formatted_archive']}");
        $this->line("  مجموع ترافیک ماهانه: {$summary['formatted_bandwidth']}");
        $this->line("  سایت‌های بالای ۸۰٪: {$summary['sites_over_80_pct']}");
        $this->line("  سایت‌های بالای ۹۵٪: {$summary['sites_over_95_pct']}");

        if (!$dryRun) {
            $report = $this->reportService->generateMonthlyReport();
            $count  = count($report['sites']);
            $this->info("  {$count} سایت با اضافه‌مصرف — مجموع " . number_format($report['total_fee']) . " ریال.");
        }

        Log::info("resource:usage-report", ['summary' => $summary]);
        return self::SUCCESS;
    }
}
