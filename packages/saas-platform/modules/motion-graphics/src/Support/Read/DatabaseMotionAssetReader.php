<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicVersion;

/**
 * Database-backed MotionAssetReadContract. Only ever reads non-hidden
 * MotionGraphic/MotionGraphicVersion attributes (engine, asset_type,
 * classification, dimensions, runtime_configuration, checksum, version
 * status) — source_definition_json, editable_schema_json, and
 * ai_metadata_json are never referenced anywhere in this class, so they
 * cannot leak by construction.
 */
final class DatabaseMotionAssetReader implements MotionAssetReadContract
{
    public function findByCanonicalId(string $assetId, ?int $version = null): ?AssetReadModel
    {
        $motion = MotionGraphic::query()->where('uuid', $assetId)->with(['group', 'category', 'tags'])->first();
        if ($motion === null) {
            return null;
        }

        $targetVersion = $version ?? $motion->published_version;
        if ($targetVersion === null) {
            return null;
        }

        $record = $motion->versions()->where('version', $targetVersion)->first();
        if ($record === null || ! $this->isReadable($record->status)) {
            return null;
        }

        return $this->toReadModel($motion, $record);
    }

    public function resolveActiveVersion(string $assetId): ?int
    {
        return MotionGraphic::query()->where('uuid', $assetId)->value('published_version');
    }

    public function verifyLifecycleState(string $assetId, int $version, string $expectedState): bool
    {
        $motion = MotionGraphic::query()->where('uuid', $assetId)->first();
        if ($motion === null) {
            return false;
        }

        $status = $motion->versions()->where('version', $version)->value('status');

        return $status === $expectedState;
    }

    private function isReadable(string $status): bool
    {
        return match ($status) {
            'published', 'approved' => true,
            'retired' => (bool) config('motion-graphics.deprecated_versions_readable', false),
            default => false,
        };
    }

    private function toReadModel(MotionGraphic $motion, MotionGraphicVersion $record): AssetReadModel
    {
        return new AssetReadModel(
            assetId: $motion->uuid,
            version: $record->version,
            type: (string) $motion->asset_type,
            category: $motion->category?->slug,
            compatibility: [
                'engine' => $motion->engine,
                'asset_type' => $motion->asset_type,
                'group' => $motion->group?->slug,
                'category' => $motion->category?->slug,
                'tags' => $motion->tags->pluck('slug')->values()->all(),
            ],
            checksum: (string) $record->source_checksum,
            themeSupport: (bool) (($motion->runtime_configuration ?? [])['supports_theme_tokens'] ?? false),
            name: $motion->name,
        );
    }
}
