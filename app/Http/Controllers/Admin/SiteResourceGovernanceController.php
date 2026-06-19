<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteResourceUsage;
use App\Models\SiteResourceLimit;
use App\Models\SiteStorageSnapshot;
use App\Services\ResourceGovernance\SiteResourceCalculatorService;
use App\Services\ResourceGovernance\SiteResourceGovernorService;
use App\Services\ResourceGovernance\SiteResourceReportService;
use Illuminate\Http\Request;

class SiteResourceGovernanceController extends Controller
{
    public function __construct(
        private SiteResourceGovernorService $governor,
        private SiteResourceReportService $reportService
    ) {}

    /**
     * Platform-wide resource overview — all sites sortable table.
     */
    public function index(Request $request)
    {
        $sortBy  = $request->get('sort', 'storage');
        $filter  = $request->get('filter', 'all'); // all | over80 | over95 | inactive

        $summary = $this->reportService->platformSummary();

        $query = SiteResourceUsage::with('site')->orderByDesc('storage_bytes');

        $limitBytes = config('resource_governance.storage_limit_bytes', 1073741824);

        switch ($sortBy) {
            case 'db':       $query->orderByDesc('db_bytes'); break;
            case 'archive':  $query->orderByDesc('archive_bytes'); break;
            case 'bandwidth':$query->orderByDesc('bandwidth_bytes_monthly'); break;
            case 'files':    $query->orderByDesc('file_count'); break;
            default:         $query->orderByDesc('storage_bytes'); break;
        }

        switch ($filter) {
            case 'over80':
                $threshold = (int) ($limitBytes * 0.80);
                $query->where('storage_bytes', '>=', $threshold);
                break;
            case 'over95':
                $threshold = (int) ($limitBytes * 0.95);
                $query->where('storage_bytes', '>=', $threshold);
                break;
            case 'inactive':
                $query->where(function ($q) {
                    $q->whereNull('calculated_at')
                      ->orWhere('calculated_at', '<', now()->subHours(48));
                });
                break;
        }

        $usages = $query->paginate(30)->withQueryString();

        // Sites with no resource record yet
        $sitesWithNoRecord = Site::whereNotIn('lifecycle_status', ['deleted'])
            ->whereNotExists(function ($q) {
                $q->from('site_resource_usage')->whereColumn('site_resource_usage.site_id', 'sites.id');
            })
            ->count();

        return view('admin.resource-governance.index', compact(
            'usages', 'summary', 'sortBy', 'filter', 'limitBytes', 'sitesWithNoRecord'
        ));
    }

    /**
     * Per-site resource detail with history and limits editor.
     */
    public function site(Site $site)
    {
        $usage    = SiteResourceUsage::firstOrNew(['site_id' => $site->id]);
        $limit    = SiteResourceLimit::defaultForSite($site);
        $override = SiteResourceLimit::where('site_id', $site->id)->first();

        $snapshots = SiteStorageSnapshot::where('site_id', $site->id)
            ->orderByDesc('snapshot_date')
            ->limit(30)
            ->get();

        $overageFees = $this->governor->calculateOverageFees($site);

        $storagePct   = $usage->exists ? $usage->storagePercent((int) $limit->storage_limit_bytes) : 0;
        $bandwidthPct = $usage->exists ? $usage->bandwidthPercent((int) $limit->bandwidth_limit_bytes) : 0;

        // Chart data (last 30 snapshots)
        $chartDates   = $snapshots->pluck('snapshot_date')->map(fn($d) => $d->format('m/d'))->reverse()->values();
        $chartStorage = $snapshots->pluck('storage_bytes')->reverse()->values();
        $chartDb      = $snapshots->pluck('db_bytes')->reverse()->values();

        return view('admin.resource-governance.site', compact(
            'site', 'usage', 'limit', 'override', 'snapshots',
            'overageFees', 'storagePct', 'bandwidthPct',
            'chartDates', 'chartStorage', 'chartDb'
        ));
    }

    /**
     * Force-recalculate resource metrics for a single site.
     */
    public function recalculate(Site $site)
    {
        $calculator = app(SiteResourceCalculatorService::class);
        $metrics    = $calculator->calculate($site);
        $calculator->pushSummaryToCustomerSite($site, $metrics);

        return back()->with('success', "منابع سایت {$site->title} محاسبه و به‌روزرسانی شد.");
    }

    /**
     * Save per-site resource limit override.
     */
    public function updateLimits(Request $request, Site $site)
    {
        $data = $request->validate([
            'storage_limit_gb'             => 'required|numeric|min:0.1|max:10000',
            'bandwidth_limit_gb'           => 'required|numeric|min:0.1|max:100000',
            'archive_retention_days'       => 'required|integer|min:1|max:3650',
            'backup_retention_days'        => 'required|integer|min:1|max:365',
            'cpu_weight'                   => 'required|integer|min:1|max:100',
            'ram_weight'                   => 'required|integer|min:1|max:100',
            'storage_overage_fee_per_gb'   => 'required|integer|min:0',
            'bandwidth_overage_fee_per_gb' => 'required|integer|min:0',
            'archive_retention_fee'        => 'required|integer|min:0',
            'restore_fee'                  => 'required|integer|min:0',
        ]);

        SiteResourceLimit::updateOrCreate(
            ['site_id' => $site->id],
            [
                'storage_limit_bytes'          => (int) round($data['storage_limit_gb'] * 1073741824),
                'bandwidth_limit_bytes'        => (int) round($data['bandwidth_limit_gb'] * 1073741824),
                'archive_retention_days'       => $data['archive_retention_days'],
                'backup_retention_days'        => $data['backup_retention_days'],
                'cpu_weight'                   => $data['cpu_weight'],
                'ram_weight'                   => $data['ram_weight'],
                'storage_overage_fee_per_gb'   => $data['storage_overage_fee_per_gb'],
                'bandwidth_overage_fee_per_gb' => $data['bandwidth_overage_fee_per_gb'],
                'archive_retention_fee'        => $data['archive_retention_fee'],
                'restore_fee'                  => $data['restore_fee'],
            ]
        );

        return back()->with('success', "محدودیت‌های منابع سایت {$site->title} ذخیره شد.");
    }

    /**
     * Platform-wide default settings.
     */
    public function settings()
    {
        $keys = [
            'RESOURCE_STORAGE_LIMIT_BYTES',
            'RESOURCE_BANDWIDTH_LIMIT_BYTES',
            'RESOURCE_ARCHIVE_RETENTION_DAYS',
            'RESOURCE_BACKUP_RETENTION_DAYS',
            'RESOURCE_STORAGE_OVERAGE_FEE',
            'RESOURCE_BANDWIDTH_OVERAGE_FEE',
            'RESOURCE_ARCHIVE_RETENTION_FEE',
            'RESOURCE_RESTORE_FEE',
            'RESOURCE_ALERT_WARNING_PCT',
            'RESOURCE_ALERT_CRITICAL_PCT',
            'RESOURCE_SNAPSHOT_RETENTION_DAYS',
        ];

        $current = [];
        foreach ($keys as $key) {
            $current[$key] = env($key, '');
        }

        $defaultStorageGb    = round(config('resource_governance.storage_limit_bytes') / 1073741824, 0);
        $defaultBandwidthGb  = round(config('resource_governance.bandwidth_limit_bytes') / 1073741824, 0);

        return view('admin.resource-governance.settings', compact('current', 'defaultStorageGb', 'defaultBandwidthGb'));
    }

    /**
     * Update platform-wide resource governance .env settings.
     */
    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'storage_limit_gb'             => 'required|numeric|min:0.1|max:100000',
            'bandwidth_limit_gb'           => 'required|numeric|min:0.1|max:1000000',
            'archive_retention_days'       => 'required|integer|min:1|max:3650',
            'backup_retention_days'        => 'required|integer|min:1|max:365',
            'storage_overage_fee'          => 'required|integer|min:0',
            'bandwidth_overage_fee'        => 'required|integer|min:0',
            'archive_retention_fee'        => 'required|integer|min:0',
            'restore_fee'                  => 'required|integer|min:0',
            'alert_warning_pct'            => 'required|integer|min:50|max:99',
            'alert_critical_pct'           => 'required|integer|min:51|max:100',
            'snapshot_retention_days'      => 'required|integer|min:7|max:365',
        ]);

        $envUpdates = [
            'RESOURCE_STORAGE_LIMIT_BYTES'   => (int) round($data['storage_limit_gb'] * 1073741824),
            'RESOURCE_BANDWIDTH_LIMIT_BYTES' => (int) round($data['bandwidth_limit_gb'] * 1073741824),
            'RESOURCE_ARCHIVE_RETENTION_DAYS'  => $data['archive_retention_days'],
            'RESOURCE_BACKUP_RETENTION_DAYS'   => $data['backup_retention_days'],
            'RESOURCE_STORAGE_OVERAGE_FEE'     => $data['storage_overage_fee'],
            'RESOURCE_BANDWIDTH_OVERAGE_FEE'   => $data['bandwidth_overage_fee'],
            'RESOURCE_ARCHIVE_RETENTION_FEE'   => $data['archive_retention_fee'],
            'RESOURCE_RESTORE_FEE'             => $data['restore_fee'],
            'RESOURCE_ALERT_WARNING_PCT'        => $data['alert_warning_pct'],
            'RESOURCE_ALERT_CRITICAL_PCT'       => $data['alert_critical_pct'],
            'RESOURCE_SNAPSHOT_RETENTION_DAYS'  => $data['snapshot_retention_days'],
        ];

        foreach ($envUpdates as $key => $value) {
            $this->setEnvValue($key, (string) $value);
        }

        return back()->with('success', 'تنظیمات حاکمیت منابع ذخیره شد.');
    }

    private function setEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) return;

        $content = file_get_contents($envPath);
        if (preg_match("/^{$key}=.*/m", $content)) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            $content .= "\n{$key}={$value}";
        }
        file_put_contents($envPath, $content);
    }
}
