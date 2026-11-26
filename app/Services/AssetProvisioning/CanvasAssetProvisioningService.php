<?php

namespace App\Services\AssetProvisioning;

use App\Models\CanvasButtonPack;
use App\Models\CanvasHeaderPreset;
use App\Models\CanvasIconPack;
use App\Models\Site;
use App\Models\SiteAssetGrant;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;

/**
 * Unified Provisioning Service for all 4 Canvas Asset Types:
 * Motions, Headers, Button Packs, and Icon Packs.
 *
 * Connects directly to `site_asset_grants` and coordinates grant/revoke
 * actions for single and bulk site allocations, providing immediate
 * propagation to `SiteAssetEntitlements` and the `/api/asset-entitlements` endpoint.
 */
class CanvasAssetProvisioningService
{
    public function __construct(
        protected ActivityLoggerInterface $logger,
        protected SiteAssetEntitlementService $entitlementService,
    ) {}

    /**
     * Grants a specific asset to a single site.
     */
    public function grantToSite(
        Site $site,
        string $assetKind,
        string $assetKey,
        ?string $version = null,
        string $source = SiteAssetGrant::SOURCE_ADMIN_GRANT,
        ?string $reference = null,
        ?int $adminId = null,
    ): SiteAssetGrant {
        if (!in_array($assetKind, AssetRef::KINDS, true)) {
            throw new \InvalidArgumentException("Unsupported asset kind [{$assetKind}].");
        }

        $now = now();
        $grant = SiteAssetGrant::updateOrCreate(
            ['site_id' => $site->id, 'asset_kind' => $assetKind, 'asset_key' => $assetKey],
            [
                'status' => SiteAssetGrant::STATUS_ACTIVE,
                'source' => $source,
                'reference' => $reference,
                'granted_version' => $version,
                'granted_by' => $adminId ?: null,
                'granted_at' => $now,
                'revoked_by' => null,
                'revoked_at' => null,
            ]
        );

        $this->logger->log(SiteAssetEntitlementService::EVENT_GRANTED, 'Canvas asset granted', $grant, [
            'site_id' => $site->id,
            'asset_ref' => AssetRef::make($assetKind, $assetKey),
            'version' => $version,
            'source' => $source,
            'admin_id' => $adminId,
        ]);

        return $grant;
    }

    /**
     * Grants multiple assets to a single site in one transaction.
     *
     * @param list<string> $assetRefs Format: "<kind>:<key>"
     */
    public function grantMultipleAssetsToSite(
        Site $site,
        array $assetRefs,
        ?string $version = null,
        string $source = SiteAssetGrant::SOURCE_ADMIN_GRANT,
        ?int $adminId = null,
    ): array {
        return DB::transaction(function () use ($site, $assetRefs, $version, $source, $adminId) {
            $results = [];
            foreach ($assetRefs as $ref) {
                $parsed = AssetRef::parse($ref);
                if ($parsed !== null) {
                    [$kind, $key] = $parsed;
                    $results[] = $this->grantToSite($site, $kind, $key, $version, $source, null, $adminId);
                }
            }
            return $results;
        });
    }

    /**
     * Grants an asset to multiple sites simultaneously (bulk allocation).
     *
     * @param list<int> $siteIds
     */
    public function grantToMultipleSites(
        array $siteIds,
        string $assetKind,
        string $assetKey,
        ?string $version = null,
        string $source = SiteAssetGrant::SOURCE_ADMIN_GRANT,
        ?string $reference = null,
        ?int $adminId = null,
    ): int {
        return DB::transaction(function () use ($siteIds, $assetKind, $assetKey, $version, $source, $reference, $adminId) {
            $count = 0;
            $sites = Site::query()->whereIn('id', $siteIds)->get();
            foreach ($sites as $site) {
                $this->grantToSite($site, $assetKind, $assetKey, $version, $source, $reference, $adminId);
                $count++;
            }
            return $count;
        });
    }

    /**
     * Revokes access to an asset from a single site.
     */
    public function revokeFromSite(
        Site $site,
        string $assetKind,
        string $assetKey,
        ?int $adminId = null,
    ): bool {
        $grant = SiteAssetGrant::query()
            ->where('site_id', $site->id)
            ->where('asset_kind', $assetKind)
            ->where('asset_key', $assetKey)
            ->first();

        if ($grant === null || !$grant->isActive()) {
            return false;
        }

        $grant->update([
            'status' => SiteAssetGrant::STATUS_REVOKED,
            'revoked_by' => $adminId ?: null,
            'revoked_at' => now(),
        ]);

        $this->logger->log(SiteAssetEntitlementService::EVENT_REVOKED, 'Canvas asset revoked', $grant, [
            'site_id' => $site->id,
            'asset_ref' => AssetRef::make($assetKind, $assetKey),
            'admin_id' => $adminId,
        ]);

        return true;
    }

    /**
     * Revokes an asset from multiple sites in bulk.
     *
     * @param list<int> $siteIds
     */
    public function revokeFromMultipleSites(
        array $siteIds,
        string $assetKind,
        string $assetKey,
        ?int $adminId = null,
    ): int {
        return DB::transaction(function () use ($siteIds, $assetKind, $assetKey, $adminId) {
            $count = 0;
            $sites = Site::query()->whereIn('id', $siteIds)->get();
            foreach ($sites as $site) {
                if ($this->revokeFromSite($site, $assetKind, $assetKey, $adminId)) {
                    $count++;
                }
            }
            return $count;
        });
    }

    /**
     * Checks if a site holds an active grant for the specified asset.
     */
    public function isAssetGrantedToSite(Site $site, string $assetKind, string $assetKey): bool
    {
        return SiteAssetGrant::query()
            ->where('site_id', $site->id)
            ->where('asset_kind', $assetKind)
            ->where('asset_key', $assetKey)
            ->active()
            ->exists();
    }

    /**
     * Returns all active granted asset references for a site, keyed by kind.
     */
    public function getSiteGrantedAssets(Site $site): array
    {
        $grants = SiteAssetGrant::query()
            ->where('site_id', $site->id)
            ->active()
            ->get();

        $grouped = [];
        foreach (AssetRef::KINDS as $kind) {
            $grouped[$kind] = [];
        }

        foreach ($grants as $g) {
            $grouped[$g->asset_kind][] = [
                'key' => $g->asset_key,
                'ref' => $g->ref(),
                'version' => $g->granted_version,
                'source' => $g->source,
                'granted_at' => $g->granted_at?->toIso8601String(),
            ];
        }

        return $grouped;
    }
}
