<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * The allowlisted, published-only read model a customer site is permitted
 * to receive. See docs/motion-saas-integration/RUNTIME_PROJECTION.md —
 * forbidden fields (source, AI metadata, ownership, credentials, ...) are
 * rejected by ForbiddenFieldGuard rather than merely omitted.
 *
 * `optimizedReference` (added in Phase 2) is the opaque optimized asset
 * URL/reference described in RUNTIME_PROJECTION.md's allowlist; it
 * defaults to null so existing Phase 1 callers remain valid.
 */
class RuntimeProjectionDTO
{
    /**
     * @param array<string, string> $themeTokens
     * @param array<string, mixed> $placementRules
     */
    public function __construct(
        public readonly MotionAssetReferenceDTO $assetReference,
        public readonly int $publishedVersion,
        public readonly array $themeTokens = [],
        public readonly array $placementRules = [],
        public readonly ?string $optimizedReference = null,
    ) {
        ForbiddenFieldGuard::assertClean($themeTokens, 'theme_tokens');
        ForbiddenFieldGuard::assertClean($placementRules, 'placement_rules');
    }
}
