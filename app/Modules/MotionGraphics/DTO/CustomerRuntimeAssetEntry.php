<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Phase 8 (docs/motion-customer-runtime-phase8/MANIFEST_SCHEMA.md): one
 * asset entry inside a CustomerRuntimeManifest. Extends the task's
 * suggested payload with one field, `asset_type` — required because a
 * customer-site renderer cannot safely decide *how* to embed an asset
 * (sandboxed iframe vs. SRI-checked script module vs. plain `<img>`)
 * without knowing its type; omitting it would force the renderer to
 * guess, which is unsafe. Allowed: published runtime asset reference,
 * optimized output reference, approved theme tokens, placement rules,
 * responsive rules, checksum, type. Forbidden (never present, by
 * construction): source definitions, AI metadata, prompts, editable
 * schema, internal storage paths, ownership internals — enforced by
 * ForbiddenFieldGuard exactly like every other Motion DTO since Phase 1.
 *
 * `responsiveRules` defaults to an empty array — no read contract in this
 * codebase has ever modeled per-breakpoint rules (mirrors Phase 4's
 * ResponsiveSupportDecision::notImplemented() honesty: never fabricate
 * data that doesn't exist yet).
 */
final class CustomerRuntimeAssetEntry
{
    /**
     * @param array<string, string> $themeTokens
     * @param array<string, mixed> $placementRules
     * @param array<string, mixed> $responsiveRules
     */
    public function __construct(
        public readonly string $assetId,
        public readonly int $publishedVersion,
        public readonly ?string $optimizedReference,
        public readonly string $checksum,
        public readonly string $assetType,
        public readonly array $themeTokens = [],
        public readonly array $placementRules = [],
        public readonly array $responsiveRules = [],
        public readonly ?string $name = null,
        public readonly ?string $category = null,
        /**
         * ADR-079: the asset's public runtime parameters (what the customer
         * Canvas inspector may let a site owner change, and how). Distinct
         * from — and never derived at runtime from — the encrypted master
         * editable schema.
         *
         * @var list<array<string, mixed>>
         */
        public readonly array $parameters = [],
    ) {
        ForbiddenFieldGuard::assertClean($themeTokens, 'theme_tokens');
        ForbiddenFieldGuard::assertClean($placementRules, 'placement_rules');
        ForbiddenFieldGuard::assertClean($responsiveRules, 'responsive_rules');
        ForbiddenFieldGuard::assertClean($parameters, 'parameters');
    }

    /**
     * @return array{asset_id: string, published_version: int, optimized_reference: ?string, checksum: string, asset_type: string, theme_tokens: array<string,string>, placement_rules: array<string,mixed>, responsive_rules: array<string,mixed>, name: ?string, category: ?string}
     */
    public function toArray(): array
    {
        return [
            'asset_id' => $this->assetId,
            'published_version' => $this->publishedVersion,
            'optimized_reference' => $this->optimizedReference,
            'checksum' => $this->checksum,
            'asset_type' => $this->assetType,
            'theme_tokens' => $this->themeTokens,
            'placement_rules' => $this->placementRules,
            'responsive_rules' => $this->responsiveRules,
            // Picker display metadata only (Theme Builder Motion Picker) —
            // never source/AI/ownership data; see class docblock allowlist.
            'name' => $this->name,
            'category' => $this->category,
            'parameters' => $this->parameters,
        ];
    }
}
