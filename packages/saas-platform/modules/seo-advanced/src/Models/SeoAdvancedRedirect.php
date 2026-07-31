<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAdvancedRedirect extends Model
{
    protected $table = 'seo_advanced_redirects';

    protected $fillable = [
        'site_id',
        'from_path',
        'to_path',
        'status_code',
        'is_active',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'is_active' => 'boolean',
    ];
}
