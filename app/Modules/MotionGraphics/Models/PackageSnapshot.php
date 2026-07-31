<?php

namespace App\Modules\MotionGraphics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * An immutable, fully frozen snapshot of a Site Asset Package's resolved
 * state at one point in time (template reference, brand variant, and
 * every item's {asset_id, version, checksum, type}). Captured on every
 * activation/upgrade/rollback so a package's exact historical state can
 * always be reconstructed without re-querying the standalone module.
 * Append-only — never updated. See
 * docs/motion-persistence/ACTIVATION_FLOW.md.
 */
final class PackageSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'motion_package_snapshots';

    protected $fillable = [
        'uuid', 'site_asset_package_id', 'snapshot', 'checksum', 'captured_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'captured_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $snapshot): void {
            $snapshot->uuid ??= (string) Str::uuid();
        });
        static::updating(fn () => throw new LogicException('Package snapshots are append-only and cannot be modified.'));
        static::deleting(fn () => throw new LogicException('Package snapshots cannot be deleted.'));
    }

    public function siteAssetPackage(): BelongsTo
    {
        return $this->belongsTo(SiteAssetPackage::class);
    }
}
