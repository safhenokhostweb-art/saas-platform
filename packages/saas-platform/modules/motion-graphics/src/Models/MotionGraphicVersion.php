<?php

namespace SaaSPlatform\Modules\MotionGraphics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class MotionGraphicVersion extends Model
{
    protected $fillable = [
        'motion_graphic_id', 'version', 'parent_version', 'source_snapshot',
        'editable_schema_snapshot', 'ai_metadata_json', 'compiled_assets',
        'preview_metadata', 'source_checksum', 'status', 'change_description',
        'created_by_actor_identifier', 'approved_by_actor_identifier', 'approved_at',
        'published_by_actor_identifier', 'published_at',
    ];

    protected $hidden = ['source_snapshot', 'editable_schema_snapshot', 'ai_metadata_json'];

    protected $casts = [
        'source_snapshot' => 'encrypted:array',
        'editable_schema_snapshot' => 'encrypted:array',
        'ai_metadata_json' => 'encrypted:array',
        'compiled_assets' => 'array',
        'preview_metadata' => 'array',
        'approved_at' => 'immutable_datetime',
        'published_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            $immutable = [
                'motion_graphic_id', 'version', 'parent_version', 'source_snapshot',
                'editable_schema_snapshot', 'ai_metadata_json', 'compiled_assets',
                'preview_metadata', 'source_checksum', 'change_description',
                'created_by_actor_identifier',
            ];
            if ($version->isDirty($immutable)) {
                throw new LogicException('Historical motion version content is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Motion version history cannot be deleted.'));
    }

    public function motionGraphic(): BelongsTo
    {
        return $this->belongsTo(MotionGraphic::class);
    }
}
