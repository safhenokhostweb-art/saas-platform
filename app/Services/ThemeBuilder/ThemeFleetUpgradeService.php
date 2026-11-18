<?php

namespace App\Services\ThemeBuilder;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationPackage;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\ThemeFleetUpdate;
use App\Models\ThemeLicense;
use App\Models\ThemeRelease;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Log;

class ThemeFleetUpgradeService
{
    public function __construct(
        private readonly ThemeInstallGateway $gateway,
    ) {}

    /**
     * Discover all updates available across the fleet:
     * - Themes: Sites whose assigned/installed theme release is older than currentRelease
     * - Packages: Sites whose package_version is older than the active CustomerInstallationPackage
     * - Motions: Versioned motion assets with pending updates
     *
     * @return array{
     *     themes: list<array<string, mixed>>,
     *     packages: list<array<string, mixed>>,
     *     motions: list<array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    public function discoverAvailableUpdates(): array
    {
        $themeUpdates = [];
        $activePackage = CustomerInstallationPackage::active();
        $packageUpdates = [];
        $motionUpdates = [];

        // 1. Discover Theme Updates
        $themesWithReleases = Theme::with('currentRelease')->whereNotNull('current_release_id')->get()->keyBy('id');

        $sites = Site::with(['theme', 'themeAssignments.release'])->whereNotNull('domain')->get();

        foreach ($sites as $site) {
            $themeId = $site->theme_id;
            if (!$themeId || !isset($themesWithReleases[$themeId])) {
                continue;
            }

            $theme = $themesWithReleases[$themeId];
            $currentRelease = $theme->currentRelease;
            if (!$currentRelease) {
                continue;
            }

            // Find current installed release for this site
            $latestAssignment = $site->themeAssignments
                ->where('theme_id', $themeId)
                ->where('status', ThemeAssignment::STATUS_ASSIGNED)
                ->sortByDesc('id')
                ->first();

            $installedReleaseId = $latestAssignment?->theme_release_id;
            $installedVersion = $latestAssignment?->release?->version;

            if (!$installedReleaseId) {
                $license = ThemeLicense::where('site_id', $site->id)->where('theme_id', $themeId)->first();
                $installedReleaseId = $license?->installed_release_id;
                if ($installedReleaseId && $installedReleaseId == $currentRelease->id) {
                    $installedVersion = $currentRelease->version;
                }
            }

            $isOutdated = !$installedReleaseId || ($installedReleaseId !== $currentRelease->id);

            if ($isOutdated) {
                $eligibility = $this->checkEligibility($site, $theme, $currentRelease);

                $themeUpdates[] = [
                    'site_id'               => $site->id,
                    'site_title'            => $site->title,
                    'domain'                => $site->domain,
                    'theme_id'              => $theme->id,
                    'theme_name'            => $theme->name,
                    'theme_slug'            => $theme->slug,
                    'distribution_model'    => $theme->distribution_model ?? Theme::DISTRIBUTION_MARKETPLACE,
                    'installed_release_id'  => $installedReleaseId,
                    'installed_version'     => $installedVersion ?? '1.0.0',
                    'available_release_id'  => $currentRelease->id,
                    'available_version'     => $currentRelease->version,
                    'eligible'              => $eligibility['eligible'],
                    'eligibility_reason'    => $eligibility['reason'],
                ];
            }
        }

        // 2. Discover Core Package / Canvas Engine Updates
        $activePackage = CustomerInstallationPackage::active();
        if ($activePackage) {
            $installations = CustomerInstallation::where('status', 'complete')
                ->whereNotNull('site_id')
                ->get();

            foreach ($installations as $inst) {
                $instSite = $sites->firstWhere('id', (int) $inst->site_id) ?? Site::find($inst->site_id);
                if (!$instSite) {
                    continue;
                }

                $currentVer = $inst->package_version ?? '1.0.0';
                if (version_compare($currentVer, $activePackage->version, '<')) {
                    $packageUpdates[] = [
                        'installation_id'   => $inst->id,
                        'site_id'           => $instSite->id,
                        'site_title'        => $instSite->title,
                        'domain'            => $instSite->domain,
                        'current_version'   => $currentVer,
                        'available_version' => $activePackage->version,
                        'release_notes'     => $activePackage->release_notes,
                    ];
                }
            }
        }

        // 3. Discover Motion Graphics Updates
        if (class_exists(\App\Modules\MotionGraphics\Models\MotionSiteAssetGrant::class)
            && \Illuminate\Support\Facades\Schema::hasTable('motion_site_asset_grants')
        ) {
            $activeGrants = \App\Modules\MotionGraphics\Models\MotionSiteAssetGrant::where('status', 'active')
                ->with('site')
                ->get();

            foreach ($activeGrants as $grant) {
                if ($grant->site && !empty($grant->note) && str_contains(strtolower((string) $grant->note), 'update_pending')) {
                    $motionUpdates[] = [
                        'grant_id'    => $grant->id,
                        'site_id'     => $grant->site->id,
                        'site_title'  => $grant->site->title,
                        'domain'      => $grant->site->domain,
                        'asset_key'   => $grant->asset_id,
                        'asset_title' => $grant->asset_id,
                    ];
                }
            }
        }

        return [
            'themes'   => $themeUpdates,
            'packages' => $packageUpdates,
            'motions'  => $motionUpdates,
            'summary'  => [
                'themes_count'   => count($themeUpdates),
                'packages_count' => count($packageUpdates),
                'motions_count'  => count($motionUpdates),
                'total_pending'  => count($themeUpdates) + count($packageUpdates) + count($motionUpdates),
            ],
        ];
    }

    /**
     * Smart Eligibility Guard:
     * - Site's assigned theme must match the target theme
     * - For private_license or priced themes, valid license must exist
     * - Target release must exist and be newer or valid
     */
    public function checkEligibility(Site $site, Theme $theme, ThemeRelease $targetRelease): array
    {
        // Guard 1: Theme Lineage Match
        if ($site->theme_id && (int) $site->theme_id !== (int) $theme->id) {
            return [
                'eligible' => false,
                'reason'   => 'قالب نصب‌شده روی این سایت با قالب هدف انتخابی همخوانی ندارد.',
            ];
        }

        // Guard 2: License Requirement
        $distModel = $theme->distribution_model ?? Theme::DISTRIBUTION_MARKETPLACE;
        $requiresLicense = ($distModel === Theme::DISTRIBUTION_PRIVATE_LICENSE)
            || ($distModel === Theme::DISTRIBUTION_MARKETPLACE && (int) ($theme->marketplace_price ?? 0) > 0);

        if ($requiresLicense) {
            $hasLicense = ThemeLicense::where('site_id', $site->id)
                ->where('theme_id', $theme->id)
                ->exists();

            if (!$hasLicense) {
                return [
                    'eligible' => false,
                    'reason'   => 'سایت فاقد لایسنس معتبر برای این قالب اختصاصی یا تجاری است.',
                ];
            }
        }

        return [
            'eligible' => true,
            'reason'   => 'واجد شرایط دریافت به‌روزرسانی',
        ];
    }

    /**
     * Execute non-destructive fleet update for a single site.
     */
    public function dispatchUpgrade(Site $site, ThemeRelease $release, ?int $userId = null): ThemeFleetUpdate
    {
        $theme = $release->theme;
        if (!$theme) {
            throw new \InvalidArgumentException('قالب مرتبط با این ریلیز یافت نشد.');
        }

        $eligibility = $this->checkEligibility($site, $theme, $release);
        if (!$eligibility['eligible']) {
            throw new \RuntimeException($eligibility['reason']);
        }

        // Determine current release ID before upgrade
        $latestAssignment = ThemeAssignment::where('site_id', $site->id)
            ->where('theme_id', $theme->id)
            ->where('status', ThemeAssignment::STATUS_ASSIGNED)
            ->latest('id')
            ->first();

        $fromReleaseId = $latestAssignment?->theme_release_id;
        if (!$fromReleaseId) {
            $license = ThemeLicense::where('site_id', $site->id)->where('theme_id', $theme->id)->first();
            $fromReleaseId = $license?->installed_release_id;
        }

        // Create audit record
        $fleetUpdate = ThemeFleetUpdate::create([
            'site_id'         => $site->id,
            'theme_id'        => $theme->id,
            'from_release_id' => $fromReleaseId,
            'to_release_id'   => $release->id,
            'status'          => ThemeFleetUpdate::STATUS_RUNNING,
            'dispatched_by'   => $userId,
            'started_at'      => now(),
        ]);

        // Execute server-to-server 3-way merge upgrade
        $result = $this->gateway->upgrade($site, $release);

        if (!$result->success) {
            $fleetUpdate->update([
                'status'         => ThemeFleetUpdate::STATUS_FAILED,
                'failure_reason' => $result->message,
                'completed_at'   => now(),
            ]);

            Log::error('Fleet update failed for site', [
                'site_id'  => $site->id,
                'theme_id' => $theme->id,
                'error'    => $result->message,
            ]);

            return $fleetUpdate;
        }

        // Success: update ThemeAssignment and ThemeLicense
        $payload = $result->body ?? [];

        ThemeAssignment::updateOrCreate(
            [
                'site_id'          => $site->id,
                'theme_id'         => $theme->id,
                'theme_release_id' => $release->id,
            ],
            [
                'domain'      => $site->domain,
                'source'      => ThemeAssignment::SOURCE_ADMIN,
                'status'      => ThemeAssignment::STATUS_ASSIGNED,
                'assigned_by' => $userId,
            ]
        );

        ThemeLicense::where('site_id', $site->id)
            ->where('theme_id', $theme->id)
            ->update([
                'installed_release_id' => $release->id,
                'licensed_release_id'  => $release->id,
            ]);

        $fleetUpdate->update([
            'status'                 => ThemeFleetUpdate::STATUS_COMPLETED,
            'revision_number'        => $payload['revision_number'] ?? null,
            'reconciliation_summary' => $payload['reconciliation_metrics'] ?? ($payload['metrics'] ?? null),
            'completed_at'           => now(),
        ]);
        $fleetUpdate->refresh();

        ActivityLogger::log('fleet.theme_upgraded', "ارتقای غیرمخرب قالب «{$theme->name}» به نسخه {$release->version} روی سایت {$site->domain}", $site, [
            'update_id'        => $fleetUpdate->id,
            'theme_release_id' => $release->id,
            'revision_number'  => $payload['revision_number'] ?? null,
        ]);

        return $fleetUpdate;
    }
}
