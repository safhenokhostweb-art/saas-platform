<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = ['site_id', 'name', 'location', 'order'];

    protected $casts = [
        'order' => 'integer',
    ];

    public const LOCATION_HEADER = 'header';
    public const LOCATION_FOOTER = 'footer';
    public const LOCATION_SIDEBAR = 'sidebar';

    public static function locations(): array
    {
        return [
            self::LOCATION_HEADER => 'هدر',
            self::LOCATION_FOOTER => 'فوتر',
            self::LOCATION_SIDEBAR => 'سایدبار',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->whereNull('parent_id')->orderBy('order');
    }

    public function allItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('order');
    }
}
