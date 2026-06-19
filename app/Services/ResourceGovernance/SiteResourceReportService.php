<?php

namespace App\Services\ResourceGovernance;

use App\Models\Site;
use App\Models\SiteResourceUsage;
use App\Models\SiteStorageSnapshot;
use App\Models\SiteBandwidthUsage;
use App\Services\Infrastructure\SlackAlertService;
use App\Services\Notification\NotificationCenterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SiteResourceReportService
{
    public function __construct(
        private SiteResourceGovernorService $governor,
        private NotificationCenterService $nc,
        private SlackAlertService $slack
    ) {}

    /**
     * Generate monthly usage overage report across all sites.
     * Returns array of sites with overages and total fees.
     */
    public function generateMonthlyReport(): array
    {
        $sites   = Site::whereNotIn('lifecycle_status', ['deleted'])->get();
        $report  = [];
        $totalFee = 0;

        foreach ($sites as $site) {
            $items = $this->governor->calculateOverageFees($site);
            if (empty($items)) continue;

            $siteFee   = array_sum(array_column($items, 'amount'));
            $totalFee += $siteFee;
            $report[]  = [
                'site'      => $site,
                'line_items' => $items,
                'total_fee'  => $siteFee,
            ];
        }

        if (!empty($report)) {
            $count   = count($report);
            $formatted = number_format($totalFee);
            $message  = "گزارش ماهانه: {$count} سایت با اضافه‌مصرف — مجموع {$formatted} ریال.";

            $this->nc->notify([
                'type'          => 'monthly_overage_report',
                'priority'      => 'medium',
                'title'         => 'گزارش اضافه‌مصرف منابع (ماهانه)',
                'message'       => $message,
                'audience_type' => 'platform_admin',
                'channel'       => 'dashboard',
                'link'          => route('admin.resource-governance.index'),
            ]);
        }

        return [
            'sites'      => $report,
            'total_fee'  => $totalFee,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Scan for sites with oversized archives and report to admin.
     */
    public function generateArchiveReport(): array
    {
        $threshold = config('resource_governance.archive_growth_anomaly_bytes', 104857600);
        $oversized = $this->governor->oversizedArchiveSites($threshold);

        if ($oversized->isEmpty()) return ['count' => 0];

        $count   = $oversized->count();
        $message = "{$count} سایت با آرشیو بزرگ‌تر از " . SiteResourceUsage::formatBytes($threshold) . " شناسایی شد.";

        $this->nc->notify([
            'type'          => 'archive_report',
            'priority'      => 'low',
            'title'         => 'گزارش آرشیوهای بزرگ',
            'message'       => $message,
            'audience_type' => 'platform_admin',
            'channel'       => 'dashboard',
            'link'          => route('admin.resource-governance.index'),
        ]);

        return [
            'count'   => $count,
            'sites'   => $oversized->map(fn($u) => [
                'site_id' => $u->site_id,
                'title'   => $u->site->title ?? '-',
                'bytes'   => $u->archive_bytes,
            ])->toArray(),
        ];
    }

    /**
     * Platform-wide resource summary for dashboard widgets.
     */
    public function platformSummary(): array
    {
        $usages = SiteResourceUsage::all();

        $totalStorage   = (int) $usages->sum('storage_bytes');
        $totalDb        = (int) $usages->sum('db_bytes');
        $totalArchive   = (int) $usages->sum('archive_bytes');
        $totalBandwidth = (int) $usages->sum('bandwidth_bytes_monthly');
        $totalFiles     = (int) $usages->sum('file_count');

        $limitBytes     = config('resource_governance.storage_limit_bytes', 1073741824);
        $sitesOver80    = $usages->filter(fn($u) => $u->storage_bytes >= $limitBytes * 0.80)->count();
        $sitesOver95    = $usages->filter(fn($u) => $u->storage_bytes >= $limitBytes * 0.95)->count();

        return [
            'total_sites'        => $usages->count(),
            'total_storage'      => $totalStorage,
            'total_db'           => $totalDb,
            'total_archive'      => $totalArchive,
            'total_bandwidth'    => $totalBandwidth,
            'total_files'        => $totalFiles,
            'sites_over_80_pct'  => $sitesOver80,
            'sites_over_95_pct'  => $sitesOver95,
            'formatted_storage'  => SiteResourceUsage::formatBytes($totalStorage),
            'formatted_db'       => SiteResourceUsage::formatBytes($totalDb),
            'formatted_archive'  => SiteResourceUsage::formatBytes($totalArchive),
            'formatted_bandwidth'=> SiteResourceUsage::formatBytes($totalBandwidth),
        ];
    }

    /**
     * Purge daily snapshots older than retention days.
     */
    public function purgeOldSnapshots(): int
    {
        $days = config('resource_governance.snapshot_retention_days', 90);
        return SiteStorageSnapshot::where('snapshot_date', '<', now()->subDays($days)->toDateString())->delete();
    }

    /**
     * Purge stale bandwidth records (more than 12 months old).
     */
    public function purgeOldBandwidthRecords(): int
    {
        $cutoffYear  = (int) now()->subYear()->format('Y');
        $cutoffMonth = (int) now()->format('n');

        return SiteBandwidthUsage::where('year', '<', $cutoffYear)
            ->orWhere(function ($q) use ($cutoffYear, $cutoffMonth) {
                $q->where('year', $cutoffYear)->where('month', '<', $cutoffMonth);
            })
            ->delete();
    }
}
