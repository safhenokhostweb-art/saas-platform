<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['site_id', 'key', 'value'];

    /** کلیدهای قابل override برای ظاهر سایت (خالی = استفاده از پیش‌فرض پلتفرم) */
    public const DESIGN_KEYS = [
        'theme_primary', 'theme_secondary', 'theme_menu_bg', 'theme_menu_text',
        'theme_menu_link_active', 'theme_title_color', 'theme_link_color', 'theme_link_hover',
        'theme_button_primary_bg', 'theme_button_primary_text',
    ];

    public const SITE_LOGO_KEY = 'site_logo';

    /** کلیدهای SEO قابل تنظیم برای هر سایت (tenant-level) */
    public const SEO_KEYS = [
        'seo_default_title',
        'seo_default_description',
        'seo_robots',
        'seo_og_image',
        'seo_twitter_card',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public static function getForSite(int $siteId, string $key, ?string $default = null): ?string
    {
        $row = self::where('site_id', $siteId)->where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    public static function setForSite(int $siteId, string $key, ?string $value): void
    {
        self::updateOrCreate(
            ['site_id' => $siteId, 'key' => $key],
            ['value' => $value ?? '']
        );
    }

    public static function allForSite(int $siteId): array
    {
        return self::where('site_id', $siteId)->pluck('value', 'key')->all();
    }
}
