<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationCategory extends Model
{
    protected $table = 'edu_categories';

    protected $fillable = [
        'parent_id', 'site_id', 'name', 'slug', 'icon',
        'sort_order', 'content_type', 'visibility', 'allowed_roles', 'is_active',
    ];

    protected $casts = [
        'allowed_roles' => 'array',
        'is_active'     => 'boolean',
        'sort_order'    => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(EducationArticle::class, 'edu_category_id');
    }

    public function publishedArticles(): HasMany
    {
        return $this->hasMany(EducationArticle::class, 'edu_category_id')
            ->where('status', EducationArticle::STATUS_PUBLISHED);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSite($query, ?int $siteId)
    {
        return $query->where(function ($q) use ($siteId) {
            $q->whereNull('site_id')->orWhere('site_id', $siteId);
        });
    }
}
