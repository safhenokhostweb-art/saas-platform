<?php

namespace SaaSPlatform\Modules\MotionGraphics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class MotionTag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function motionGraphics(): BelongsToMany
    {
        return $this->belongsToMany(MotionGraphic::class, 'motion_graphic_tag');
    }
}
