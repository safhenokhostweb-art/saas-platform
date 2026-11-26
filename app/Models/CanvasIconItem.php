<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanvasIconItem extends Model
{
    protected $table = 'canvas_icon_items';

    protected $fillable = [
        'pack_id',
        'slug',
        'title',
        'keywords',
        'svg_content',
        'view_box',
        'sort_order',
    ];

    protected $casts = [
        'keywords' => 'array',
        'sort_order' => 'integer',
    ];

    public function pack(): BelongsTo
    {
        return $this->belongsTo(CanvasIconPack::class, 'pack_id');
    }
}
