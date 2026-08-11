<?php

namespace App\Modules\MotionGraphics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * Append-only assignment history for one bespoke commission — mirrors
 * AssetActivation (Phase 3) exactly. Never updates a prior row; a rollback
 * always inserts a new event with event_type = rollback and
 * previous_version set to whatever was active immediately before.
 */
final class MotionBespokeAssignmentEvent extends Model
{
    public const TYPE_INSTALL = 'install';
    public const TYPE_UPGRADE = 'upgrade';
    public const TYPE_ROLLBACK = 'rollback';
    public const TYPE_REVOKE = 'revoke';

    public const UPDATED_AT = null;

    protected $table = 'motion_bespoke_assignment_events';

    protected $fillable = [
        'uuid', 'assignment_id', 'version', 'previous_version', 'event_type', 'actor_identifier',
    ];

    protected $casts = [
        'version' => 'integer',
        'previous_version' => 'integer',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->uuid ??= (string) Str::uuid();
        });
        static::updating(fn () => throw new LogicException('Bespoke assignment history is append-only and cannot be modified.'));
        static::deleting(fn () => throw new LogicException('Bespoke assignment history cannot be deleted.'));
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MotionBespokeAssignment::class, 'assignment_id');
    }
}
