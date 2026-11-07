<?php

namespace App\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\DTO\CustomerRuntimeAssetEntry;
use App\Modules\MotionGraphics\DTO\RuntimeProjectionDTO;

/**
 * ADR-081: the one place a published runtime projection becomes a
 * customer-safe manifest entry. Both the package items and the bespoke
 * assignments of /api/motion-runtime/manifest, and the separate
 * /bespoke-manifest endpoint, go through it, so every entry carries the same
 * fields (name, category, parameters).
 *
 * A private (exclusive) reference is signed for the requesting site here.
 * Public library references pass through byte for byte, so the manifest and
 * checksum of a site with only public motions do not change.
 */
final class CustomerRuntimeAssetEntryFactory
{
    public function __construct(
        private readonly MotionPrivateAssetUrl $urls = new MotionPrivateAssetUrl(),
    ) {}

    /**
     * Returns null when a private reference cannot be signed for this site.
     * Such an entry is withheld rather than shipped with an unusable URL.
     */
    public function fromProjection(RuntimeProjectionDTO $projection, int $siteId): ?CustomerRuntimeAssetEntry
    {
        $reference = $projection->optimizedReference;
        if (MotionPrivateAssetUrl::isPrivateReference($reference)) {
            $reference = $this->urls->sign($reference, $siteId);
            if ($reference === null) {
                return null;
            }
        }

        $compatibility = $projection->assetReference->compatibility;

        return new CustomerRuntimeAssetEntry(
            assetId: $projection->assetReference->assetId,
            publishedVersion: $projection->publishedVersion,
            optimizedReference: $reference,
            checksum: (string) ($compatibility['checksum'] ?? ''),
            assetType: $projection->assetReference->type,
            themeTokens: $projection->themeTokens,
            placementRules: $projection->placementRules,
            responsiveRules: [],
            name: $compatibility['name'] ?? null,
            category: $compatibility['category'] ?? null,
            parameters: $projection->parameters,
        );
    }
}
