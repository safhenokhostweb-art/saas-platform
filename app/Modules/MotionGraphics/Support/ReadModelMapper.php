<?php

namespace App\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\DTO\RuntimeProjectionDTO;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;

/**
 * Maps the standalone module's provider-neutral read models (Phase 2)
 * into the Laravel adapter's own DTOs (Phase 1). Kept in one place so
 * every adapter maps identically and so the module's read-model classes
 * never leak past this boundary — see
 * docs/motion-read-contracts/INTEGRATION_UPDATE.md.
 */
final class ReadModelMapper
{
    public static function toAssetReferenceDTO(AssetReadModel $model): MotionAssetReferenceDTO
    {
        return new MotionAssetReferenceDTO(
            assetId: $model->assetId,
            version: $model->version,
            type: $model->type,
            compatibility: [
                ...$model->compatibility,
                'category' => $model->category,
                'checksum' => $model->checksum,
                'theme_support' => $model->themeSupport,
                // Picker display metadata (Theme Builder Motion Picker):
                // 'name' already existed on MotionGraphic/AssetReadModel but
                // was never plumbed past this boundary — same trust tier as
                // 'category' above, added the same way, not a new field on
                // the module's own models.
                'name' => $model->name,
            ],
        );
    }

    public static function toRuntimeProjectionDTO(RuntimeProjectionReadModel $model): RuntimeProjectionDTO
    {
        return new RuntimeProjectionDTO(
            assetReference: self::toAssetReferenceDTO($model->assetReference),
            publishedVersion: $model->publishedVersion,
            themeTokens: $model->themeTokens,
            placementRules: $model->placementRules,
            optimizedReference: $model->optimizedReference,
        );
    }
}
