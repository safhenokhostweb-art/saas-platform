<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteStorageSnapshot extends Model
{
    protected $table = 'site_storage_snapshots';

    protected $fillable = [
        'site_id',
        'snapshot_date',
        'storage_bytes',
        'db_bytes',
        'backup_bytes',
        'archive_bytes',
        'file_count',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function totalBytes(): int
    {
        return (int) ($this->storage_bytes + $this->db_bytes + $this->backup_bytes);
    }

    public static function upsertToday(int $siteId, array $data): void
    {
        self::updateOrCreate(
            ['site_id' => $siteId, 'snapshot_date' => now()->toDateString()],
            $data
        );
    }

    public function scopeLast30Days($query)
    {
        return $query->where('snapshot_date', '>=', now()->subDays(30)->toDateString());
    }
}
