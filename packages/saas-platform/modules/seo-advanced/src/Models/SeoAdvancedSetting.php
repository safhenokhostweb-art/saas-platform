<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAdvancedSetting extends Model
{
    public const SCHEMA_ORGANIZATION = 'organization';
    public const SCHEMA_LOCAL_BUSINESS = 'local_business';
    public const SCHEMA_WEBSITE = 'website';

    protected $table = 'seo_advanced_settings';

    public const CANONICAL_AUTO = 'auto';
    public const CANONICAL_FORCE_HTTPS = 'force_https';
    public const CANONICAL_CUSTOM = 'custom';

    protected $fillable = [
        'site_id',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'robots_directive',
        'canonical_url',
        'canonical_strategy',
        'og_title',
        'og_description',
        'og_image_url',
        'twitter_card_type',
        'schema_type',
        'organization_name',
        'organization_logo_url',
        'business_address',
        'business_phone',
        'title_min_length',
        'title_max_length',
        'description_min_length',
        'description_max_length',
    ];

    protected $casts = [
        'title_min_length' => 'integer',
        'title_max_length' => 'integer',
        'description_min_length' => 'integer',
        'description_max_length' => 'integer',
    ];
}
