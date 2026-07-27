<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model;

/**
 * Safe lifecycle state for one asset version. Never carries snapshot
 * content, checksums-as-secrets, approval actor identity, or change
 * descriptions — only the identity and status needed to make a lifecycle
 * decision at the boundary.
 */
final class VersionReadModel
{
    public function __construct(
        public readonly string $assetId,
        public readonly int $version,
        public readonly string $status,
        public readonly ?int $parentVersion,
        public readonly bool $isDeprecated,
    ) {}
}
