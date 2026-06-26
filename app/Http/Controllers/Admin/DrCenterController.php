<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Infrastructure\RestoreLog;
use App\Models\Site;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Infrastructure\DrIntegrityService;
use App\Services\Infrastructure\RestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DrCenterController extends Controller
{
    public function __construct(
        private ArvanBackupService  $arvan,
        private DrIntegrityService  $integrity,
        private RestoreService      $restore,
    ) {}

    // ─── Main Dashboard ────────────────────────────────────────────────────────

    public function index(): \Illuminate\View\View
    {
        // Platform DB backup (latest of any status)
        $lastDb = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'database')
            ->orderByDesc('started_at')
            ->first();

        // Platform Storage backup (latest of any status)
        $lastStorage = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'storage')
            ->orderByDesc('started_at')
            ->first();

        // Compute health statuses
        $dbHealth      = $this->computeDbHealth($lastDb);
        $storageHealth = $this->computeStorageHealth($lastStorage);

        // Arvan file counts (cached 5 min)
        $arvanDbFiles      = Cache::remember('dr_arvan_platform_db', 300, fn() =>
            $this->arvan->listFiles('saas-platform/database/'));
        $arvanStorageFiles = Cache::remember('dr_arvan_platform_storage', 300, fn() =>
            $this->arvan->listFiles('saas-platform/storage/'));

        // Sync gap detection (last 7d)
        $dbRunsLast7d  = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'database')
            ->where('status', 'success')
            ->where('started_at', '>=', now()->subDays(7))
            ->count();
        $arvanDbLast7d = collect($arvanDbFiles)
            ->filter(fn($f) => ($f['last_modified'] ?? 0) >= now()->subDays(7)->timestamp)
            ->count();
        $syncGap = max(0, $dbRunsLast7d - $arvanDbLast7d);

        // Recent restore events
        $recentRestores = RestoreLog::with('site', 'executor')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Restore stats (30d)
        $restoreStats = $this->computeRestoreStats();

        // Recent backup failures
        $backupFailures = InfraBackupRun::where('status', 'failed')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        // Dashboard alerts (banners)
        $alerts = $this->buildDashboardAlerts($dbHealth, $storageHealth, $syncGap);

        // Lock status
        $lockActive = $this->restore->isLocked();

        // Alert log exists?
        $alertLogExists = Schema::hasTable('dr_alert_log');

        return view('admin.disaster-recovery.center.index', compact(
            'lastDb', 'lastStorage',
            'dbHealth', 'storageHealth',
            'arvanDbFiles', 'arvanStorageFiles',
            'syncGap', 'dbRunsLast7d', 'arvanDbLast7d',
            'recentRestores', 'restoreStats',
            'backupFailures',
            'alerts', 'lockActive',
            'alertLogExists'
        ));
    }

    // ─── Per-Tenant Detail ────────────────────────────────────────────────────

    public function tenantHealth(Request $request, Site $site): \Illuminate\View\View
    {
        $backupRuns = InfraBackupRun::where('site_id', $site->id)
            ->orderByDesc('started_at')
            ->paginate(20, ['*'], 'backup_page');

        $restoreLogs = RestoreLog::with('executor')
            ->where('site_id', $site->id)
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'restore_page');

        $arvanDbFiles = Cache::remember("dr_arvan_tenant_{$site->id}_db", 300, fn() =>
            $this->arvan->listFiles("customer-sites/tenant-{$site->id}/database/"));

        $arvanStorageFiles = Cache::remember("dr_arvan_tenant_{$site->id}_storage", 300, fn() =>
            $this->arvan->listFiles("customer-sites/tenant-{$site->id}/storage/"));

        $healthScore = $this->computeTenantHealthScore($site->id, $arvanDbFiles);

        return view('admin.disaster-recovery.center.tenant-detail', compact(
            'site', 'backupRuns', 'restoreLogs',
            'arvanDbFiles', 'arvanStorageFiles',
            'healthScore'
        ));
    }

    // ─── AJAX: Timeline Chart Data ────────────────────────────────────────────

    public function backupTimeline(): JsonResponse
    {
        $dbRuns = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'database')
            ->where('status', 'success')
            ->orderByDesc('started_at')
            ->limit(30)
            ->get(['file_size_mb', 'started_at']);

        $storageRuns = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'storage')
            ->where('status', 'success')
            ->orderByDesc('started_at')
            ->limit(30)
            ->get(['file_size_mb', 'started_at']);

        // Reverse so oldest is on left
        $dbRuns      = $dbRuns->reverse()->values();
        $storageRuns = $storageRuns->reverse()->values();

        $labels     = $dbRuns->map(fn($r) => $r->started_at?->format('M d'))->all();
        $dbData     = $dbRuns->map(fn($r) => round((float) $r->file_size_mb, 2))->all();
        $storageData = $storageRuns->map(fn($r) => round((float) $r->file_size_mb, 2))->all();

        // Anomaly detection (size < mean - 2σ)
        $dbAnomalies = $this->detectAnomalies($dbData);

        return response()->json([
            'labels'   => $labels,
            'datasets' => [
                ['label' => 'بکاپ DB (MB)', 'data' => $dbData,      'anomalies' => $dbAnomalies],
                ['label' => 'بکاپ Storage (MB)', 'data' => $storageData, 'anomalies' => []],
            ],
        ]);
    }

    // ─── POST: On-Demand Integrity Check ─────────────────────────────────────

    public function integrityCheck(Request $request): JsonResponse
    {
        $type   = $request->input('type', 'platform');
        $siteId = $request->integer('site_id', 0) ?: null;

        if ($type === 'tenant' && !$siteId) {
            return response()->json(['error' => 'site_id الزامی است'], 422);
        }

        if ($type === 'tenant') {
            $result = $this->integrity->checkTenant($siteId);
        } else {
            $result = $this->integrity->checkPlatform();
        }

        // Clear Arvan cache so next page load gets fresh data
        Cache::forget('dr_arvan_platform_db');
        Cache::forget('dr_arvan_platform_storage');

        return response()->json($result);
    }

    // ─── GET: Alert History ───────────────────────────────────────────────────

    public function alertHistory(Request $request): \Illuminate\View\View
    {
        $alertLogExists = Schema::hasTable('dr_alert_log');
        $alerts         = collect();

        if ($alertLogExists) {
            $query = \DB::table('dr_alert_log')->orderByDesc('created_at');

            if ($severity = $request->query('severity')) {
                $query->where('severity', $severity);
            }
            if ($request->query('state') === 'open') {
                $query->where('resolved', false);
            } elseif ($request->query('state') === 'resolved') {
                $query->where('resolved', true);
            }

            $alerts = $query->paginate(20);
        }

        return view('admin.disaster-recovery.center.alerts', compact('alerts', 'alertLogExists'));
    }

    // ─── AJAX: Alert Feed (auto-refresh) ─────────────────────────────────────

    public function alertFeed(): JsonResponse
    {
        if (!Schema::hasTable('dr_alert_log')) {
            return response()->json(['items' => [], 'table_exists' => false]);
        }

        $items = \DB::table('dr_alert_log')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json(['items' => $items, 'table_exists' => true]);
    }

    // ─── Health Computations ──────────────────────────────────────────────────

    private function computeDbHealth(?InfraBackupRun $run): string
    {
        if (!$run) return 'MISSING';
        if ($run->isFailed()) return 'FAILED';
        if (!$run->completed_at || $run->completed_at->lt(now()->subHours(24))) return 'STALE';
        if ($run->file_path) {
            $filename = basename($run->file_path);
            if (!$this->arvan->exists("saas-platform/database/{$filename}")) return 'ARVAN_MISSING';
        }
        return 'OK';
    }

    private function computeStorageHealth(?InfraBackupRun $run): string
    {
        if (!$run) return 'MISSING';
        if ($run->isFailed()) return 'FAILED';
        if (!$run->completed_at || $run->completed_at->lt(now()->subDays(7))) return 'STALE';
        return 'OK';
    }

    private function computeRestoreStats(): array
    {
        $q30 = RestoreLog::where('created_at', '>=', now()->subDays(30));

        $total   = (clone $q30)->count();
        $success = (clone $q30)->where('status', 'success')->count();
        $failed  = (clone $q30)->where('status', 'failed')->count();
        $dryRuns = (clone $q30)->where('dry_run', true)->count();

        $avgDuration = (clone $q30)
            ->whereNotNull('completed_at')
            ->whereNotNull('started_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg')
            ->value('avg');

        return [
            'total'        => $total,
            'success'      => $success,
            'failed'       => $failed,
            'dry_runs'     => $dryRuns,
            'success_rate' => $total > 0 ? round(($success / $total) * 100) : 100,
            'avg_duration' => $avgDuration ? (int) $avgDuration : 0,
        ];
    }

    private function computeTenantHealthScore(int $siteId, array $arvanDbFiles): int
    {
        $score = 100;

        $lastDb7d = InfraBackupRun::where('site_id', $siteId)
            ->where('backup_type', 'database')
            ->where('status', 'success')
            ->where('started_at', '>=', now()->subDays(7))
            ->exists();

        if (!$lastDb7d) $score -= 30;

        $hasFailedRestore = RestoreLog::where('site_id', $siteId)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();

        if ($hasFailedRestore) $score -= 20;
        if (empty($arvanDbFiles)) $score -= 20;

        $lastBackup = InfraBackupRun::where('site_id', $siteId)
            ->orderByDesc('started_at')
            ->first();

        if ($lastBackup && $lastBackup->isFailed()) $score -= 10;

        $hasRestore = RestoreLog::where('site_id', $siteId)->exists();
        if (!$hasRestore) $score -= 10;

        // Bonus: Arvan count matches DB run count
        $dbCount    = InfraBackupRun::where('site_id', $siteId)->where('status', 'success')->count();
        $arvanCount = count($arvanDbFiles);
        if ($dbCount > 0 && $arvanCount >= $dbCount) $score += 10;

        return max(0, min(100, $score));
    }

    private function buildDashboardAlerts(string $dbHealth, string $storageHealth, int $syncGap): array
    {
        $alerts = [];

        if (in_array($dbHealth, ['MISSING', 'FAILED', 'STALE'], true)) {
            $alerts[] = [
                'type'     => 'ALERT-001',
                'severity' => 'critical',
                'message'  => match($dbHealth) {
                    'MISSING' => 'هیچ بکاپ دیتابیس پلتفرمی ثبت نشده است.',
                    'FAILED'  => 'آخرین بکاپ دیتابیس پلتفرم ناموفق بود.',
                    'STALE'   => 'بکاپ دیتابیس پلتفرم بیش از ۲۴ ساعت است بروز نشده.',
                    default   => 'خطای بکاپ',
                },
            ];
        } elseif ($dbHealth === 'ARVAN_MISSING') {
            $alerts[] = [
                'type'     => 'ALERT-003',
                'severity' => 'high',
                'message'  => 'آخرین فایل بکاپ در Arvan یافت نشد — احتمالاً sync انجام نشده.',
            ];
        }

        if (in_array($storageHealth, ['MISSING', 'STALE'], true)) {
            $alerts[] = [
                'type'     => 'ALERT-004',
                'severity' => 'high',
                'message'  => 'بکاپ Storage پلتفرم بیش از ۷ روز است بروز نشده.',
            ];
        }

        if ($syncGap > 0) {
            $alerts[] = [
                'type'     => 'ALERT-003',
                'severity' => 'high',
                'message'  => "{$syncGap} اجرای بکاپ احتمالاً به Arvan sync نشده‌اند.",
            ];
        }

        return $alerts;
    }

    private function detectAnomalies(array $data): array
    {
        if (count($data) < 3) return [];

        $mean   = array_sum($data) / count($data);
        $stddev = sqrt(array_sum(array_map(fn($v) => ($v - $mean) ** 2, $data)) / count($data));
        $threshold = $mean - 2 * $stddev;

        return array_map(fn($v) => $v < $threshold, $data);
    }
}
