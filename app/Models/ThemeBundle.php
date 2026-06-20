<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeBundle extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'thumbnail',
        'price',
        'theme_ids',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'theme_ids'   => 'array',
        'price'       => 'integer',
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function themes()
    {
        return Theme::whereNull('site_id')
            ->whereIn('id', $this->theme_ids ?? [])
            ->get();
    }

    public function hasPurchasedBySite(int $siteId): bool
    {
        return ThemeBundleOrder::where('site_id', $siteId)
            ->where('bundle_id', $this->id)
            ->where('status', ThemeBundleOrder::STATUS_PAID)
            ->exists();
    }

    // Grant licenses for all bundle themes to a site
    public function grantLicensesToSite(int $siteId): int
    {
        $count = 0;
        foreach ($this->theme_ids ?? [] as $themeId) {
            ThemeLicense::grant($siteId, (int) $themeId);
            $count++;
        }
        return $count;
    }
}
