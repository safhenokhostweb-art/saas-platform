<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationGenerationLog extends Model
{
    protected $table = 'edu_generation_logs';

    public const STATUS_SUCCESS           = 'success';
    public const STATUS_VALIDATION_FAILED = 'validation_failed';
    public const STATUS_PROVIDER_ERROR    = 'provider_error';

    protected $fillable = [
        'site_id',
        'module_slug',
        'content_type',
        'provider',
        'status',
        'confidence_score',
        'source_reason',
        'edu_article_id',
        'metadata',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'metadata'         => 'array',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(EducationArticle::class, 'edu_article_id');
    }

    public function scopeForSite($query, ?int $siteId)
    {
        return $siteId !== null
            ? $query->where('site_id', $siteId)
            : $query->whereNull('site_id');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }
}
