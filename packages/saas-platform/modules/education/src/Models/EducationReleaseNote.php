<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EducationReleaseNote extends Model
{
    use SoftDeletes;

    protected $table = 'edu_release_notes';

    protected $fillable = [
        'title', 'slug', 'body', 'version_number',
        'release_type', 'module_slug', 'site_id',
        'revision_id', 'is_published', 'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $note) {
            if (empty($note->slug)) {
                $note->slug = Str::slug($note->title . '-' . $note->version_number) . '-' . Str::random(4);
            }
        });
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(EducationArticleRevision::class, 'revision_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeForSite($query, ?int $siteId)
    {
        return $query->where(function ($q) use ($siteId) {
            $q->whereNull('site_id')->orWhere('site_id', $siteId);
        });
    }
}
