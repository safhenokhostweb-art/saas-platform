<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbCategory extends Model
{
    protected $table = 'kb_categories';

    protected $fillable = [
        'site_id', 'parent_id', 'name', 'slug', 'icon', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

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
        return $this->hasMany(KbArticle::class, 'kb_category_id');
    }

    public function publishedArticles(): HasMany
    {
        return $this->hasMany(KbArticle::class, 'kb_category_id')->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
