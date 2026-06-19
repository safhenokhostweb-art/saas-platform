<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ThemeTemplate extends Model
{
    public const TYPES = [
        'section'      => 'بخش',
        'page'         => 'صفحه',
        'header'       => 'هدر',
        'footer'       => 'فوتر',
        'mega_menu'    => 'مگامنو',
        'popup'        => 'پاپ‌آپ',
        'landing_page' => 'لندینگ پیج',
    ];

    public const CATEGORIES = [
        'hero'          => 'هیرو',
        'features'      => 'ویژگی‌ها',
        'pricing'       => 'قیمت‌گذاری',
        'testimonials'  => 'نظرات',
        'faq'           => 'سوالات متداول',
        'contact'       => 'تماس',
        'team'          => 'تیم',
        'gallery'       => 'گالری',
        'blog'          => 'وبلاگ',
        'product'       => 'محصول',
        'checkout'      => 'پرداخت',
        'corporate'     => 'شرکتی',
        'personal'      => 'شخصی',
        'store'         => 'فروشگاه',
        'education'     => 'آموزش',
    ];

    public const LICENSE_TYPES = ['free', 'premium', 'commercial', 'mit'];

    protected $fillable = [
        'uuid', 'type', 'category', 'name', 'description',
        'tags', 'author', 'version', 'license_type', 'compatibility',
        'blocks', 'global_styles', 'demo_data',
        'thumbnail_url', 'preview_image_url',
        'is_public', 'is_active', 'usage_count',
        'site_id', 'created_by',
    ];

    protected $casts = [
        'tags'          => 'array',
        'compatibility' => 'array',
        'blocks'        => 'array',
        'global_styles' => 'array',
        'demo_data'     => 'array',
        'is_public'     => 'boolean',
        'is_active'     => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopePlatform($query)
    {
        return $query->whereNull('site_id');
    }

    public function scopeForSite($query, int $siteId)
    {
        return $query->where(function ($q) use ($siteId) {
            $q->whereNull('site_id')->orWhere('site_id', $siteId);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }

    // ── Methods ──────────────────────────────────────────────────

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function toExportArray(): array
    {
        return [
            'uuid'              => $this->uuid,
            'type'              => $this->type,
            'category'          => $this->category,
            'name'              => $this->name,
            'description'       => $this->description,
            'tags'              => $this->tags ?? [],
            'author'            => $this->author,
            'version'           => $this->version,
            'license_type'      => $this->license_type,
            'compatibility'     => $this->compatibility ?? [],
            'blocks'            => $this->blocks ?? [],
            'global_styles'     => $this->global_styles,
            'demo_data'         => $this->demo_data,
            'thumbnail_url'     => $this->thumbnail_url,
            'preview_image_url' => $this->preview_image_url,
            'exported_at'       => now()->toISOString(),
            'exporter'          => 'saas-platform-tb3',
        ];
    }
}
