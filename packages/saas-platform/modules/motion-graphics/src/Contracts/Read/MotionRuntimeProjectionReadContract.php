<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;

/**
 * Customer-safe runtime projection, provider-neutral. See
 * docs/motion-read-contracts/RUNTIME_PROJECTION_CONTRACT.md.
 */
interface MotionRuntimeProjectionReadContract
{
    /**
     * Build the published-only runtime projection for one asset.
     * `$siteIdentifier` must be supplied for a customer-specific asset —
     * a mismatch (or a missing identifier for a customer-specific asset)
     * returns null rather than leaking another tenant's asset. A global
     * asset accepts any (or no) site identifier.
     */
    public function projectAsset(string $assetId, ?string $siteIdentifier = null): ?RuntimeProjectionReadModel;
}
