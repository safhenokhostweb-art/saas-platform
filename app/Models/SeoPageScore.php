<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoPageScore extends Model
{
    protected $fillable = ['site_id', 'page_url', 'page_type', 'score', 'checks', 'scored_at'];

    protected $casts = [
        'checks' => 'array',
        'scored_at' => 'datetime',
        'score' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
