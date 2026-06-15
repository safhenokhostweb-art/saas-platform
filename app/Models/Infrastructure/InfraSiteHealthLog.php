<?php
namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class InfraSiteHealthLog extends Model
{
    protected $fillable = [
        'site_id', 'domain', 'status', 'http_status', 'response_time_ms',
        'ssl_status', 'ssl_expires_at', 'ssl_days_remaining',
        'db_status', 'redis_status', 'queue_status',
        'failed_jobs', 'disk_used_percent', 'memory_used_percent',
        'domain_expires_at', 'domain_days_remaining',
        'error_message', 'checked_at',
    ];

    protected $casts = [
        'ssl_expires_at'    => 'date',
        'domain_expires_at' => 'date',
        'checked_at'        => 'datetime',
    ];

    public function site()
    {
        return $this->belongsTo(\App\Models\Site::class);
    }

    public function isHealthy(): bool  { return $this->status === 'healthy'; }
    public function isWarning(): bool  { return $this->status === 'warning'; }
    public function isCritical(): bool { return $this->status === 'critical'; }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'healthy'  => '<span class="badge-green">سالم</span>',
            'warning'  => '<span class="badge-amber">هشدار</span>',
            'critical' => '<span class="badge-red">بحرانی</span>',
            default    => '<span class="badge-gray">نامشخص</span>',
        };
    }

    public function scopeLatestPerSite(Builder $query): Builder
    {
        return $query->whereIn('id', function ($sub) {
            $sub->selectRaw('MAX(id)')
                ->from('infra_site_health_logs')
                ->groupBy('site_id');
        });
    }

    public function scopeRecent(Builder $query, int $minutes = 60): Builder
    {
        return $query->where('checked_at', '>=', now()->subMinutes($minutes));
    }

    public static function uptimePercent(int $siteId, int $hours = 24): float
    {
        $total = static::where('site_id', $siteId)
            ->where('checked_at', '>=', now()->subHours($hours))
            ->count();

        if ($total === 0) {
            return 100.0;
        }

        $healthy = static::where('site_id', $siteId)
            ->where('checked_at', '>=', now()->subHours($hours))
            ->where('status', 'healthy')
            ->count();

        return round(($healthy / $total) * 100, 1);
    }

    public static function uptimeHistory(int $siteId, int $days = 7): array
    {
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date  = now()->subDays($i)->startOfDay();
            $end   = $date->copy()->endOfDay();
            $total = static::where('site_id', $siteId)->whereBetween('checked_at', [$date, $end])->count();
            $ok    = $total > 0
                ? static::where('site_id', $siteId)->whereBetween('checked_at', [$date, $end])->where('status', 'healthy')->count()
                : 0;
            $result[] = [
                'date'   => $date->toDateString(),
                'uptime' => $total > 0 ? round(($ok / $total) * 100, 1) : null,
            ];
        }
        return $result;
    }
}
