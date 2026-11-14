<?php

namespace App\Modules\MotionGraphics\Services;

use App\Models\Site;
use App\Modules\MotionGraphics\Contracts\MotionLicenseGatewayContract;
use App\Modules\MotionGraphics\DTO\CustomerRuntimeAssetEntry;
use App\Modules\MotionGraphics\DTO\CustomerRuntimeManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\MotionSiteAssetGrant;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\BespokeAssignedAssets;
use App\Modules\MotionGraphics\Support\CustomerRuntimeAssetEntryFactory;
use App\Modules\MotionGraphics\Support\MotionAssetLicensePolicy;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use App\Modules\MotionGraphics\Support\ReadModelMapper;
use Illuminate\Support\Carbon;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;

/**
 * Phase 8 (docs/motion-customer-runtime-phase8/CUSTOMER_RUNTIME_ARCHITECTURE.md):
 * builds the customer-safe CustomerRuntimeManifest for one site's active
 * SiteAssetPackage, on demand — no separate persisted "published
 * manifest" table. `SiteAssetPackage.activation_status === ACTIVE`
 * (Phase 3, unchanged) already is the authoritative "an admin approved
 * this for this site" signal, matching task requirement 7's "deployment
 * state READY or ACTIVE" gate without introducing new schema.
 *
 * Deliberately does NOT delegate to MotionRuntimeInstallationService::
 * projectSitePackage() / MotionRuntimeProjectionAdapter — both call
 * MotionAuthorizationInterface::authorize(), which requires a real
 * Laravel Auth::user() (an authenticated admin/customer session). This
 * service is reached from the `tenant.api` machine-to-machine channel
 * (license_key + domain, no Laravel user at all — see AUDIT_REPORT.md
 * §2), exactly like every other tenant.api controller
 * (ThemeMarketplaceApiController etc.), none of which go through
 * MotionAuthorizationInterface either. The license/entitlement check
 * below (MotionLicenseGatewayContract, Phase 6's real gateway) *is* the
 * authorization for this channel — requiring a second, user-session-based
 * check on top of it would conflate two different trust models, not add
 * real safety. It still reuses the same provider-neutral read contracts
 * (MotionAssetReadContract, MotionRuntimeProjectionReadContract) and the
 * same ReadModelMapper every other phase uses — no duplicated resolution
 * logic, only the authorization *path* differs.
 */
final class MotionCustomerRuntimeService extends AbstractMotionAdapter
{
    /**
     * ADR-081: `template_reference` of a manifest built for a site that has
     * bespoke assignments but no active package (`package_id` is then '').
     */
    public const BESPOKE_ONLY_TEMPLATE_REFERENCE = 'motion-bespoke';

    /** ADR-086: audit reason of an item skipped because an admin disabled it. */
    public const SKIP_DISABLED = 'asset_disabled';

    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionLicenseGatewayContract $license,
        private readonly MotionAssetReadContract $assets,
        private readonly MotionRuntimeProjectionReadContract $projections,
        private readonly MotionAssetLicensePolicy $licensePolicy,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    /**
     * @throws MotionAdapterFailClosedException when the flag is off, the
     *   site is not entitled, or the site has no active package, no active
     *   bespoke assignment (ADR-081) and no private-asset grant (ADR-086) —
     *   every branch fails closed with a stable code the API controller
     *   maps to a safe "not available" response, never a 500.
     */
    public function buildManifestForSite(Site $site, MotionAdapterContext $context): CustomerRuntimeManifest
    {
        // The caller (a tenant.api controller resolving $site from a
        // license_key, or MotionCustomerActivationService) is the source
        // of tenant truth here, not a subdomain — explicitly establish it
        // exactly like every Phase 6/7 call site does before any
        // fail-closed check runs.
        $this->tenantContext->set($site);
        $this->ensureEnabled();
        $this->assertSiteMatches($context->siteId);

        $entitlement = $this->license->validateEntitlement($context->siteId, 'customer-runtime');
        if (! $entitlement->allowed) {
            throw new MotionAdapterFailClosedException($entitlement->code, $entitlement->reason);
        }

        $sitePackage = SiteAssetPackage::query()
            ->where('site_id', $site->id)
            ->where('activation_status', SiteAssetPackage::STATUS_ACTIVE)
            ->with('items', 'templateAssetPackage')
            ->latest('activated_at')
            ->first();

        $entries = new CustomerRuntimeAssetEntryFactory();
        $assets = [];
        $locked = [];
        foreach ($sitePackage?->items ?? [] as $item) {
            $assetModel = $this->assets->findByCanonicalId($item->asset_id, $item->active_version);
            if ($assetModel === null) {
                throw new MotionAdapterFailClosedException(
                    'asset_not_available',
                    "Asset [{$item->asset_id}] is not currently readable."
                );
            }

            // ADR-086: an admin-disabled motion drops out of the next
            // manifest. Checked before anything that could fail the whole
            // manifest: switching one motion off never breaks a site.
            if (! MotionAssetLicensePolicy::isRuntimeEnabled($assetModel->compatibility)) {
                $this->recordSkipped($site, $item->asset_id, (int) $item->active_version, self::SKIP_DISABLED, $context);
                continue;
            }

            $projection = $this->projections->projectAsset($item->asset_id, $context->siteId);
            if ($projection === null) {
                throw new MotionAdapterFailClosedException(
                    'asset_not_available_for_site',
                    "Asset [{$item->asset_id}] does not have a published runtime projection for this site."
                );
            }

            $projectionDto = ReadModelMapper::toRuntimeProjectionDTO($projection);

            // ADR-078: re-checked on every fetch, not only at activation,
            // so an expired or revoked premium license removes the item
            // from the next manifest without anyone re-activating the
            // package. A skipped item is a smaller manifest, never an error.
            // ADR-086: the same call enforces visibility, so a private item
            // whose grant was revoked leaves too (its lock reason is not a
            // published one, so it is dropped from `locked_assets`).
            $license = $this->licensePolicy->decide($site, $projectionDto->assetReference->compatibility, $item->asset_id);
            if (! $license->allowed) {
                $this->recordSkipped($site, $item->asset_id, (int) $item->active_version, $license->code, $context);
                $locked[] = ['asset_id' => $item->asset_id, 'reason' => $license->code];
                continue;
            }

            $entry = $entries->fromProjection($projectionDto, (int) $site->id);
            if ($entry === null) {
                continue;
            }
            $assets[] = $entry;
        }

        // ADR-086: private motions this site was granted explicitly in the
        // central panel. A grant is a direct assignment, like a bespoke one,
        // so it also opens the runtime for a site without a package.
        $grantedIds = MotionSiteAssetGrant::grantedAssetIds((int) $site->id);

        // ADR-081: bespoke assets assigned to this site ride the same
        // manifest, after the package items (so existing entries keep their
        // positions) and without duplicating an asset the package delivers.
        $bespoke = (new BespokeAssignedAssets($this->assets, $this->projections))
            ->entriesFor((int) $site->id, array_map(fn (CustomerRuntimeAssetEntry $a): string => $a->assetId, $assets));

        if ($sitePackage === null && $bespoke === [] && $grantedIds === []) {
            throw new MotionAdapterFailClosedException(
                'no_active_package',
                'No active Motion Graphics package is assigned to this site.'
            );
        }

        // ADR-086: after the package items, every public library motion the
        // package does not bind (so a motion added to the catalog later
        // reaches every runtime-enabled site without re-activation), then the
        // granted private ones. Package items, even skipped ones, are never
        // re-added here, so a bound version is never overridden.
        $packageIds = [];
        foreach ($sitePackage?->items ?? [] as $item) {
            $packageIds[] = (string) $item->asset_id;
        }
        $bespokeIds = array_map(fn (CustomerRuntimeAssetEntry $a): string => $a->assetId, $bespoke);
        $seen = array_fill_keys([...$packageIds, ...$bespokeIds], true);
        foreach ([...$this->publicCatalogAssetIds(), ...$grantedIds] as $assetId) {
            if (isset($seen[$assetId])) {
                continue;
            }
            $seen[$assetId] = true;
            $entry = $this->catalogEntry($site, $assetId, $entries, $context, $locked);
            if ($entry !== null) {
                $assets[] = $entry;
            }
        }
        $assets = [...$assets, ...$bespoke];

        $templateReference = $sitePackage?->template_reference ?? self::BESPOKE_ONLY_TEMPLATE_REFERENCE;
        $packageId = $sitePackage === null ? '' : (string) $sitePackage->uuid;

        $generatedAt = Carbon::now()->toIso8601String();
        $manifestChecksum = $this->computeChecksum($site, $templateReference, $packageId, $assets);

        $manifest = new CustomerRuntimeManifest(
            siteReference: (string) $site->id,
            templateReference: $templateReference,
            packageId: $packageId,
            packageVersion: $sitePackage?->templateAssetPackage?->package_version ?? 0,
            generatedAt: $generatedAt,
            assets: $assets,
            manifestChecksum: $manifestChecksum,
            lockedAssets: $locked,
        );

        $this->auditEvents->record(MotionAuditEvents::CUSTOMER_DEPLOYMENT_PREPARED, [
            'site_id' => $site->id,
            'site_package_id' => $sitePackage?->id,
            'manifest_checksum' => $manifestChecksum,
            'asset_count' => count($assets),
            ...($bespoke === [] ? [] : ['bespoke_asset_count' => count($bespoke)]),
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $manifest;
    }

    /**
     * ADR-086: one public-catalog or granted asset at its latest published
     * version, through the same read contracts, availability switch and
     * licence policy as a package item. Unlike a package item, a problem
     * here only skips this asset: it was never bound to the site, so it can
     * never fail the manifest.
     *
     * @param list<array{asset_id: string, reason: string}> $locked
     */
    private function catalogEntry(Site $site, string $assetId, CustomerRuntimeAssetEntryFactory $entries, MotionAdapterContext $context, array &$locked): ?CustomerRuntimeAssetEntry
    {
        $assetModel = $this->assets->findByCanonicalId($assetId);
        if ($assetModel === null) {
            return null;
        }
        if (! MotionAssetLicensePolicy::isRuntimeEnabled($assetModel->compatibility)) {
            $this->recordSkipped($site, $assetId, $assetModel->version, self::SKIP_DISABLED, $context);

            return null;
        }

        $projection = $this->projections->projectAsset($assetId, $context->siteId);
        if ($projection === null) {
            return null;
        }
        $projectionDto = ReadModelMapper::toRuntimeProjectionDTO($projection);

        $license = $this->licensePolicy->decide($site, $projectionDto->assetReference->compatibility, $assetId);
        if (! $license->allowed) {
            $this->recordSkipped($site, $assetId, $projectionDto->publishedVersion, $license->code, $context);
            // Public motions are listed in the catalog anyway, so saying one
            // is locked leaks nothing. A private one is never mentioned.
            if (MotionAssetLicensePolicy::visibilityOf($projectionDto->assetReference->compatibility) === MotionGraphic::VISIBILITY_PUBLIC) {
                $locked[] = ['asset_id' => $assetId, 'reason' => $license->code];
            }

            return null;
        }

        return $entries->fromProjection($projectionDto, (int) $site->id);
    }

    /**
     * ADR-086: the public, enabled, published platform library motions, in
     * catalog (publish) order. Only the curated library catalog is "public
     * for everyone"; motions authored elsewhere reach a site through its
     * package as before.
     *
     * @return list<string>
     */
    private function publicCatalogAssetIds(): array
    {
        return MotionGraphic::query()
            ->whereIn('slug', array_keys(MotionLibraryCatalogService::ASSETS))
            ->where('ownership_type', MotionGraphic::OWNERSHIP_GLOBAL)
            ->where('visibility', MotionGraphic::VISIBILITY_PUBLIC)
            ->where('runtime_enabled', true)
            ->whereNotNull('published_version')
            ->orderBy('id')
            ->pluck('uuid')
            ->all();
    }

    private function recordSkipped(Site $site, string $assetId, int $version, string $reason, MotionAdapterContext $context): void
    {
        $this->auditEvents->record(MotionAuditEvents::ASSET_SKIPPED, [
            'site_id' => $site->id,
            'asset_id' => $assetId,
            'version' => $version,
            'reason' => $reason,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);
    }

    /**
     * @param list<CustomerRuntimeAssetEntry> $assets
     */
    private function computeChecksum(Site $site, string $templateReference, string $packageId, array $assets): string
    {
        $payload = [
            'schema_version' => CustomerRuntimeManifest::SCHEMA_VERSION,
            'site_reference' => (string) $site->id,
            'template_reference' => $templateReference,
            'package_id' => $packageId,
            'assets' => array_map(fn (CustomerRuntimeAssetEntry $a): array => $a->toArray(), $assets),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
