<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoRedirect extends Model
{
    protected $fillable = ['site_id', 'from_url', 'to_url', 'status_code', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'status_code' => 'integer',
    ];

    public const STATUS_PERMANENT = 301;
    public const STATUS_TEMPORARY = 302;

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
