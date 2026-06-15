<?php
namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Model;

class InfraPlatformHealthLog extends Model
{
    protected $table = 'infra_platform_health_logs';

    protected $fillable = [
        'database_status', 'redis_status', 'queue_status', 'queue_size', 'failed_jobs',
        'reverb_status', 'sms_provider_status', 'sms_provider_name',
        'ai_provider_status', 'ai_provider_name', 'storage_status',
        'disk_used_percent', 'disk_free_gb', 'memory_used_percent',
        'cpu_load_1m', 'overall_status', 'checked_at',
    ];

    protected $casts = [
        'checked_at'         => 'datetime',
        'disk_free_gb'       => 'float',
        'cpu_load_1m'        => 'float',
        'queue_size'         => 'integer',
        'failed_jobs'        => 'integer',
        'disk_used_percent'  => 'integer',
        'memory_used_percent'=> 'integer',
    ];

    public static function latest24h(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('checked_at', '>=', now()->subHours(24))
            ->orderBy('checked_at')
            ->get();
    }

    public static function latestCheck(): ?self
    {
        return static::orderByDesc('checked_at')->first();
    }

    public function isHealthy(): bool { return $this->overall_status === 'online'; }
    public function isCritical(): bool { return $this->overall_status === 'critical'; }
}
