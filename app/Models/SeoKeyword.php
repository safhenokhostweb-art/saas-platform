<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoKeyword extends Model
{
    protected $fillable = ['site_id', 'keyword', 'target_url', 'position', 'search_volume', 'tracked_at'];

    protected $casts = [
        'tracked_at' => 'date',
        'position' => 'integer',
        'search_volume' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
