<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model;

/**
 * Outcome of resolving a template's declared asset references against
 * the real catalog. `unresolved` entries are the caller-supplied asset
 * ids that could not be resolved to a readable version (not found, not
 * published/approved, or lifecycle-blocked) — never an exception, so a
 * template can render a safe fallback for missing assets.
 */
final class TemplateAssetResolutionReadModel
{
    /**
     * @param list<AssetReadModel> $resolved
     * @param list<string> $unresolved
     */
    public function __construct(
        public readonly array $resolved,
        public readonly array $unresolved,
    ) {}
}
