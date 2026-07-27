<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model;

/**
 * One safe entry in a version's lineage chain — identity and status
 * only, never snapshot content or restore-source detail.
 */
final class LineageEntry
{
    public function __construct(
        public readonly int $version,
        public readonly ?int $parentVersion,
        public readonly string $status,
    ) {}
}
