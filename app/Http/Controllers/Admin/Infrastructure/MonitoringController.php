<?php
namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Infrastructure\InfraSiteHealthLog;
use App\Models\Infrastructure\InfraIncident;
use App\Services\Infrastructure\Monitoring\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function __construct(private HealthCheckService $health) {}

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

        $openIncidents = InfraIncident::open()
            ->orderByDesc('created_at')
            ->with('site')
            ->limit(20)
            ->get();

        $criticalCount = InfraIncident::open()->critical()->count();

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

        return view('admin.infrastructure.monitoring.index', compact(
            'platformChecks', 'platformStatus',
            'latestLogs', 'totalSites',
            'healthySites', 'warningSites', 'criticalSites',
            'openIncidents', 'criticalCount',
            'responseTrend', 'incidentTrend'
        ));
    }

    public function summary(): JsonResponse
    {
        $platformChecks = $this->health->checkAll();

        $latestLogs = InfraSiteHealthLog::latestPerSite()->get();
        $totalSites    = Site::where('is_active', true)->count();
        $healthySites  = $latestLogs->where('status', 'healthy')->count();
        $warningSites  = $latestLogs->where('status', 'warning')->count();
        $criticalSites = $latestLogs->where('status', 'critical')->count();

        return response()->json([
            'platform'       => $platformChecks,
            'sites_total'    => $totalSites,
            'sites_healthy'  => $healthySites,
            'sites_warning'  => $warningSites,
            'sites_critical' => $criticalSites,
            'open_incidents' => InfraIncident::open()->count(),
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
                'site_id'          => $log->site_id,
                'site_name'        => $log->site->title ?? '—',
                'domain'           => $log->domain,
                'status'           => $log->status,
                'http_status'      => $log->http_status,
                'response_time_ms' => $log->response_time_ms,
                'ssl_status'       => $log->ssl_status,
                'ssl_days'         => $log->ssl_days_remaining,
                'db_status'        => $log->db_status,
                'redis_status'     => $log->redis_status,
                'queue_status'     => $log->queue_status,
                'checked_at'       => $log->checked_at?->toIso8601String(),
            ]);

        return response()->json(['sites' => $logs]);
    }

    public function incidents(Request $request): JsonResponse
    {
        $query = InfraIncident::with('site')->orderByDesc('created_at');

        if ($request->boolean('open_only', true)) {
            $query->whereNull('resolved_at');
        }

        $incidents = $query->limit(50)->get()->map(fn ($i) => [
            'id'          => $i->id,
            'site_id'     => $i->site_id,
            'site_name'   => $i->site->title ?? 'پلتفرم',
            'severity'    => $i->severity,
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
}
