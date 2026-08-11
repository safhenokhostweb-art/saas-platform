<?php

namespace App\Modules\MotionGraphics\Models;

use App\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * The "current pointer" — which version of a bespoke commission is
 * currently assigned to its owning site, and whether that assignment is
 * active. Exactly one row per commission (unique constraint). The full
 * history of how this pointer moved (install/upgrade/rollback/revoke)
 * lives in MotionBespokeAssignmentEvent, append-only — mirrors the
 * existing SiteAssetPackageItem / AssetActivation pattern (Phase 3).
 */
final class MotionBespokeAssignment extends Model
{
    protected $table = 'motion_bespoke_assignments';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'uuid', 'commission_id', 'site_id', 'asset_id', 'active_version',
        'status', 'assigned_by_actor_identifier', 'assigned_at',
    ];

    protected $casts = [
        'active_version' => 'integer',
        'assigned_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $assignment): void {
            $assignment->uuid ??= (string) Str::uuid();
        });
        static::updating(function (self $assignment): void {
            $immutable = ['commission_id', 'site_id', 'asset_id'];
            if ($assignment->isDirty($immutable)) {
                throw new LogicException('Bespoke assignment identity fields are immutable.');
            }
        });
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(MotionBespokeCommission::class, 'commission_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MotionBespokeAssignmentEvent::class, 'assignment_id');
    }
}
