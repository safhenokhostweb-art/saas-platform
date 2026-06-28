<?php

namespace App\Models\Infrastructure;

use App\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalRecoveryLog extends Model
{
    protected $table = 'local_recovery_logs';

    protected $fillable = [
        'type',
        'site_id',
        'installation_uuid',
        'baseline_label',
        'local_path',
        'size_bytes',
        'sha256',
        'manifest_checksum',
        'status',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'size_bytes'  => 'integer',
        'verified_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isPlatform(): bool { return $this->type === 'platform'; }
    public function isCustomer(): bool { return $this->type === 'customer'; }
    public function isVerified(): bool { return $this->status === 'verified'; }
    public function isFailed(): bool   { return $this->status === 'failed'; }

    public function sizeHuman(): string
    {
        $bytes = $this->size_bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) return round($bytes, 1) . ' ' . $unit;
            $bytes /= 1024;
        }
        return round($bytes, 1) . ' TB';
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'verified' => 'bg-green-100 text-green-800',
            'failed'   => 'bg-red-100 text-red-800',
            default    => 'bg-yellow-100 text-yellow-800',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'verified' => 'تایید شده',
            'failed'   => 'ناموفق',
            default    => 'در انتظار',
        };
    }

    public function scopePlatform($query) { return $query->where('type', 'platform'); }
    public function scopeCustomer($query) { return $query->where('type', 'customer'); }
    public function scopeVerified($query) { return $query->where('status', 'verified'); }
    public function scopeFailed($query)   { return $query->where('status', 'failed'); }
}
