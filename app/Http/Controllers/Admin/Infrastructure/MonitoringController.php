<?php
namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Infrastructure\InfraSiteHealthLog;
use App\Models\Infrastructure\InfraIncident;
use App\Models\Infrastructure\InfraPlatformHealthLog;
use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Infrastructure\InfraSchedulerRun;
use App\Services\Infrastructure\Monitoring\HealthCheckService;
use App\Services\Infrastructure\Monitoring\UptimeCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function __construct(
        private HealthCheckService $health,
        private UptimeCalculator   $uptime
    ) {}

    public function index()
    {
        $platformChecks = $this->health->checkAll();
        $platformStatus = $this->health->overallStatus($platformChecks);

        $latestLogs = InfraSiteHealthLog::latestPerSite()
            ->with('site')
            ->orderByDesc('checked_at')
            ->get();

        $totalSites    = Site::where('is_active', true)->count();
        $healthySites  = $latestLogs->where('status', 'healthy')->count();
        $warningSites  = $latestLogs->where('status', 'warning')->count();
        $criticalSites = $latestLogs->where('status', 'critical')->count();

        $openIncidents = InfraIncident::active()
            ->orderByDesc('created_at')
            ->with('site')
            ->limit(20)
            ->get();

        $criticalCount = InfraIncident::active()->critical()->count();

        $responseTrend = InfraSiteHealthLog::where('checked_at', '>=', now()->subHours(24))
            ->selectRaw('DATE_FORMAT(checked_at, "%Y-%m-%d %H:00") as hour, AVG(response_time_ms) as avg_ms, COUNT(*) as checks')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $incidentTrend = InfraIncident::where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // New: platform health log (latest snapshot)
        $platformLog = InfraPlatformHealthLog::latestCheck();

        // Scheduler status
        $schedulerRuns = InfraSchedulerRun::recentSummary();

        // Backup status
        $latestBackup = InfraBackupRun::latestPlatform();

        return view('admin.infrastructure.monitoring.index', compact(
            'platformChecks', 'platformStatus',
            'latestLogs', 'totalSites',
            'healthySites', 'warningSites', 'criticalSites',
            'openIncidents', 'criticalCount',
            'responseTrend', 'incidentTrend',
            'platformLog', 'schedulerRuns', 'latestBackup'
        ));
    }

    public function show(int $siteId)
    {
        $site = Site::findOrFail($siteId);

        $latestLog = InfraSiteHealthLog::where('site_id', $siteId)
            ->orderByDesc('checked_at')
            ->first();

        $uptime24h = $this->uptime->siteUptime($siteId, 24);
        $uptime7d  = $this->uptime->siteUptime($siteId, 24 * 7);
        $uptime30d = $this->uptime->siteUptime($siteId, 24 * 30);
        $uptimeHistory = $this->uptime->siteUptimeHistory($siteId, 14);

        $responseTrend = InfraSiteHealthLog::where('site_id', $siteId)
            ->where('checked_at', '>=', now()->subHours(24))
            ->orderBy('checked_at')
            ->get(['checked_at', 'response_time_ms', 'status']);

        $incidents = InfraIncident::where('site_id', $siteId)
            ->orderByDesc('created_at')
            ->with('assignedTo')
            ->limit(20)
            ->get();

        $backups = InfraBackupRun::where('site_id', $siteId)
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        $healthHistory = InfraSiteHealthLog::where('site_id', $siteId)
            ->orderByDesc('checked_at')
            ->limit(48)
            ->get();

        return view('admin.infrastructure.monitoring.site', compact(
            'site', 'latestLog',
            'uptime24h', 'uptime7d', 'uptime30d', 'uptimeHistory',
            'responseTrend', 'incidents', 'backups', 'healthHistory'
        ));
    }

    public function summary(): JsonResponse
    {
        $platformChecks = $this->health->checkAll();

        $latestLogs  = InfraSiteHealthLog::latestPerSite()->get();
        $totalSites  = Site::where('is_active', true)->count();
        $healthySites  = $latestLogs->where('status', 'healthy')->count();
        $warningSites  = $latestLogs->where('status', 'warning')->count();
        $criticalSites = $latestLogs->where('status', 'critical')->count();

        return response()->json([
            'platform'       => $platformChecks,
            'sites_total'    => $totalSites,
            'sites_healthy'  => $healthySites,
            'sites_warning'  => $warningSites,
            'sites_critical' => $criticalSites,
            'open_incidents' => InfraIncident::active()->count(),
            'critical_incidents' => InfraIncident::active()->critical()->count(),
            'checked_at'     => now()->toIso8601String(),
        ]);
    }

    public function sites(Request $request): JsonResponse
    {
        $logs = InfraSiteHealthLog::latestPerSite()
            ->with('site')
            ->orderByDesc('checked_at')
            ->get()
            ->map(fn ($log) => [
                'site_id'            => $log->site_id,
                'site_name'          => $log->site->title ?? '—',
                'domain'             => $log->domain,
                'status'             => $log->status,
                'http_status'        => $log->http_status,
                'response_time_ms'   => $log->response_time_ms,
                'ssl_status'         => $log->ssl_status,
                'ssl_days'           => $log->ssl_days_remaining,
                'domain_days'        => $log->domain_days_remaining,
                'db_status'          => $log->db_status,
                'redis_status'       => $log->redis_status,
                'queue_status'       => $log->queue_status,
                'failed_jobs'        => $log->failed_jobs,
                'disk_used_percent'  => $log->disk_used_percent,
                'memory_used_percent'=> $log->memory_used_percent,
                'checked_at'         => $log->checked_at?->toIso8601String(),
            ]);

        return response()->json(['sites' => $logs]);
    }

    public function incidents(Request $request): JsonResponse
    {
        $query = InfraIncident::with('site')->orderByDesc('created_at');

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $incidents = $query->limit(50)->get()->map(fn ($i) => [
            'id'          => $i->id,
            'site_id'     => $i->site_id,
            'site_name'   => $i->site->title ?? 'پلتفرم',
            'severity'    => $i->severity,
            'status'      => $i->status,
            'type'        => $i->type,
            'title'       => $i->title,
            'message'     => $i->message,
            'resolved_at' => $i->resolved_at?->toIso8601String(),
            'created_at'  => $i->created_at->toIso8601String(),
        ]);

        return response()->json(['incidents' => $incidents]);
    }

    public function resolveIncident(Request $request, int $id): JsonResponse
    {
        $incident = InfraIncident::findOrFail($id);
        $incident->markResolved();

        return response()->json(['ok' => true, 'resolved_at' => $incident->resolved_at->toIso8601String()]);
    }

    public function platformHistory(): JsonResponse
    {
        $logs = InfraPlatformHealthLog::latest24h()
            ->map(fn ($l) => [
                'checked_at'         => $l->checked_at->toIso8601String(),
                'overall_status'     => $l->overall_status,
                'disk_used_percent'  => $l->disk_used_percent,
                'memory_used_percent'=> $l->memory_used_percent,
                'cpu_load_1m'        => $l->cpu_load_1m,
                'failed_jobs'        => $l->failed_jobs,
            ]);

        return response()->json(['logs' => $logs]);
    }
}
