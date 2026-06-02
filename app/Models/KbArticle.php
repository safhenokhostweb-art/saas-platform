<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KbArticle extends Model
{
    use SoftDeletes;

    protected $table = 'kb_articles';

    protected $fillable = [
        'kb_category_id', 'site_id', 'author_id',
        'title', 'slug', 'excerpt', 'body', 'status',
        'view_count', 'helpful_count', 'not_helpful_count',
        'tags', 'published_at',
    ];

    protected $casts = [
        'tags'          => 'array',
        'published_at'  => 'datetime',
        'view_count'    => 'integer',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED  = 'archived';

    protected static function booted(): void
    {
        static::creating(function (KbArticle $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title) . '-' . Str::random(4);
            }
            if (empty($article->excerpt) && $article->body) {
                $article->excerpt = Str::limit(strip_tags($article->body), 160);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'kb_category_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->whereFullText(['title', 'excerpt'], $term)
                     ->orWhere('title', 'like', "%{$term}%");
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
