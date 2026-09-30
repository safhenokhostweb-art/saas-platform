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

    /**
     * Grant licenses for every commercially eligible theme in the bundle to
     * a site. Phase 2d (ADR-025): a theme in the bundle that is not
     * currently commercially eligible (no valid current release backed by a
     * passing Commercial Quality Report) is skipped, not granted — the
     * customer already paid for the whole bundle by the time this runs, so
     * failing the entire grant over one bad theme would be worse than
     * granting what is actually eligible and honestly reporting a smaller
     * count; callers already surface the returned count to the customer.
     */
    public function grantLicensesToSite(int $siteId): int
    {
        $count = 0;
        $site = Site::findOrFail($siteId);
        foreach ($this->theme_ids ?? [] as $themeId) {
            $theme = Theme::find((int) $themeId);
            if (!$theme) {
                continue;
            }
            try {
                app(\App\Services\PublishedThemeAssignmentService::class)->assign(
                    $site,
                    $theme,
                    ThemeAssignment::SOURCE_BUNDLE,
                    selectForSite: false,
                );
                $count++;
            } catch (\DomainException) {
                // Preserve the existing bundle behavior: one unavailable
                // Theme does not roll back grants for the other valid items.
            }
        }
        return $count;
    }
}
