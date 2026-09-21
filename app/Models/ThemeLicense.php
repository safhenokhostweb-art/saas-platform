<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Phase 2b final state: `ThemeRelease`/`installed_release_id`/
 * `isOutdatedByLedger()` are the sole commercial version authority. The
 * legacy `Theme.version` int / `installed_theme_version` /
 * ThemeLicense::isOutdated() pair (kept through ADR-021/ADR-022 purely for
 * API compatibility with potentially-unupgraded customer-site deployments)
 * was retired in this phase — the business owner confirmed there are no
 * active customers or production Theme fleet, so that compatibility reason
 * no longer applies. See DECISIONS.md for the ADR amendment and full
 * Phase 1a–2b history.
 */
class ThemeLicense extends Model
{
    protected $fillable = [
        'site_id',
        'theme_id',
        'purchase_order_id',
        'license_key',
        'is_permanent',
        'purchased_at',
        'installed_release_id',
        'licensed_release_id',
    ];

    protected $casts = [
        'is_permanent' => 'boolean',
        'purchased_at' => 'datetime',
    ];

    /**
     * `installed_release_id` is populated from the theme's
     * `current_release_id` at grant time, if any release exists yet.
     * Pre-existing licenses granted before this column existed keep
     * `installed_release_id = null` forever unless explicitly re-granted;
     * this method never back-guesses one for them.
     */
    public static function grant(int $siteId, int $themeId, ?int $orderId = null): self
    {
        $theme = Theme::find($themeId);

        $license = self::firstOrCreate(
            ['site_id' => $siteId, 'theme_id' => $themeId],
            [
                'purchase_order_id'    => $orderId,
                'license_key'          => Str::upper(Str::random(8)) . '-' . Str::upper(Str::random(8)) . '-' . Str::upper(Str::random(8)),
                'is_permanent'         => true,
                'purchased_at'         => now(),
                'installed_release_id' => $theme?->current_release_id,
                'licensed_release_id'  => $theme?->current_release_id,
            ]
        );

        // Backward-compatible low-level callers still receive idempotent
        // release scoping. The canonical assignment workflow performs the
        // eligibility and PlatformLicense checks before reaching this row.
        if ($theme?->current_release_id && (int) $license->licensed_release_id !== (int) $theme->current_release_id) {
            $license->licensed_release_id = $theme->current_release_id;
            $license->save();
        }

        return $license;
    }

    /**
     * The sole outdated-state authority. Returns null — never a guessed
     * true/false — when either side of the comparison isn't known yet (no
     * installed_release_id recorded on this license, or the theme has no
     * current release), so a caller cannot mistake "unknown" for "up to
     * date". A null result must never be presented as "current" or counted
     * as "outdated" — see Theme::outdatedLicensesCountByLedger().
     */
    public function isOutdatedByLedger(): ?bool
    {
        $installedVersion = $this->installedRelease?->version;
        $currentVersion = $this->theme?->currentRelease?->version;

        if ($installedVersion === null || $currentVersion === null) {
            return null;
        }

        return version_compare($installedVersion, $currentVersion, '<');
    }

    public function installedRelease()
    {
        return $this->belongsTo(ThemeRelease::class, 'installed_release_id');
    }

    public function licensedRelease()
    {
        return $this->belongsTo(ThemeRelease::class, 'licensed_release_id');
    }

    public function assignment()
    {
        return $this->hasOne(ThemeAssignment::class, 'theme_license_id')
            ->where('status', ThemeAssignment::STATUS_ASSIGNED)
            ->latestOfMany();
    }

    public function assignments()
    {
        return $this->hasMany(ThemeAssignment::class, 'theme_license_id');
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
