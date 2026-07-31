<?php

namespace App\Modules\MotionGraphics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * Append-only activation history for one Site Asset Package item.
 * Rollback NEVER updates a prior row — it always inserts a new one with
 * `activation_type = rollback` and `previous_version` set to whatever
 * was active immediately before. See
 * docs/motion-persistence/ROLLBACK_MODEL.md.
 */
final class AssetActivation extends Model
{
    public const TYPE_INSTALL = 'install';
    public const TYPE_UPGRADE = 'upgrade';
    public const TYPE_ROLLBACK = 'rollback';

    public const UPDATED_AT = null;

    protected $table = 'motion_asset_activations';

    protected $fillable = [
        'uuid', 'site_asset_package_item_id', 'asset_id', 'version',
        'previous_version', 'activation_type', 'activated_at', 'actor_identifier',
    ];

    protected $casts = [
        'version' => 'integer',
        'previous_version' => 'integer',
        'activated_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $activation): void {
            $activation->uuid ??= (string) Str::uuid();
        });
        static::updating(fn () => throw new LogicException('Asset activation history is append-only and cannot be modified.'));
        static::deleting(fn () => throw new LogicException('Asset activation history cannot be deleted.'));
    }

    public function siteAssetPackageItem(): BelongsTo
    {
        return $this->belongsTo(SiteAssetPackageItem::class);
    }
}
