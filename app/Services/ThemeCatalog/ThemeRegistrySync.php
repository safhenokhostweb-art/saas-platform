<?php

namespace App\Services\ThemeCatalog;

use App\Models\Setting;
use App\Models\Theme;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Post-deploy sync of the active theme registry (ThemeRegistry::autoSyncSlugs()).
 *
 * For each active slug:
 *  - not installed and never synced before: install it through its canonical
 *    source, then give it its first commercial release through the
 *    Mandatory Publish Gate (`theme-release:backfill --slug=`);
 *  - installed but without any release: release it through the same gate;
 *  - installed and released: leave it alone. Its content is never re-seeded,
 *    because the admin may have edited it, and new versions are released by
 *    the admin;
 *  - synced before but now missing: someone deleted it on purpose, so it
 *    is NOT recreated. `theme-catalog:rebuild --slug=` is the deliberate way
 *    back, and the sync adopts the theme again afterwards.
 * Slugs outside the active registry (the purged legacy catalog) are never
 * touched.
 *
 * The record of synced slugs lives in `settings` under TRACKING_KEY. A theme
 * that is already installed when the sync first runs (such as one installed
 * by hand) is adopted, so a later deletion is respected too.
 */
class ThemeRegistrySync
{
    public const TRACKING_KEY = 'theme_registry.synced_slugs';

    public const INSTALLED_AND_RELEASED = 'installed_and_released';
    public const RELEASED = 'released';
    public const UP_TO_DATE = 'up_to_date';
    public const KEPT_REMOVED = 'kept_removed';
    public const NOT_ELIGIBLE = 'released_but_not_eligible';
    public const FAILED = 'failed';

    /**
     * @return list<array{slug: string, action: string, detail: string}>
     */
    public function sync(bool $dryRun = false): array
    {
        $tracked = $this->tracked();
        $results = [];

        foreach (ThemeRegistry::autoSyncSlugs() as $slug) {
            try {
                $results[] = $this->syncOne($slug, $tracked, $dryRun);
            } catch (Throwable $e) {
                $results[] = ['slug' => $slug, 'action' => self::FAILED, 'detail' => mb_substr($e->getMessage(), 0, 300)];
            }
        }

        if (! $dryRun) {
            $this->storeTracked($tracked);
        }

        return $results;
    }

    /** @param list<string> $tracked */
    private function syncOne(string $slug, array &$tracked, bool $dryRun): array
    {
        $theme = $this->master($slug);
        $wasTracked = in_array($slug, $tracked, true);

        if ($theme === null && $wasTracked) {
            return ['slug' => $slug, 'action' => self::KEPT_REMOVED,
                'detail' => "synced before and since removed; not recreated (run theme-catalog:rebuild --slug={$slug} to bring it back)"];
        }

        $installed = false;
        if ($theme === null) {
            if ($dryRun) {
                return ['slug' => $slug, 'action' => self::INSTALLED_AND_RELEASED, 'detail' => '[dry run] would install and release'];
            }
            [$command, $arguments] = ThemeRegistry::sourceFor($slug)['run'];
            Artisan::call($command, $arguments);
            $theme = $this->master($slug);
            if ($theme === null) {
                return ['slug' => $slug, 'action' => self::FAILED, 'detail' => 'canonical source ran but the theme is still missing'];
            }
            // A newly synced theme is never listed in the tenant marketplace;
            // the admin lists it and prices it in «فروشگاه قالب ← Listing».
            ThemeRegistry::applyInstallDefaults($theme);
            $installed = true;
        }

        if (! $wasTracked && ! $dryRun) {
            $tracked[] = $slug;
        }

        if ($theme->releases()->exists()) {
            if ($installed) {
                // A seeder that also created a release: nothing more to do.
                return ['slug' => $slug, 'action' => self::INSTALLED_AND_RELEASED, 'detail' => 'installed'];
            }
            $reasons = $theme->commercialIneligibilityReasons();

            return $reasons === []
                ? ['slug' => $slug, 'action' => self::UP_TO_DATE, 'detail' => 'installed, released and eligible']
                : ['slug' => $slug, 'action' => self::NOT_ELIGIBLE, 'detail' => 'has releases but is not eligible: ' . implode(', ', $reasons)];
        }

        if ($dryRun) {
            return ['slug' => $slug, 'action' => self::RELEASED, 'detail' => '[dry run] would release 1.0.0 through the publish gate'];
        }

        $exit = Artisan::call('theme-release:backfill', ['--slug' => $slug]);
        $output = trim(Artisan::output());
        $theme->refresh();

        if ($exit !== 0 || ! $theme->isCommerciallyEligible()) {
            return ['slug' => $slug, 'action' => self::FAILED,
                'detail' => 'publish gate did not release it: ' . mb_substr(preg_replace('/\s+/', ' ', $output), 0, 300)];
        }

        return ['slug' => $slug, 'action' => $installed ? self::INSTALLED_AND_RELEASED : self::RELEASED,
            'detail' => 'released ' . ($theme->currentRelease?->version ?? '1.0.0') . ' through the publish gate'];
    }

    private function master(string $slug): ?Theme
    {
        return Theme::whereNull('site_id')->where('slug', $slug)->first();
    }

    /** @return list<string> */
    public function tracked(): array
    {
        $decoded = json_decode((string) Setting::get(self::TRACKING_KEY, '[]'), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /** @param list<string> $tracked */
    private function storeTracked(array $tracked): void
    {
        $tracked = array_values(array_unique($tracked));
        sort($tracked);
        Setting::updateOrCreate(['key' => self::TRACKING_KEY], ['value' => json_encode($tracked)]);
    }
}
