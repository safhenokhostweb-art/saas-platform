<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model;

/**
 * Safe, allowlisted reference to one readable asset version. Never
 * carries source_definition, editable_schema, ai_metadata, internal
 * storage paths, or ownership internals — readers build this only from
 * non-hidden model attributes, by construction.
 */
final class AssetReadModel
{
    /**
     * @param array<string, mixed> $compatibility
     */
    public function __construct(
        public readonly string $assetId,
        public readonly int $version,
        public readonly string $type,
        public readonly ?string $category,
        public readonly array $compatibility,
        public readonly string $checksum,
        public readonly bool $themeSupport,
        public readonly ?string $name = null,
    ) {}
}
