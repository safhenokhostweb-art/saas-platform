<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Theme Builder Motion Picker: the safe, displayable summary of one asset,
 * used only to render a visual preview card before an admin/customer
 * confirms a motion_asset_id selection. Composed entirely from
 * AssetReadModel + RuntimeProjectionReadModel fields that already reach
 * the admin/customer boundary elsewhere (name/category via
 * ReadModelMapper's compatibility bag, thumbnailReference via the same
 * optimized_reference every runtime render already uses) — no new read
 * path, no source/AI/ownership data.
 */
final class MotionAssetPreview
{
    public function __construct(
        public readonly string $assetId,
        public readonly ?string $name,
        public readonly ?string $category,
        public readonly string $type,
        public readonly ?string $thumbnailReference,
    ) {}

    /**
     * @return array{asset_id: string, name: ?string, category: ?string, type: string, thumbnail_reference: ?string}
     */
    public function toArray(): array
    {
        return [
            'asset_id' => $this->assetId,
            'name' => $this->name,
            'category' => $this->category,
            'type' => $this->type,
            'thumbnail_reference' => $this->thumbnailReference,
        ];
    }
}
