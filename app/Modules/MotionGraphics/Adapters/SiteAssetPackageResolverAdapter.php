<?php

namespace App\Modules\MotionGraphics\Adapters;

use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\Contracts\SiteAssetPackageResolverInterface;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\OwnershipDecision;
use App\Modules\MotionGraphics\DTO\SiteAssetPackageDTO;
use App\Modules\MotionGraphics\DTO\TemplateAssetRequestDTO;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionVersionReadContract;

/**
 * Laravel-side implementation of `SiteAssetPackageResolverInterface`.
 * Candidate creation is an in-memory definition only — no persistence,
 * per docs/motion-saas-integration/PERSISTENCE_DESIGN.md ("This is a
 * logical model only; no tables or migrations are created"), but every
 * required asset is now confirmed against the standalone module's real
 * `MotionAssetReadContract` before a candidate is built, and lineage
 * delegates to `MotionVersionReadContract`
 * (docs/motion-read-contracts/).
 */
final class SiteAssetPackageResolverAdapter extends AbstractMotionAdapter implements SiteAssetPackageResolverInterface
{
    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionAuthorizationInterface $authorization,
        private readonly MotionAssetReadContract $assets,
        private readonly MotionVersionReadContract $versions,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    public function createCandidate(
        TemplateAssetRequestDTO $templatePackage,
        ?string $brandIntent,
        MotionAdapterContext $context,
    ): SiteAssetPackageDTO {
        $this->ensureEnabled();

        $decision = $this->authorization->authorize($context->actorId, 'motion.use', $templatePackage->templateId, $context->siteId);
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }

        $this->assertSiteMatches($context->siteId);

        foreach ($templatePackage->requiredAssets as $required) {
            if ($this->assets->findByCanonicalId($required->assetId, $required->version) === null) {
                throw new MotionAdapterFailClosedException(
                    'unresolved_required_asset',
                    'A required template asset is not currently readable from the standalone module.'
                );
            }
        }

        $candidate = new SiteAssetPackageDTO(
            siteId: $context->siteId,
            templateId: $templatePackage->templateId,
            resolvedAssets: $templatePackage->requiredAssets,
            brandVariant: $brandIntent,
        );

        $this->auditEvents->record(MotionAuditEvents::PACKAGE_CREATED, [
            'site_id' => $candidate->siteId,
            'template_id' => $candidate->templateId,
            'asset_count' => count($candidate->resolvedAssets),
            'correlation_id' => $context->correlationId,
        ]);

        return $candidate;
    }

    public function validateOwnership(SiteAssetPackageDTO $candidate, string $siteId): OwnershipDecision
    {
        if (! $this->tenantContext->hasFeature(self::FEATURE_FLAG)) {
            return OwnershipDecision::notOwned('motion_adapter_disabled', 'The Motion Graphics adapter is disabled.');
        }

        if ($candidate->siteId !== $siteId) {
            return OwnershipDecision::notOwned('tenant_isolation_violation', 'Package candidate does not belong to the requesting site.');
        }

        return $this->authorization->validateOwnership(
            $candidate->templateId,
            $siteId,
            new MotionAdapterContext(actorId: 'system', siteId: $siteId, correlationId: 'ownership-check'),
        );
    }

    public function lineage(string $packageId, int $version, MotionAdapterContext $context): array
    {
        $this->ensureEnabled();

        $decision = $this->authorization->authorize($context->actorId, 'motion.view', $packageId, $context->siteId);
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }

        // No dedicated Site Asset Package persistence exists yet (see
        // docs/motion-saas-integration/PERSISTENCE_DESIGN.md), so in this
        // phase `$packageId` is the canonical asset id whose version
        // lineage is being requested — the same simplification documented
        // on MotionRuntimeProjectionAdapter::projectPublished().
        return array_map(
            static fn ($entry): array => [
                'version' => $entry->version,
                'parent_version' => $entry->parentVersion,
                'status' => $entry->status,
            ],
            $this->versions->lineage($packageId),
        );
    }
}
