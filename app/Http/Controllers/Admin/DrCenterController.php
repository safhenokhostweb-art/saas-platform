<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\DrSimulation;
use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Infrastructure\RestoreLog;
use App\Models\Site;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Infrastructure\DrIntegrityService;
use App\Services\Infrastructure\DrReadinessScoreService;
use App\Services\Infrastructure\RestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DrCenterController extends Controller
{
    public function __construct(
        private ArvanBackupService       $arvan,
        private DrIntegrityService       $integrity,
        private RestoreService           $restore,
        private DrReadinessScoreService  $readiness,
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
        $backupFailures = InfraBackupRun::with('site')
            ->where('status', 'failed')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        // Dashboard alerts (banners)
        $alerts = $this->buildDashboardAlerts($dbHealth, $storageHealth, $syncGap);

        // Lock status
        $lockActive = $this->restore->isLocked();

        // Alert log exists?
        $alertLogExists = Schema::hasTable('dr_alert_log');

        // Readiness score (cached 5 min)
        $readinessScore = Cache::remember('dr_readiness_score', 300,
            fn() => $this->readiness->calculate());

        // Latest simulation
        $latestSimulation = Schema::hasTable('dr_simulations')
            ? DrSimulation::orderByDesc('created_at')->first()
            : null;

        // Arvan detail (cached 5 min, lazy — shown in partial via AJAX)
        $arvanStatus = Cache::remember('dr_arvan_detail_status', 300,
            fn() => $this->buildArvanDetailStatus($arvanDbFiles, $arvanStorageFiles));

        return view('admin.disaster-recovery.center.index', compact(
            'lastDb', 'lastStorage',
            'dbHealth', 'storageHealth',
            'arvanDbFiles', 'arvanStorageFiles',
            'syncGap', 'dbRunsLast7d', 'arvanDbLast7d',
            'recentRestores', 'restoreStats',
            'backupFailures',
            'alerts', 'lockActive',
            'alertLogExists',
            'readinessScore',
            'latestSimulation',
            'arvanStatus',
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

    // ─── Simulation History ───────────────────────────────────────────────────

    public function simulationIndex(): \Illuminate\View\View
    {
        $simulations = Schema::hasTable('dr_simulations')
            ? DrSimulation::orderByDesc('created_at')->paginate(20)
            : collect();

        return view('admin.disaster-recovery.center.simulation-history', compact('simulations'));
    }

    // ─── POST: Run Non-Destructive Simulation ─────────────────────────────────

    public function runSimulation(Request $request): JsonResponse
    {
        $startedAt = now();

        // Collect component results (same logic as readiness score, but detailed)
        $readinessData = $this->readiness->calculate();
        $components    = $readinessData['components'];

        // Determine PASS / FAIL
        // FAIL if any weight-heavy component is CRITICAL or MISSING
        $criticalKeys = ['platform_db_backup', 'arvan_sync', 'redis'];
        $result       = 'PASS';
        foreach ($criticalKeys as $key) {
            if (isset($components[$key]) && in_array($components[$key]['status'], ['MISSING', 'CRITICAL'], true)) {
                $result = 'FAIL';
                break;
            }
        }

        // Estimate RPO = age of last successful platform DB backup
        $lastDb = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'database')
            ->where('status', 'success')
            ->orderByDesc('completed_at')
            ->first();

        $estimatedRpo = $lastDb?->completed_at
            ? $this->humanDiff($lastDb->completed_at->diffInMinutes(now()))
            : 'نامشخص';

        // Estimate restore time: 1 MB/sec DB restore rate
        $sizeMb              = (float) ($lastDb?->file_size_mb ?? 0);
        $estimatedRestoreMin = $sizeMb > 0
            ? ceil($sizeMb / 60) + 5   // 1 MB/sec = 60 MB/min, +5min overhead
            : null;

        $estimatedRestoreTime = $estimatedRestoreMin
            ? $this->humanDiff($estimatedRestoreMin)
            : 'نامشخص (بکاپ موجود نیست)';

        $duration = (int) $startedAt->diffInSeconds(now());

        $simulation = DrSimulation::create([
            'operator_id'            => auth()->id(),
            'operator_name'          => auth()->user()?->name ?? 'سیستم',
            'result'                 => $result,
            'duration_seconds'       => $duration,
            'estimated_restore_time' => $estimatedRestoreTime,
            'estimated_rpo'          => $estimatedRpo,
            'components'             => array_map(
                fn($c) => ['label' => $c['label'], 'score' => $c['score'], 'max' => $c['max'], 'status' => $c['status'], 'detail' => $c['detail']],
                $components
            ),
            'notes'                  => 'شبیه‌سازی غیرمخرب — هیچ داده‌ای تغییر نکرده',
        ]);

        // Invalidate readiness cache so next load reflects the simulation
        Cache::forget('dr_readiness_score');

        return response()->json([
            'id'                     => $simulation->id,
            'result'                 => $result,
            'score'                  => $readinessData['score'],
            'estimated_restore_time' => $estimatedRestoreTime,
            'estimated_rpo'          => $estimatedRpo,
            'duration_seconds'       => $duration,
            'components'             => $components,
            'history_url'            => route('admin.disaster-recovery.center.simulations'),
        ]);
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

        $dbRuns      = $dbRuns->reverse()->values();
        $storageRuns = $storageRuns->reverse()->values();

        $labels      = $dbRuns->map(fn($r) => $r->started_at?->format('M d'))->all();
        $dbData      = $dbRuns->map(fn($r) => round((float) $r->file_size_mb, 2))->all();
        $storageData = $storageRuns->map(fn($r) => round((float) $r->file_size_mb, 2))->all();
        $dbAnomalies = $this->detectAnomalies($dbData);

        return response()->json([
            'labels'   => $labels,
            'datasets' => [
                ['label' => 'بکاپ DB (MB)',      'data' => $dbData,      'anomalies' => $dbAnomalies],
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

        $result = $type === 'tenant'
            ? $this->integrity->checkTenant($siteId)
            : $this->integrity->checkPlatform();

        // Track last integrity check time for Arvan status display
        Cache::put('dr_integrity_last_checked_at', now()->toIso8601String(), 3600);

        Cache::forget('dr_arvan_platform_db');
        Cache::forget('dr_arvan_platform_storage');
        Cache::forget('dr_arvan_detail_status');

        return response()->json($result);
    }

    // ─── GET: Alert History ───────────────────────────────────────────────────

    public function alertHistory(Request $request): \Illuminate\View\View
    {
        $alertLogExists = Schema::hasTable('dr_alert_log');
        $alerts         = collect();

        if ($alertLogExists) {
            $query = DB::table('dr_alert_log')->orderByDesc('created_at');

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

    // ─── AJAX: Tenant Health Table ────────────────────────────────────────────

    public function tenantHealthAjax(): JsonResponse
    {
        $sites = Site::where(function ($q) {
            $q->where('lifecycle_status', Site::LIFECYCLE_ACTIVE)
              ->orWhere(fn ($q2) => $q2->whereNull('lifecycle_status')->where('is_active', true));
        })->get();

        $tenants = $sites->map(function (Site $site) {
            $lastDbBackup = InfraBackupRun::where('site_id', $site->id)
                ->where('backup_type', 'database')
                ->where('status', 'success')
                ->orderByDesc('started_at')
                ->first();

            $arvanFiles = Cache::remember("dr_arvan_tenant_{$site->id}", 300, fn() =>
                $this->arvan->listFiles("customer-sites/tenant-{$site->id}/database/"));

            $lastRestore = RestoreLog::where('site_id', $site->id)
                ->where('status', 'success')
                ->orderByDesc('created_at')
                ->first();

            $dbHealth = $lastDbBackup
                ? ($lastDbBackup->completed_at?->lt(now()->subDays(7)) ? 'STALE' : 'OK')
                : 'MISSING';

            $healthScore = $this->computeTenantHealthScore($site->id, $arvanFiles);

            return [
                'site'               => ['id' => $site->id, 'name' => $site->name, 'domain' => $site->domain],
                'last_db_backup'     => (bool) $lastDbBackup,
                'last_db_backup_ago' => $lastDbBackup?->completed_at?->diffForHumans() ?? null,
                'db_health'          => $dbHealth,
                'arvan_file_count'   => count($arvanFiles),
                'last_restore_ago'   => $lastRestore?->created_at?->diffForHumans() ?? null,
                'health_score'       => $healthScore,
                'detail_url'         => route('admin.disaster-recovery.center.tenant', $site->id),
            ];
        })->values()->all();

        return response()->json(['tenants' => $tenants]);
    }

    // ─── AJAX: Alert Feed (auto-refresh) ─────────────────────────────────────

    public function alertFeed(): JsonResponse
    {
        if (!Schema::hasTable('dr_alert_log')) {
            return response()->json(['items' => [], 'table_exists' => false]);
        }

        $items = DB::table('dr_alert_log')
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

        $lastBackup = InfraBackupRun::where('site_id', $siteId)->orderByDesc('started_at')->first();
        if ($lastBackup && $lastBackup->isFailed()) $score -= 10;

        $hasRestore = RestoreLog::where('site_id', $siteId)->exists();
        if (!$hasRestore) $score -= 10;

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

        $mean      = array_sum($data) / count($data);
        $stddev    = sqrt(array_sum(array_map(fn($v) => ($v - $mean) ** 2, $data)) / count($data));
        $threshold = $mean - 2 * $stddev;

        return array_map(fn($v) => $v < $threshold, $data);
    }

    private function buildArvanDetailStatus(array $dbFiles, array $storageFiles): array
    {
        $allFiles = array_merge($dbFiles, $storageFiles);

        $totalFiles   = count($allFiles);
        $totalSizeB   = array_sum(array_column($allFiles, 'size'));
        $totalSizeMb  = round($totalSizeB / 1048576, 1);

        $latestUpload = null;
        foreach ($allFiles as $f) {
            if ($f['last_modified'] && (!$latestUpload || $f['last_modified'] > $latestUpload)) {
                $latestUpload = $f['last_modified'];
            }
        }

        $lastCheckedAt = Cache::get('dr_integrity_last_checked_at');

        return [
            'total_files'       => $totalFiles,
            'total_size_mb'     => $totalSizeMb,
            'latest_upload_ts'  => $latestUpload,
            'latest_upload_ago' => $latestUpload
                ? \Carbon\Carbon::createFromTimestamp($latestUpload)->diffForHumans()
                : null,
            'last_verified_at'  => $lastCheckedAt,
            'db_file_count'     => count($dbFiles),
            'storage_file_count'=> count($storageFiles),
            'capacity_available'=> false, // ArvanCloud S3 does not expose bucket quota via standard API
        ];
    }

    private function humanDiff(int $minutes): string
    {
        if ($minutes < 60) return "{$minutes} دقیقه";
        $hours   = (int) ($minutes / 60);
        $mins    = $minutes % 60;
        $label   = "{$hours} ساعت";
        if ($mins > 0) $label .= " {$mins} دقیقه";
        return $label;
    }
}
