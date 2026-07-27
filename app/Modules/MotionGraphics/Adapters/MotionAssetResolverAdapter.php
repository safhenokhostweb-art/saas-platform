<?php

namespace App\Modules\MotionGraphics\Adapters;

use App\Modules\MotionGraphics\Contracts\MotionAssetResolverInterface;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\CompatibilityDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use App\Modules\MotionGraphics\Support\ReadModelMapper;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;

/**
 * Laravel-side implementation of `MotionAssetResolverInterface`. Delegates
 * canonical resolution to the standalone module's own
 * `MotionAssetReadContract` (docs/motion-read-contracts/ASSET_READ_CONTRACT.md)
 * instead of querying module Eloquent internals directly — the module
 * remains the source of truth, and only published/approved data ever
 * crosses the boundary.
 */
final class MotionAssetResolverAdapter extends AbstractMotionAdapter implements MotionAssetResolverInterface
{
    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionAuthorizationInterface $authorization,
        private readonly MotionAssetReadContract $assets,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    public function resolveCanonical(string $assetId, int $version, MotionAdapterContext $context): MotionAssetReferenceDTO
    {
        $this->ensureEnabled();

        $decision = $this->authorization->authorize($context->actorId, 'motion.view', $assetId, $context->siteId);
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }

        $asset = $this->assets->findByCanonicalId($assetId, $version);
        if ($asset === null) {
            throw new MotionAdapterFailClosedException(
                'asset_not_available',
                'The requested asset version does not exist or is not currently readable.'
            );
        }

        $this->auditEvents->record(MotionAuditEvents::ASSET_RESOLVED, [
            'asset_id' => $assetId,
            'version' => $version,
            'actor' => $context->actorId,
            'site_id' => $context->siteId,
            'correlation_id' => $context->correlationId,
        ]);

        return ReadModelMapper::toAssetReferenceDTO($asset);
    }

    public function validateCompatibility(
        MotionAssetReferenceDTO $asset,
        array $templateRequirement,
        array $siteProfile,
    ): CompatibilityDecision {
        if (! $this->tenantContext->hasFeature(self::FEATURE_FLAG)) {
            return CompatibilityDecision::incompatible('motion_adapter_disabled', 'The Motion Graphics adapter is disabled.');
        }

        foreach ($templateRequirement as $dimension => $expected) {
            $actual = $asset->compatibility[$dimension] ?? null;
            if ($actual !== $expected) {
                return CompatibilityDecision::incompatible(
                    'compatibility_mismatch',
                    "Asset does not satisfy the template requirement for [{$dimension}]."
                );
            }
        }

        return CompatibilityDecision::compatible();
    }
}
