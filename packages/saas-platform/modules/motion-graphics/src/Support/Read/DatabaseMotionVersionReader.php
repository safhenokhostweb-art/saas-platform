<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\LineageEntry;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\VersionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionVersionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicVersion;

/**
 * Database-backed MotionVersionReadContract. Only reads version
 * identity/status/lineage columns — never source_snapshot,
 * editable_schema_snapshot, ai_metadata_json, or actor identity.
 */
final class DatabaseMotionVersionReader implements MotionVersionReadContract
{
    public function resolveVersion(string $assetId, int $version): ?VersionReadModel
    {
        $motion = MotionGraphic::query()->where('uuid', $assetId)->first();
        if ($motion === null) {
            return null;
        }

        $record = $motion->versions()->where('version', $version)->first();
        if ($record === null || ! $this->isReadable($record->status)) {
            return null;
        }

        return $this->toReadModel($assetId, $record);
    }

    public function resolvePublishedVersion(string $assetId): ?VersionReadModel
    {
        $motion = MotionGraphic::query()->where('uuid', $assetId)->first();
        if ($motion === null || $motion->published_version === null) {
            return null;
        }

        return $this->resolveVersion($assetId, $motion->published_version);
    }

    public function lineage(string $assetId): array
    {
        $motion = MotionGraphic::query()->where('uuid', $assetId)->first();
        if ($motion === null) {
            return [];
        }

        return $motion->versions()
            ->orderBy('version')
            ->get()
            ->filter(fn (MotionGraphicVersion $record): bool => $this->isReadable($record->status))
            ->map(fn (MotionGraphicVersion $record): LineageEntry => new LineageEntry(
                version: $record->version,
                parentVersion: $record->parent_version,
                status: $record->status,
            ))
            ->values()
            ->all();
    }

    private function isReadable(string $status): bool
    {
        return match ($status) {
            'published', 'approved' => true,
            'retired' => (bool) config('motion-graphics.deprecated_versions_readable', false),
            default => false,
        };
    }

    private function toReadModel(string $assetId, MotionGraphicVersion $record): VersionReadModel
    {
        return new VersionReadModel(
            assetId: $assetId,
            version: $record->version,
            status: $record->status,
            parentVersion: $record->parent_version,
            isDeprecated: $record->status === 'retired',
        );
    }
}
