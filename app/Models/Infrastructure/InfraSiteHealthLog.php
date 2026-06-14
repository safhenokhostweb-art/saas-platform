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
        'error_message', 'checked_at',
    ];

    protected $casts = [
        'ssl_expires_at' => 'date',
        'checked_at'     => 'datetime',
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
}
