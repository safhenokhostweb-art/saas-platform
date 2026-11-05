<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model;

/**
 * Customer-safe, published-only runtime projection. Never carries draft
 * data, approval history, prompts, or internal metadata — see
 * docs/motion-read-contracts/RUNTIME_PROJECTION_CONTRACT.md.
 */
final class RuntimeProjectionReadModel
{
    /**
     * @param array<string, mixed> $themeTokens
     * @param array<string, mixed> $placementRules
     * @param list<array<string, mixed>> $parameters ADR-079 customer-facing
     *   runtime parameters (MotionSvgComposer::normalizeDefinitions shape);
     *   never the master editable schema.
     */
    public function __construct(
        public readonly AssetReadModel $assetReference,
        public readonly int $publishedVersion,
        public readonly string $optimizedReference,
        public readonly array $themeTokens = [],
        public readonly array $placementRules = [],
        public readonly array $parameters = [],
    ) {}
}
