<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EducationArticle extends Model
{
    use SoftDeletes;

    protected $table = 'edu_articles';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED  = 'archived';

    protected $fillable = [
        'edu_category_id', 'site_id', 'author_id',
        'title', 'slug', 'excerpt', 'body',
        'status', 'content_type', 'target_roles',
        'module_slug', 'route_pattern', 'auto_generated',
        'view_count', 'helpful_count', 'not_helpful_count',
        'tags', 'meta', 'published_at',
    ];

    protected $casts = [
        'target_roles'   => 'array',
        'tags'           => 'array',
        'meta'           => 'array',
        'published_at'     => 'datetime',
        'auto_generated'   => 'boolean',
        'view_count'       => 'integer',
        'helpful_count'  => 'integer',
        'not_helpful_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title) . '-' . Str::random(4);
            }
            if (empty($article->excerpt) && $article->body) {
                $article->excerpt = Str::limit(strip_tags($article->body), 180);
            }
        });

        static::updated(function (self $article) {
            if ($article->wasChanged(['title', 'body', 'excerpt'])) {
                $article->revisions()->create([
                    'author_id'       => auth()->id(),
                    'title'           => $article->title,
                    'excerpt'         => $article->excerpt,
                    'body'            => $article->body,
                    'revision_number' => $article->revisions()->count() + 1,
                ]);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EducationCategory::class, 'edu_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'), 'author_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(EducationArticleRevision::class, 'edu_article_id')->orderByDesc('revision_number');
    }

    public function latestRevision(): HasMany
    {
        return $this->hasMany(EducationArticleRevision::class, 'edu_article_id')->orderByDesc('revision_number')->limit(1);
    }

    public function media(): HasMany
    {
        return $this->hasMany(EducationMedia::class, 'edu_article_id')->orderBy('sort_order');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeForSite($query, ?int $siteId)
    {
        return $query->where(function ($q) use ($siteId) {
            $q->whereNull('site_id')->orWhere('site_id', $siteId);
        });
    }

    public function scopeForRole($query, ?string $role)
    {
        if (! $role) {
            return $query;
        }
        return $query->where(function ($q) use ($role) {
            $q->whereNull('target_roles')
              ->orWhereJsonContains('target_roles', $role);
        });
    }

    public function scopeForModule($query, ?string $moduleSlug)
    {
        if (! $moduleSlug) {
            return $query;
        }
        return $query->where('module_slug', $moduleSlug);
    }

    public function scopeForRoute($query, ?string $routePattern)
    {
        if (! $routePattern) {
            return $query;
        }
        return $query->where('route_pattern', $routePattern);
    }

    public function incrementView(): void
    {
        $this->increment('view_count');
    }

    public function markHelpful(bool $helpful): void
    {
        $this->increment($helpful ? 'helpful_count' : 'not_helpful_count');
    }

    public function getHelpfulnessPercentAttribute(): ?int
    {
        $total = $this->helpful_count + $this->not_helpful_count;
        return $total > 0 ? (int) round($this->helpful_count / $total * 100) : null;
    }
}
