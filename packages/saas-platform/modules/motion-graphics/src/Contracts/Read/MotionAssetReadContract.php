<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;

/**
 * Canonical asset resolution, provider-neutral. See
 * docs/motion-read-contracts/ASSET_READ_CONTRACT.md. Implementations
 * must never return source_definition, editable_schema, ai_metadata,
 * internal storage paths, or ownership internals.
 */
interface MotionAssetReadContract
{
    /**
     * Resolve a safe reference for one asset. When `$version` is
     * omitted, resolves the published version only (customer-safe
     * default). An explicit `$version` also allows an approved (or,
     * where policy permits, retired/deprecated) version — never a draft
     * or review version.
     */
    public function findByCanonicalId(string $assetId, ?int $version = null): ?AssetReadModel;

    /**
     * The asset's currently published version, or null when the asset
     * does not exist or has never been published.
     */
    public function resolveActiveVersion(string $assetId): ?int;

    /**
     * Whether the given version currently holds the exact expected
     * lifecycle status (one of draft, review, approved, published,
     * retired).
     */
    public function verifyLifecycleState(string $assetId, int $version, string $expectedState): bool;
}
