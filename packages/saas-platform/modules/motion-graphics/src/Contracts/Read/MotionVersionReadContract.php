<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\VersionReadModel;

/**
 * Version resolution and lineage, provider-neutral. See
 * docs/motion-read-contracts/VERSION_READ_MODEL.md.
 *
 * Rules: published version only for default consumption; rollback
 * always creates a new version rather than rewriting history; a retired
 * ("deprecated") version stays readable only when
 * `motion-graphics.deprecated_versions_readable` allows it.
 */
interface MotionVersionReadContract
{
    public function resolveVersion(string $assetId, int $version): ?VersionReadModel;

    public function resolvePublishedVersion(string $assetId): ?VersionReadModel;

    /**
     * The version chain for one asset, oldest first. Only versions
     * readable under current policy are included (draft/review are
     * always excluded; retired is excluded unless policy allows it).
     *
     * @return list<\SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\LineageEntry>
     */
    public function lineage(string $assetId): array;
}
