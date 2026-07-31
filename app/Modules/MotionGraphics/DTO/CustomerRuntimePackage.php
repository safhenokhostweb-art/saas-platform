<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * The narrow, customer-facing output of a deployment preparation (task
 * scope item 4). Deliberately thinner than DeploymentManifest — no
 * template/package/checksum bookkeeping, only what a customer runtime
 * would ever need to render.
 *
 * Allowed: published assets, optimized references, theme tokens,
 * placement rules — exactly the RuntimeProjectionDTO allowlist (Phase
 * 1/2), reused unchanged.
 *
 * Forbidden: editing data, source definitions, ownership data, approval
 * data — never present, by construction: this class holds nothing but a
 * list of already-allowlisted RuntimeProjectionDTO values. See
 * docs/motion-deployment-preparation/RUNTIME_PACKAGE_MODEL.md.
 */
final class CustomerRuntimePackage
{
    /**
     * @param list<RuntimeProjectionDTO> $assets
     */
    public function __construct(
        public readonly array $assets,
    ) {}
}
