<?php

namespace SaaSPlatform\Modules\MotionGraphics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MotionGroup extends Model
{
    protected $fillable = [
        'name', 'slug', 'ownership_type', 'owner_site_identifier',
        'search_metadata', 'sort_order', 'is_active',
    ];

    protected $casts = ['search_metadata' => 'array', 'sort_order' => 'integer', 'is_active' => 'boolean'];

    public function categories(): HasMany
    {
        return $this->hasMany(MotionCategory::class);
    }

    public function motionGraphics(): HasMany
    {
        return $this->hasMany(MotionGraphic::class, 'group_id');
    }
}
