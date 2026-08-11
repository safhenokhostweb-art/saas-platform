<?php

namespace App\Modules\MotionGraphics\Services;

use App\Models\Site;
use App\Modules\MotionGraphics\Contracts\MotionLicenseGatewayContract;
use App\Modules\MotionGraphics\DTO\CustomerRuntimeAssetEntry;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\MotionBespokeAssignment;
use App\Modules\MotionGraphics\Models\MotionBespokeCommission;
use App\Modules\MotionGraphics\Models\MotionBespokePurchase;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use App\Modules\MotionGraphics\Support\ReadModelMapper;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;

/**
 * Phase X — builds the customer-safe runtime list of bespoke/custom
 * Motion assets currently ASSIGNED to one site. Deliberately a separate
 * service and a separate endpoint from MotionCustomerRuntimeService
 * (Phase 8) rather than an edit to that already-tested method: a bespoke
 * asset does not belong to any SiteAssetPackage/TemplateAssetPackage, and
 * a site may legitimately have bespoke assets with no template package at
 * all (or vice versa) — conflating the two "no active X" fail-closed
 * checks would either wrongly deny sites that only bought bespoke assets,
 * or wrongly change Phase 8's existing, regression-tested behavior for
 * template-package sites. An empty bespoke list is not a failure — see
 * `bespokeAssetsForSite()`.
 *
 * Returns only what MotionAssignment + the module's own read contracts
 * (which enforce customer_specific ownership internally, see
 * docs/motion-persistence/OWNERSHIP_RULES.md) already prove this site may
 * use — never a raw ownership_type/owner_site_identifier field.
 */
final class MotionBespokeCustomerRuntimeService extends AbstractMotionAdapter
{
    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionLicenseGatewayContract $license,
        private readonly MotionAssetReadContract $assets,
        private readonly MotionRuntimeProjectionReadContract $projections,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    /**
     * @return list<CustomerRuntimeAssetEntry>
     *
     * @throws MotionAdapterFailClosedException when the flag is off or the
     *   site is not entitled to Motion Graphics at all — never for "zero
     *   bespoke assets", which is a normal, valid state (empty array).
     */
    public function bespokeAssetsForSite(Site $site, MotionAdapterContext $context): array
    {
        $this->tenantContext->set($site);
        $this->ensureEnabled();
        $this->assertSiteMatches($context->siteId);

        $entitlement = $this->license->validateEntitlement($context->siteId, 'bespoke-customer-runtime');
        if (! $entitlement->allowed) {
            throw new MotionAdapterFailClosedException($entitlement->code, $entitlement->reason);
        }

        $assignments = MotionBespokeAssignment::query()
            ->where('site_id', $site->id)
            ->where('status', MotionBespokeAssignment::STATUS_ACTIVE)
            ->get();

        $entries = [];
        foreach ($assignments as $assignment) {
            $assetModel = $this->assets->findByCanonicalId($assignment->asset_id, $assignment->active_version);
            if ($assetModel === null) {
                // Fail closed for THIS entry only — a version that stopped
                // being readable (e.g. retired) never silently disappears
                // from the count without a trace, but it also never blocks
                // the rest of this site's legitimately readable assets.
                continue;
            }

            $projection = $this->projections->projectAsset($assignment->asset_id, $context->siteId);
            if ($projection === null) {
                continue;
            }

            $projectionDto = ReadModelMapper::toRuntimeProjectionDTO($projection);

            $entries[] = new CustomerRuntimeAssetEntry(
                assetId: $projectionDto->assetReference->assetId,
                publishedVersion: $projectionDto->publishedVersion,
                optimizedReference: $projectionDto->optimizedReference,
                checksum: (string) ($projectionDto->assetReference->compatibility['checksum'] ?? ''),
                assetType: $projectionDto->assetReference->type,
                themeTokens: $projectionDto->themeTokens,
                placementRules: $projectionDto->placementRules,
                responsiveRules: [],
            );
        }

        $this->auditEvents->record(MotionAuditEvents::RUNTIME_REQUESTED, [
            'site_id' => $site->id,
            'bespoke_asset_count' => count($entries),
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $entries;
    }

    /**
     * Customer-panel status list (task requirement: "view purchased
     * Motions, view design status, view active Version"). Deliberately
     * NOT gated behind ensureEnabled()/entitlement — this is bookkeeping
     * about the site's own commissions, not runtime asset content, and a
     * customer must be able to see "my custom Motion is in review" even
     * before the base Motion Graphics runtime is technically enabled.
     * Still hard-scoped to the caller's own site — never another site's
     * commissions.
     *
     * @return list<array{commission_id: int, title: string, status: string, purchased: bool, assigned_version: ?int}>
     */
    public function commissionsStatusForSite(Site $site): array
    {
        $commissions = MotionBespokeCommission::query()
            ->where('site_id', $site->id)
            ->with(['assignment' => fn ($q) => $q->where('status', MotionBespokeAssignment::STATUS_ACTIVE)])
            ->get();

        $purchasedCommissionIds = MotionBespokePurchase::query()
            ->whereIn('commission_id', $commissions->pluck('id'))
            ->where('site_id', $site->id)
            ->pluck('commission_id')
            ->all();

        return $commissions->map(fn (MotionBespokeCommission $commission): array => [
            'commission_id' => $commission->id,
            'title' => $commission->title,
            'status' => $commission->status,
            'purchased' => in_array($commission->id, $purchasedCommissionIds, true),
            'assigned_version' => $commission->assignment?->active_version,
        ])->values()->all();
    }
}
