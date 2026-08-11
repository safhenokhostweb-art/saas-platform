<?php

namespace App\Modules\MotionGraphics\Models;

use App\Models\Product;
use App\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

/**
 * A commercial commission record for one CUSTOM/bespoke Motion asset,
 * scoped to exactly one site. `asset_id` is an opaque reference to the
 * standalone module's own MotionGraphic.uuid (ownership_type =
 * customer_specific) once a designer has actually created it — no FK into
 * the module's tables, mirroring every other Phase 3+ motion_* table.
 *
 * This table owns the BUSINESS/commercial lifecycle status, distinct from
 * the module's own per-version content-readiness status:
 * draft -> designing -> review -> approved -> published -> archived.
 * "assigned" (task's 7th state) is not a status here — it is the existence
 * of an active MotionBespokeAssignment for this commission.
 */
final class MotionBespokeCommission extends Model
{
    protected $table = 'motion_bespoke_commissions';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_DESIGNING = 'designing';
    public const STATUS_REVIEW = 'review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    /** Forward-only lifecycle graph — see MotionBespokeCommissionService::transition(). */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_DESIGNING, self::STATUS_ARCHIVED],
        self::STATUS_DESIGNING => [self::STATUS_REVIEW, self::STATUS_ARCHIVED],
        self::STATUS_REVIEW => [self::STATUS_APPROVED, self::STATUS_DESIGNING, self::STATUS_ARCHIVED],
        self::STATUS_APPROVED => [self::STATUS_PUBLISHED, self::STATUS_ARCHIVED],
        self::STATUS_PUBLISHED => [self::STATUS_ARCHIVED],
        self::STATUS_ARCHIVED => [],
    ];

    protected $fillable = [
        'uuid', 'site_id', 'asset_id', 'product_id', 'title', 'description', 'status',
        'created_by_actor_identifier', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $commission): void {
            $commission->uuid ??= (string) Str::uuid();
            $commission->status ??= self::STATUS_DRAFT;
        });
        static::updating(function (self $commission): void {
            if ($commission->isDirty('site_id')) {
                throw new LogicException('A bespoke commission cannot be reassigned to a different owning site.');
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(MotionBespokeAssignment::class, 'commission_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(MotionBespokePurchase::class, 'commission_id');
    }
}
