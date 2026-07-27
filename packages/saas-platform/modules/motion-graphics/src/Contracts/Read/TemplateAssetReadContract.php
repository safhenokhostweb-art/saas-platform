<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\TemplateAssetResolutionReadModel;

/**
 * Resolves a template's declared asset references against the real
 * catalog, provider-neutral. See
 * docs/motion-read-contracts/TEMPLATE_READ_MODEL.md.
 *
 * The module does not own "template" identity — the caller (the
 * Laravel adapter, on behalf of the SaaS Platform's template selection)
 * supplies the asset references a template declares; this contract only
 * confirms which of them resolve to a real, currently readable
 * published version: Template -> Asset References -> Published
 * Versions.
 */
interface TemplateAssetReadContract
{
    /**
     * @param list<array{asset_id: string, version: int}> $references
     */
    public function resolveReferences(array $references): TemplateAssetResolutionReadModel;
}
