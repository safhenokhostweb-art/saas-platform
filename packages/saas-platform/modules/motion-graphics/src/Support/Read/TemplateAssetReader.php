<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\TemplateAssetResolutionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\TemplateAssetReadContract;

/**
 * Database-backed TemplateAssetReadContract. Composes
 * MotionAssetReadContract rather than querying models directly — a
 * reference resolves only if the underlying asset/version is currently
 * readable (published or approved).
 */
final class TemplateAssetReader implements TemplateAssetReadContract
{
    public function __construct(
        private readonly MotionAssetReadContract $assets,
    ) {}

    public function resolveReferences(array $references): TemplateAssetResolutionReadModel
    {
        $resolved = [];
        $unresolved = [];

        foreach ($references as $reference) {
            $assetId = $reference['asset_id'] ?? null;
            $version = $reference['version'] ?? null;

            if (! is_string($assetId) || $assetId === '' || ! is_int($version)) {
                $unresolved[] = is_string($assetId) ? $assetId : 'invalid-reference';
                continue;
            }

            $asset = $this->assets->findByCanonicalId($assetId, $version);
            if ($asset === null) {
                $unresolved[] = $assetId;
                continue;
            }

            $resolved[] = $asset;
        }

        return new TemplateAssetResolutionReadModel($resolved, $unresolved);
    }
}
