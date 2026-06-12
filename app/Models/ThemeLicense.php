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
    ];

    protected $casts = [
        'is_permanent' => 'boolean',
        'purchased_at' => 'datetime',
    ];

    public static function grant(int $siteId, int $themeId, ?int $orderId = null): self
    {
        return self::firstOrCreate(
            ['site_id' => $siteId, 'theme_id' => $themeId],
            [
                'purchase_order_id' => $orderId,
                'license_key'       => Str::upper(Str::random(8)) . '-' . Str::upper(Str::random(8)) . '-' . Str::upper(Str::random(8)),
                'is_permanent'      => true,
                'purchased_at'      => now(),
            ]
        );
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
