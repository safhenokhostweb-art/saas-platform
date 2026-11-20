<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteResourceUsage extends Model
{
    protected $table = 'site_resource_usage';

    protected $fillable = [
        'site_id',
        'storage_bytes',
        'disk_usage_bytes',
        'danger_level',
        'disk_audited_at',
        'disk_audit_error',
        'db_bytes',
        'backup_bytes',
        'archive_bytes',
        'file_count',
        'bandwidth_bytes_monthly',
        'request_count_monthly',
        'cpu_weight',
        'ram_weight',
        'alerted_80',
        'alerted_95',
        'soft_exceeded_at',
        'alerted_soft',
        'soft_grace_escalated_at',
        'calculated_at',
    ];

    protected $casts = [
        'alerted_80'    => 'boolean',
        'alerted_95'    => 'boolean',
        'alerted_soft'  => 'boolean',
        'soft_exceeded_at'        => 'datetime',
        'soft_grace_escalated_at' => 'datetime',
        'calculated_at' => 'datetime',
        'disk_audited_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function totalBytes(): int
    {
        return (int) ($this->storage_bytes + $this->db_bytes + $this->backup_bytes);
    }

    public function formattedStorage(): string
    {
        return self::formatBytes($this->storage_bytes);
    }

    public function formattedDb(): string
    {
        return self::formatBytes($this->db_bytes);
    }

    public function formattedTotal(): string
    {
        return self::formatBytes($this->totalBytes());
    }

    public function formattedArchive(): string
    {
        return self::formatBytes($this->archive_bytes);
    }

    public function formattedBandwidth(): string
    {
        return self::formatBytes($this->bandwidth_bytes_monthly);
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    public function storagePercent(?int $limitBytes): float
    {
        if (!$limitBytes || $limitBytes === 0) return 0.0;
        return round(($this->storage_bytes / $limitBytes) * 100, 1);
    }

    public function bandwidthPercent(?int $limitBytes): float
    {
        if (!$limitBytes || $limitBytes === 0) return 0.0;
        return round(($this->bandwidth_bytes_monthly / $limitBytes) * 100, 1);
    }

    public function scopeOverStoragePercent($query, float $percent, int $defaultLimitBytes = 1073741824)
    {
        $threshold = (int) ($defaultLimitBytes * $percent / 100);
        return $query->where('storage_bytes', '>=', $threshold);
    }

    public function scopeOrderByStorage($query, string $dir = 'desc')
    {
        return $query->orderBy('storage_bytes', $dir);
    }

    public function scopeOrderByDbSize($query, string $dir = 'desc')
    {
        return $query->orderBy('db_bytes', $dir);
    }
}
