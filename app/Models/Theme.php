<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Theme Builder: full visual theme (header, footer, global styles).
 * Multi-tenant: site_id null = platform template; set = tenant override.
 */
class Theme extends Model
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_GENERATOR = 'generator';
    public const SOURCE_SEEDER = 'seeder';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'thumbnail',
        'type',
        'source',
        'site_id',
        'header_blocks',
        'footer_blocks',
        'global_styles',
        'settings',
        'status',
        'published_at',
        'version',
    ];

    protected $casts = [
        'header_blocks' => 'array',
        'footer_blocks' => 'array',
        'global_styles' => 'array',
        'settings' => 'array',
        'published_at' => 'datetime',
        'version' => 'integer',
    ];

    public const TYPE_FULL = 'full';
    public const TYPE_HEADER = 'header';
    public const TYPE_FOOTER = 'footer';
    public const TYPE_LAYOUT = 'layout';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function revisions()
    {
        return $this->hasMany(ThemeRevision::class)->orderByDesc('version');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeForSite($query, ?int $siteId)
    {
        if ($siteId === null) {
            return $query->whereNull('site_id');
        }
        return $query->where(function ($q) use ($siteId) {
            $q->whereNull('site_id')->orWhere('site_id', $siteId);
        });
    }

    public function scopeTemplates($query)
    {
        return $query->whereNull('site_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** Get payload for front-end (header + footer + global_styles + metadata). */
    public function getPayload(): array
    {
        $settings = $this->settings ?? [];
        $metaFromSettings = $settings['meta'] ?? [];

        $meta = array_merge([
            'id' => $this->slug,
            'name' => $this->name,
            // Semantic versioning string; falls back to 1.0.0 if not set.
            'version' => $metaFromSettings['version']
                ?? ($settings['version'] ?? '1.0.0'),
            // Release date in Gregorian; callers should format to Jalali for display.
            'release_date' => $metaFromSettings['release_date']
                ?? ($settings['release_date'] ?? ($this->published_at?->toDateString())),
            // Map of version => list of changes (or a flat list for current version).
            'changelog' => $metaFromSettings['changelog']
                ?? ($settings['changelog'] ?? []),
            'compatibility_notes' => $metaFromSettings['compatibility_notes']
                ?? ($settings['compatibility_notes'] ?? null),
            'required_platform_version' => $metaFromSettings['required_platform_version']
                ?? ($settings['required_platform_version'] ?? '1.0.0'),
            // List of module slugs this theme is designed to work well with.
            'supported_modules' => $metaFromSettings['supported_modules']
                ?? ($settings['supported_modules'] ?? []),
        ], $metaFromSettings);

        return [
            'header_blocks' => $this->header_blocks ?? [],
            'footer_blocks' => $this->footer_blocks ?? [],
            'global_styles' => $this->global_styles ?? [],
            'settings' => $settings,
            'meta' => $meta,
        ];
    }
}
