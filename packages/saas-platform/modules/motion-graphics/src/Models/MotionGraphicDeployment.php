<?php

namespace SaaSPlatform\Modules\MotionGraphics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MotionGraphicDeployment extends Model
{
    protected $fillable = [
        'motion_graphic_id', 'external_site_identifier', 'page_identifier',
        'section_identifier', 'placement_identifier', 'sort_order', 'is_enabled',
        'follow_latest_published', 'selected_version', 'installed_at',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
        'follow_latest_published' => 'boolean',
        'selected_version' => 'integer',
        'installed_at' => 'immutable_datetime',
    ];

    public function motionGraphic(): BelongsTo
    {
        return $this->belongsTo(MotionGraphic::class);
    }
}
