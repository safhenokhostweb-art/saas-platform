<?php

namespace App\Modules\MotionGraphics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * The "current pointer" for one asset within a Site Asset Package —
 * which version is currently active and its bound integrity checksum.
 * This pointer moves on upgrade/rollback (mirroring
 * MotionGraphic.published_version); the full history of how it moved
 * lives in AssetActivation, which is append-only. See
 * docs/motion-persistence/VERSION_BINDING_RULES.md.
 */
final class SiteAssetPackageItem extends Model
{
    protected $table = 'motion_site_asset_package_items';

    protected $fillable = [
        'uuid', 'site_asset_package_id', 'asset_id', 'active_version',
        'bound_checksum', 'is_active', 'installed_at',
    ];

    protected $casts = [
        'active_version' => 'integer',
        'is_active' => 'boolean',
        'installed_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->uuid ??= (string) Str::uuid();
        });
        static::updating(function (self $item): void {
            $immutable = ['site_asset_package_id', 'asset_id', 'installed_at'];
            if ($item->isDirty($immutable)) {
                throw new LogicException('Site Asset Package item identity fields are immutable.');
            }
        });
    }

    public function siteAssetPackage(): BelongsTo
    {
        return $this->belongsTo(SiteAssetPackage::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(AssetActivation::class);
    }
}
