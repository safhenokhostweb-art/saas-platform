<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAdvancedAudit extends Model
{
    protected $table = 'seo_advanced_audits';

    protected $fillable = [
        'site_id',
        'page_url',
        'title_length',
        'description_length',
        'missing_meta',
        'missing_alt_images',
        'issues',
        'score',
        'audited_at',
    ];

    protected $casts = [
        'title_length' => 'integer',
        'description_length' => 'integer',
        'missing_meta' => 'array',
        'missing_alt_images' => 'array',
        'issues' => 'array',
        'score' => 'integer',
        'audited_at' => 'datetime',
    ];
}
