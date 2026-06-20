<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ThemeLicense extends Model
{
    protected $fillable = [
        'site_id',
        'theme_id',
        'purchase_order_id',
        'license_key',
        'is_permanent',
        'purchased_at',
        'installed_theme_version',
    ];

    protected $casts = [
        'is_permanent'            => 'boolean',
        'purchased_at'            => 'datetime',
        'installed_theme_version' => 'integer',
    ];

    public static function grant(int $siteId, int $themeId, ?int $orderId = null): self
    {
        $currentVersion = Theme::find($themeId)?->version ?? 1;

        return self::firstOrCreate(
            ['site_id' => $siteId, 'theme_id' => $themeId],
            [
                'purchase_order_id'       => $orderId,
                'license_key'             => Str::upper(Str::random(8)) . '-' . Str::upper(Str::random(8)) . '-' . Str::upper(Str::random(8)),
                'is_permanent'            => true,
                'purchased_at'            => now(),
                'installed_theme_version' => $currentVersion,
            ]
        );
    }

    public function isOutdated(): bool
    {
        return $this->installed_theme_version !== null
            && $this->installed_theme_version < ($this->theme?->version ?? 1);
    }

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
