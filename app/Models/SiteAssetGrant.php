<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;

/**
 * ADR-087: one platform asset unlocked for one site. Written only by
 * App\Services\AssetProvisioning\SiteAssetEntitlementService.
 */
class SiteAssetGrant extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const SOURCE_ADMIN_GRANT = 'admin_grant';

    public const SOURCE_PURCHASE = 'purchase';

    public const SOURCE_LICENSE = 'license';

    public const SOURCES = [self::SOURCE_ADMIN_GRANT, self::SOURCE_PURCHASE, self::SOURCE_LICENSE];

    public const SOURCE_LABELS = [
        self::SOURCE_ADMIN_GRANT => 'اختصاص ادمین',
        self::SOURCE_PURCHASE => 'خرید',
        self::SOURCE_LICENSE => 'لایسنس',
    ];

    protected $fillable = [
        'site_id', 'asset_kind', 'asset_key', 'status', 'source', 'reference',
        'granted_by', 'granted_at', 'revoked_by', 'revoked_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function ref(): string
    {
        return AssetRef::make($this->asset_kind, $this->asset_key);
    }
}
