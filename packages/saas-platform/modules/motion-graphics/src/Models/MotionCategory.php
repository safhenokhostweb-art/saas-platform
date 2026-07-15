<?php

namespace SaaSPlatform\Modules\MotionGraphics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MotionCategory extends Model
{
    protected $fillable = ['motion_group_id', 'name', 'slug', 'sort_order', 'is_active'];
    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(MotionGroup::class, 'motion_group_id');
    }

    public function motionGraphics(): HasMany
    {
        return $this->hasMany(MotionGraphic::class, 'category_id');
    }
}
