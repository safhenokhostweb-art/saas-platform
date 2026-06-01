<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps a tenant site to its chosen ThemeProfile.
 * One row per site. Delete to revert to platform default.
 */
class TenantThemeOverride extends Model
{
    protected $fillable = ['site_id', 'theme_profile_id'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function themeProfile(): BelongsTo
    {
        return $this->belongsTo(ThemeProfile::class);
    }

    public static function forSite(int $siteId): ?ThemeProfile
    {
        $override = self::where('site_id', $siteId)->with('themeProfile')->first();
        return $override?->themeProfile;
    }

    public static function setForSite(int $siteId, int $themeProfileId): self
    {
        return self::updateOrCreate(
            ['site_id' => $siteId],
            ['theme_profile_id' => $themeProfileId]
        );
    }

    public static function removeForSite(int $siteId): void
    {
        self::where('site_id', $siteId)->delete();
    }
}
