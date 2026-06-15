<?php
namespace App\Services\Infrastructure\Monitoring;

use App\Models\Infrastructure\InfraSiteHealthLog;
use App\Models\Site;
use Illuminate\Support\Collection;

class UptimeCalculator
{
    public function siteUptime(int $siteId, int $hours = 24): float
    {
        return InfraSiteHealthLog::uptimePercent($siteId, $hours);
    }

    public function siteUptimeHistory(int $siteId, int $days = 7): array
    {
        return InfraSiteHealthLog::uptimeHistory($siteId, $days);
    }

    public function allSitesUptime(int $hours = 24): Collection
    {
        $sites = Site::where('is_active', true)->get(['id', 'title', 'domain']);

        return $sites->map(function (Site $site) use ($hours) {
            return [
                'site_id' => $site->id,
                'title'   => $site->title,
                'domain'  => $site->domain,
                'uptime'  => InfraSiteHealthLog::uptimePercent($site->id, $hours),
            ];
        });
    }

    public function platformUptime(int $days = 30): array
    {
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date  = now()->subDays($i)->startOfDay();
            $end   = $date->copy()->endOfDay();
            $total = InfraSiteHealthLog::whereBetween('checked_at', [$date, $end])->count();
            $ok    = $total > 0
                ? InfraSiteHealthLog::whereBetween('checked_at', [$date, $end])->where('status', 'healthy')->count()
                : 0;
            $result[] = [
                'date'   => $date->toDateString(),
                'uptime' => $total > 0 ? round(($ok / $total) * 100, 1) : null,
            ];
        }
        return $result;
    }
}
