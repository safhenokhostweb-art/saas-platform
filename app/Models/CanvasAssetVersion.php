<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanvasAssetVersion extends Model
{
    protected $table = 'canvas_asset_versions';

    protected $fillable = [
        'asset_kind',
        'asset_slug',
        'asset_key',
        'version',
        'changelog',
        'snapshot_summary',
        'published_by_admin_id',
        'published_at',
    ];

    public function getAssetKeyAttribute(): string
    {
        return $this->attributes['asset_slug'] ?? '';
    }

    public function setAssetKeyAttribute($value): void
    {
        $this->attributes['asset_slug'] = $value;
    }

    protected $casts = [
        'snapshot_summary' => 'array',
        'published_at' => 'datetime',
    ];

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_admin_id');
    }
}
