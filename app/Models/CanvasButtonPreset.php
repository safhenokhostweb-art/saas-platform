<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanvasButtonPreset extends Model
{
    protected $table = 'canvas_button_presets';

    protected $fillable = [
        'pack_id',
        'slug',
        'title',
        'css_tokens',
        'preview_html',
        'sort_order',
    ];

    protected $casts = [
        'css_tokens' => 'array',
        'sort_order' => 'integer',
    ];

    public function pack(): BelongsTo
    {
        return $this->belongsTo(CanvasButtonPack::class, 'pack_id');
    }
}
