<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetLibraryBrowseContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;

/**
 * Database-backed MotionAssetLibraryBrowseContract. Only ever reads
 * non-hidden MotionGraphic/MotionGraphicVersion attributes — identical
 * safety guarantee to DatabaseMotionAssetReader, whose toReadModel()
 * shape this mirrors field-for-field (source_definition_json,
 * editable_schema_json, ai_metadata_json are never referenced anywhere in
 * this class).
 */
final class DatabaseMotionAssetLibraryBrowseReader implements MotionAssetLibraryBrowseContract
{
    public function browsePublished(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(48, $perPage));

        $query = MotionGraphic::query()
            ->whereNotNull('published_version')
            ->where('ownership_type', MotionGraphic::OWNERSHIP_GLOBAL)
            ->search($filters['search'] ?? null);

        $category = $filters['category'] ?? null;
        if (is_string($category) && $category !== '') {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        $total = (clone $query)->count();

        $motions = $query->with(['category'])
            ->orderByDesc('published_at')
            ->forPage($page, $perPage)
            ->get();

        $items = $motions->map(function (MotionGraphic $motion): AssetReadModel {
            $publishedRecord = $motion->versions()
                ->where('version', $motion->published_version)
                ->where('status', 'published')
                ->first();

            return new AssetReadModel(
                assetId: $motion->uuid,
                version: (int) $motion->published_version,
                type: (string) $motion->asset_type,
                category: $motion->category?->slug,
                compatibility: [],
                checksum: (string) ($publishedRecord->source_checksum ?? ''),
                themeSupport: (bool) (($motion->runtime_configuration ?? [])['supports_theme_tokens'] ?? false),
                name: $motion->name,
            );
        })->values()->all();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }
}
