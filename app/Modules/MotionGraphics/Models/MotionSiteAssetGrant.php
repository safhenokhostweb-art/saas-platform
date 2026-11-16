<?php

namespace App\Modules\MotionGraphics\Models;

use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * ADR-086: the explicit entitlement of one site to one PRIVATE motion.
 *
 * Created and revoked only through MotionAssetGrantService (central admin
 * panel). Read by MotionAssetLicensePolicy on every manifest build and by
 * MotionPrivateAssetController on every file request, so revoking takes
 * effect on the next fetch without re-activating anything.
 */
final class MotionSiteAssetGrant extends Model
{
    protected $table = 'motion_site_asset_grants';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    public const SOURCE_ADMIN_GRANT = 'admin_grant';
    public const SOURCE_PURCHASE = 'purchase';
    public const SOURCE_LICENSE = 'license';
    public const SOURCES = [self::SOURCE_ADMIN_GRANT, self::SOURCE_PURCHASE, self::SOURCE_LICENSE];

    protected $fillable = [
        'site_id', 'asset_id', 'status', 'source', 'reference', 'note',
        'granted_by_actor_identifier', 'granted_at',
        'revoked_by_actor_identifier', 'revoked_at',
    ];

    protected $casts = [
        'granted_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $grant): void {
            if ($grant->isDirty(['site_id', 'asset_id'])) {
                throw new LogicException('Motion asset grant identity fields are immutable.');
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public static function isGranted(int $siteId, string $assetId): bool
    {
        return $siteId > 0 && $assetId !== '' && self::query()->active()
            ->where('site_id', $siteId)
            ->where('asset_id', $assetId)
            ->exists();
    }

    /** @return list<string> asset ids this site holds an active grant for */
    public static function grantedAssetIds(int $siteId): array
    {
        return self::query()->active()->where('site_id', $siteId)->orderBy('id')->pluck('asset_id')->all();
    }
}
