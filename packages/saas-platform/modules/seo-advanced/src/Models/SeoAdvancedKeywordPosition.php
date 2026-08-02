<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAdvancedKeywordPosition extends Model
{
    protected $table = 'seo_advanced_keyword_positions';

    protected $attributes = [
        'search_engine' => 'google',
        'source' => 'manual',
    ];

    protected $fillable = [
        'site_id',
        'keyword_id',
        'url',
        'position',
        'recorded_at',
        'search_engine',
        'source',
    ];

    protected $casts = [
        'position' => 'integer',
        'recorded_at' => 'date',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoAdvancedKeyword::class, 'keyword_id');
    }
}
