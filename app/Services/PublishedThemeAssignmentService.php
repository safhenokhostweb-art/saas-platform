<?php

namespace App\Services;

use App\Models\PlatformLicense;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\ThemeLicense;
use App\Models\ThemeRelease;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Canonical application boundary for assigning/acquiring a commercial Theme.
 * Direct support installation deliberately does not use this service: it is
 * an operational push workflow, not a Marketplace/customer entitlement.
 */
class PublishedThemeAssignmentService
{
    public function __construct(private readonly LicenseServerService $licenses) {}

    public function assign(
        Site $site,
        Theme $theme,
        string $source = ThemeAssignment::SOURCE_ADMIN,
        ?int $purchaseOrderId = null,
        ?int $assignedBy = null,
        bool $selectForSite = true,
    ): ThemeAssignmentResult {
        return DB::transaction(function () use ($site, $theme, $source, $purchaseOrderId, $assignedBy, $selectForSite) {
            $lockedSite = Site::query()->lockForUpdate()->findOrFail($site->getKey());
            $lockedTheme = Theme::query()
                ->with('currentRelease.qualityReports')
                ->lockForUpdate()
                ->findOrFail($theme->getKey());

            $release = $this->eligibleRelease($lockedTheme);
            $domain = $this->normalizedDomain((string) $lockedSite->domain);
            if ($domain === '') {
                throw new DomainException('برای اختصاص قالب، دامنهٔ معتبر سایت الزامی است.');
            }

            [$platformLicense, $platformCreated] = $this->platformLicenseFor($lockedSite, $domain);
            [$themeLicense, $themeCreated] = $this->themeLicenseFor(
                $lockedSite,
                $lockedTheme,
                $release,
                $purchaseOrderId,
            );

            ThemeAssignment::query()
                ->where('site_id', $lockedSite->id)
                ->where('theme_id', $lockedTheme->id)
                ->where('theme_release_id', '!=', $release->id)
                ->where('status', ThemeAssignment::STATUS_ASSIGNED)
                ->update(['status' => ThemeAssignment::STATUS_SUPERSEDED]);

            $assignment = ThemeAssignment::query()->lockForUpdate()->firstOrNew([
                'site_id' => $lockedSite->id,
                'theme_id' => $lockedTheme->id,
                'theme_release_id' => $release->id,
            ]);
            $assignmentCreated = ! $assignment->exists;
            $assignment->fill([
                'platform_license_id' => $platformLicense->id,
                'theme_license_id' => $themeLicense->id,
                'domain' => $domain,
                'source' => $source,
                'status' => ThemeAssignment::STATUS_ASSIGNED,
                'assigned_by' => $assignedBy,
            ]);
            $assignment->save();

            if ($selectForSite && (int) $lockedSite->theme_id !== (int) $lockedTheme->id) {
                $lockedSite->theme_id = $lockedTheme->id;
                $lockedSite->save();
            }

            DB::afterCommit(fn () => $this->invalidateMarketplaceCache($lockedSite->id, $lockedTheme->id));

            return new ThemeAssignmentResult(
                $assignment->fresh(),
                $platformLicense->fresh(),
                $themeLicense->fresh(),
                $release,
                $platformCreated,
                $themeCreated,
                $assignmentCreated,
            );
        }, 3);
    }

    public function unassign(Site $site, Theme $theme): void
    {
        DB::transaction(function () use ($site, $theme) {
            $lockedSite = Site::query()->lockForUpdate()->findOrFail($site->getKey());
            ThemeAssignment::query()
                ->where('site_id', $lockedSite->id)
                ->where('theme_id', $theme->id)
                ->delete();

            if ((int) $lockedSite->theme_id === (int) $theme->id) {
                $lockedSite->theme_id = null;
                $lockedSite->save();
            }

            // Entitlements are intentionally retained. Removing one
            // assignment must never revoke another Theme or the Site's
            // platform identity.
            DB::afterCommit(fn () => $this->invalidateMarketplaceCache($lockedSite->id, $theme->id));
        }, 3);
    }

    /** Revoke one Theme entitlement without touching any sibling Theme. */
    public function revoke(ThemeLicense $themeLicense): void
    {
        DB::transaction(function () use ($themeLicense) {
            $license = ThemeLicense::query()->lockForUpdate()->findOrFail($themeLicense->getKey());
            $site = Site::query()->lockForUpdate()->findOrFail($license->site_id);

            ThemeAssignment::query()
                ->where('site_id', $license->site_id)
                ->where('theme_id', $license->theme_id)
                ->delete();

            if ((int) $site->theme_id === (int) $license->theme_id) {
                $site->theme_id = null;
                $site->save();
            }

            $themeId = (int) $license->theme_id;
            $siteId = (int) $license->site_id;
            $license->delete();

            DB::afterCommit(fn () => $this->invalidateMarketplaceCache($siteId, $themeId));
        }, 3);
    }

    private function eligibleRelease(Theme $theme): ThemeRelease
    {
        if (! $theme->isPublished()) {
            throw new DomainException('قالب پیش‌نویس قابل اختصاص نیست. ابتدا آن را منتشر کنید.');
        }

        if (! $theme->isCommerciallyEligible()) {
            $reasons = implode(', ', $theme->commercialIneligibilityReasons());
            throw new DomainException('قالب Release منتشرشده و تأییدشده ندارد: ' . $reasons);
        }

        $release = $theme->currentRelease;
        if (! $release || $release->status !== ThemeRelease::STATUS_PUBLISHED || (int) $release->theme_id !== (int) $theme->id) {
            throw new DomainException('Release معتبر و منتشرشده برای این قالب یافت نشد.');
        }

        return $release;
    }

    /** @return array{0: PlatformLicense, 1: bool} */
    private function platformLicenseFor(Site $site, string $domain): array
    {
        $siteKey = trim((string) $site->license_key);
        if ($siteKey !== '') {
            $existing = $this->licenses->findByKey($siteKey);
            if ($existing
                && (int) $existing->site_id === (int) $site->id
                && ! $existing->isRevoked()
                && ! $existing->isExpired()
                && trim((string) $existing->domain_allowed) !== ''
                && $existing->isDomainAllowed($domain)) {
                return [$existing, false];
            }
        }

        // An invalid/revoked/domain-mismatched row remains immutable audit
        // evidence. Issue a fresh domain-bound key instead of overwriting it.
        $key = $this->licenses->issue($site, $domain);
        $license = $this->licenses->findByKey($key);
        if (! $license) {
            throw new DomainException('صدور PlatformLicense ناموفق بود.');
        }

        $site->license_key = $key;
        $site->save();

        return [$license, true];
    }

    /** @return array{0: ThemeLicense, 1: bool} */
    private function themeLicenseFor(Site $site, Theme $theme, ThemeRelease $release, ?int $orderId): array
    {
        $license = ThemeLicense::query()->lockForUpdate()->firstOrNew([
            'site_id' => $site->id,
            'theme_id' => $theme->id,
        ]);
        $created = ! $license->exists;

        if ($created) {
            $license->fill([
                'purchase_order_id' => $orderId,
                'license_key' => Str::upper(Str::random(8)) . '-' . Str::upper(Str::random(8)) . '-' . Str::upper(Str::random(8)),
                'is_permanent' => true,
                'purchased_at' => now(),
            ]);
        } elseif ($orderId !== null && $license->purchase_order_id === null) {
            $license->purchase_order_id = $orderId;
        }

        $license->licensed_release_id = $release->id;
        $license->save();

        return [$license, $created];
    }

    private function normalizedDomain(string $domain): string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return '';
        }

        $candidate = str_contains($domain, '://') ? $domain : 'https://' . $domain;
        $host = parse_url($candidate, PHP_URL_HOST);

        return strtolower(rtrim((string) $host, '.'));
    }

    private function invalidateMarketplaceCache(int $siteId, int $themeId): void
    {
        Cache::forget('theme_marketplace_list');
        Cache::forget("theme_marketplace_detail_{$themeId}");
        Cache::forget("theme_marketplace_licenses_site_{$siteId}");
    }
}
