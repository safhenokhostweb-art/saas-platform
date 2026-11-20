<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\SiteResourceLimit;
use App\Models\SiteResourceUsage;
use App\Services\ResourceGovernance\SiteResourceCalculatorService;
use App\Services\ResourceGovernance\SiteResourceGovernorService;
use App\Services\ResourceGovernance\SiteResourceAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CalculateSiteResourcesCommand extends Command
{
    protected $signature = 'resource:calculate
                            {--site= : Process only a specific site ID}
                            {--dry-run : Calculate but do not persist or alert}
                            {--push : Push summary to customer-site settings after calculation}';

    protected $description = 'Calculate and store resource usage metrics for all customer sites';

    public function __construct(
        private SiteResourceCalculatorService $calculator,
        private SiteResourceGovernorService $governor,
        private SiteResourceAlertService $alertService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $push    = $this->option('push');
        $siteId  = $this->option('site');

        $query = Site::query()->whereNotIn('lifecycle_status', ['deleted']);
        if ($siteId) {
            $query->where('id', $siteId);
        }

        $sites = $query->get();
        $this->info("resource:calculate — {$sites->count()} سایت" . ($dryRun ? ' [dry-run]' : ''));

        $processed = 0;
        $errors    = 0;

        foreach ($sites as $site) {
            try {
                $metrics = $this->calculator->calculate($site, $dryRun);

                // Alerts first (2026-11-19): they advance the soft-disk grace
                // state, which the summary pushed below then carries.
                $usage = null;
                if (!$dryRun) {
                    $usage = SiteResourceUsage::where('site_id', $site->id)->first();
                    $limit = SiteResourceLimit::defaultForSite($site);
                    if ($usage) {
                        $this->alertService->checkAndAlertStorage($site, $usage, $limit);
                        $usage->refresh();
                    }
                }

                if ($push && !$dryRun) {
                    try {
                        $this->calculator->pushSummaryToCustomerSite($site, $metrics, $usage);
                    } catch (\Throwable $pushErr) {
                        // GAP-M08: notify platform admin on push failure
                        $this->alertService->alertPushFailed($site, $pushErr->getMessage());
                        $this->warn("  ⚠ push failed for [{$site->id}] {$site->title}: {$pushErr->getMessage()}");
                    }
                }

                $processed++;
                $this->line("  ✓ [{$site->id}] {$site->title} — DB: " . \App\Models\SiteResourceUsage::formatBytes($metrics['db_bytes']) . ", فایل: " . ($metrics['storage_bytes'] === null ? 'اندازه‌گیری نشده' : \App\Models\SiteResourceUsage::formatBytes($metrics['storage_bytes'])));
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("  ✗ [{$site->id}] {$site->title}: {$e->getMessage()}");
                Log::warning("resource:calculate error for site {$site->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("کامل شد: {$processed} پردازش شد، {$errors} خطا.");
        Log::info("resource:calculate complete", ['processed' => $processed, 'errors' => $errors]);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
