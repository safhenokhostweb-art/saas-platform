<?php

namespace App\Modules\MotionGraphics\Models;

use App\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * One site's binding to a template, identified by (`site_id`,
 * `template_reference`) — its natural key. `site_id` is the
 * platform-trusted tenant boundary; the module's own asset ownership
 * data is never duplicated here — only the site<->template<->brand
 * binding and its lifecycle status.
 *
 * `template_asset_package_id` is the CURRENT POINTER to which frozen
 * TemplateAssetPackage version this binding uses; it moves forward on
 * upgrade (mirroring MotionGraphic.published_version) while
 * `template_reference` itself never changes. See
 * docs/motion-persistence/SITE_PACKAGE_MODEL.md and
 * docs/motion-persistence/VERSION_BINDING_RULES.md.
 */
final class SiteAssetPackage extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ROLLED_BACK = 'rolled_back';
    public const STATUS_RETIRED = 'retired';

    protected $table = 'motion_site_asset_packages';

    protected $fillable = [
        'uuid', 'site_id', 'template_reference', 'template_asset_package_id', 'brand_variant_reference',
        'activation_status', 'activated_at', 'created_by_actor_identifier',
    ];

    protected $casts = [
        'activated_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $package): void {
            $package->uuid ??= (string) Str::uuid();
            $package->activation_status ??= self::STATUS_PENDING;
        });
        static::updating(function (self $package): void {
            $immutable = ['site_id', 'template_reference', 'brand_variant_reference', 'created_by_actor_identifier'];
            if ($package->isDirty($immutable)) {
                throw new LogicException('Site Asset Package identity fields are immutable.');
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function templateAssetPackage(): BelongsTo
    {
        return $this->belongsTo(TemplateAssetPackage::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SiteAssetPackageItem::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PackageSnapshot::class);
    }
}
