<?php

namespace App\Modules\MotionGraphics\Services;

use App\Models\Site;
use App\Modules\MotionGraphics\Contracts\MotionLicenseGatewayContract;
use App\Modules\MotionGraphics\DTO\CustomerRuntimeAssetEntry;
use App\Modules\MotionGraphics\DTO\CustomerRuntimeManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\RuntimeProjectionDTO;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Illuminate\Support\Carbon;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;

/**
 * Phase 8 (docs/motion-customer-runtime-phase8/CUSTOMER_RUNTIME_ARCHITECTURE.md):
 * builds the customer-safe CustomerRuntimeManifest for one site's active
 * SiteAssetPackage, on demand — no separate persisted "published
 * manifest" table. `SiteAssetPackage.activation_status === ACTIVE`
 * (Phase 3, unchanged) already is the authoritative "an admin approved
 * this for this site" signal, matching task requirement 7's "deployment
 * state READY or ACTIVE" gate without introducing new schema.
 *
 * Never duplicates resolution/projection logic — delegates entirely to
 * MotionRuntimeInstallationService::projectSitePackage() (Phase 4) for
 * the actual asset data, and to MotionLicenseGatewayContract (Phase 6's
 * real gateway) for entitlement. This class only shapes the result into
 * the narrower, versioned, customer-facing contract and computes its
 * checksum.
 */
final class MotionCustomerRuntimeService extends AbstractMotionAdapter
{
    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionLicenseGatewayContract $license,
        private readonly MotionRuntimeInstallationService $runtimeInstallation,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    /**
     * @throws MotionAdapterFailClosedException when the flag is off, the
     *   site is not entitled, or no active package exists for the site —
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

        if ($sitePackage === null) {
            throw new MotionAdapterFailClosedException(
                'no_active_package',
                'No active Motion Graphics package is assigned to this site.'
            );
        }

        $projections = $this->runtimeInstallation->projectSitePackage($sitePackage, $context);

        $assets = array_map(
            fn (RuntimeProjectionDTO $projection): CustomerRuntimeAssetEntry => new CustomerRuntimeAssetEntry(
                assetId: $projection->assetReference->assetId,
                publishedVersion: $projection->publishedVersion,
                optimizedReference: $projection->optimizedReference,
                checksum: (string) ($projection->assetReference->compatibility['checksum'] ?? ''),
                assetType: $projection->assetReference->type,
                themeTokens: $projection->themeTokens,
                placementRules: $projection->placementRules,
                responsiveRules: [],
            ),
            $projections
        );

        $generatedAt = Carbon::now()->toIso8601String();
        $manifestChecksum = $this->computeChecksum($site, $sitePackage, $assets);

        $manifest = new CustomerRuntimeManifest(
            siteReference: (string) $site->id,
            templateReference: $sitePackage->template_reference,
            packageId: (string) $sitePackage->uuid,
            packageVersion: $sitePackage->templateAssetPackage?->package_version ?? 0,
            generatedAt: $generatedAt,
            assets: $assets,
            manifestChecksum: $manifestChecksum,
        );

        $this->auditEvents->record(MotionAuditEvents::CUSTOMER_DEPLOYMENT_PREPARED, [
            'site_id' => $site->id,
            'site_package_id' => $sitePackage->id,
            'manifest_checksum' => $manifestChecksum,
            'asset_count' => count($assets),
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $manifest;
    }

    /**
     * @param list<CustomerRuntimeAssetEntry> $assets
     */
    private function computeChecksum(Site $site, SiteAssetPackage $sitePackage, array $assets): string
    {
        $payload = [
            'schema_version' => CustomerRuntimeManifest::SCHEMA_VERSION,
            'site_reference' => (string) $site->id,
            'template_reference' => $sitePackage->template_reference,
            'package_id' => (string) $sitePackage->uuid,
            'assets' => array_map(fn (CustomerRuntimeAssetEntry $a): array => $a->toArray(), $assets),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
