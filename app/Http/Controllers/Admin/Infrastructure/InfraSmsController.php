<?php
namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InfraSmsController extends Controller
{
    public function index()
    {
        $primaryHealth   = Cache::get('sms_health_primary',   ['success' => 0, 'failure' => 0, 'last_checked_at' => null]);
        $secondaryHealth = Cache::get('sms_health_secondary', ['success' => 0, 'failure' => 0, 'last_checked_at' => null]);

        $today       = now()->startOfDay();
        $last7Days   = now()->subDays(7);
        $last30Days  = now()->subDays(30);

        $todaySent    = SmsLog::where('created_at', '>=', $today)->count();
        $todaySuccess = SmsLog::where('created_at', '>=', $today)->success()->count();
        $todayFailed  = SmsLog::where('created_at', '>=', $today)->failed()->count();

        $last30Sent    = SmsLog::where('created_at', '>=', $last30Days)->count();
        $last30Success = SmsLog::where('created_at', '>=', $last30Days)->success()->count();
        $last30Rate    = $last30Sent > 0 ? round($last30Success / $last30Sent * 100, 1) : 0;

        $primaryRate   = SmsLog::successRateFor('primary', 30);
        $secondaryRate = SmsLog::successRateFor('secondary', 30);

        // Daily chart: last 7 days
        $dailyStats = DB::table('sms_logs')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(success) as success_count')
            ->where('created_at', '>=', $last7Days)
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        // Recent failures
        $recentFailures = SmsLog::failed()
            ->latest()
            ->take(10)
            ->get(['id', 'provider', 'recipient', 'error_message', 'created_at']);

        // Average response times per provider
        $avgResponseTimes = DB::table('sms_logs')
            ->selectRaw('provider, ROUND(AVG(response_time_ms), 0) as avg_ms')
            ->where('created_at', '>=', $last7Days)
            ->whereNotNull('response_time_ms')
            ->groupBy('provider')
            ->pluck('avg_ms', 'provider');

        return view('admin.infrastructure.sms.index', compact(
            'primaryHealth', 'secondaryHealth',
            'todaySent', 'todaySuccess', 'todayFailed',
            'last30Sent', 'last30Success', 'last30Rate',
            'primaryRate', 'secondaryRate',
            'dailyStats', 'recentFailures', 'avgResponseTimes'
        ));
    }
}
