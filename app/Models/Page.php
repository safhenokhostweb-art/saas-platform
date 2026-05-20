<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Page extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'icon',
        'icon_default',
        'content',
        'menu_position',
        'menu_order',
        'is_active',
        'meta_description',
    ];

    protected $casts = [
        'content' => 'array',
        'is_active' => 'boolean',
    ];

    public static function menuPositions(): array
    {
        return [
            'top' => 'بالا (نوار منو)',
            'bottom' => 'پایین (فوتر)',
            'left' => 'کنار چپ',
            'right' => 'کنار راست',
        ];
    }

    /** لیست آیکون‌های پیش‌فرض برای انتخاب در صفحه (از پکیج آیکون‌ها) */
    public static function defaultIconNames(): array
    {
        return \App\Helpers\Icons::all();
    }

    public function getIconUrlAttribute(): ?string
    {
        if (!$this->icon) {
            return null;
        }
        $icon = trim($this->icon);
        if (preg_match('#^https?://#i', $icon) || str_starts_with($icon, '/')) {
            return $icon;
        }
        $path = \App\Helpers\PlatformSettings::normalizeStoragePath($icon);
        if ($path === '') {
            return null;
        }
        return '/storage/' . $path;
    }

    public static function boot()
    {
        parent::boot();
        static::creating(function ($page) {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });
    }
}
