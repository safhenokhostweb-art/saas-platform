<?php

namespace App\Modules\MotionGraphics\Contracts;

use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\OwnershipDecision;

/**
 * Laravel-side mirror of `MotionAuthorizationContract` from
 * docs/motion-saas-integration/LARAVEL_ADAPTER_CONTRACT.md. `actorId` and
 * `siteId` are opaque, caller-supplied identifiers; implementations must
 * re-derive the trusted actor/site from the platform's own authentication
 * and TenantContext rather than trusting these parameters directly — see
 * docs/motion-saas-integration/AUTHORIZATION_MATRIX.md ("Role possession
 * alone is insufficient").
 */
interface MotionAuthorizationInterface
{
    public function authorize(string $actorId, string $ability, string $resourceRef, string $siteId): AuthorizationDecision;

    public function validateOwnership(string $resourceRef, string $expectedOwnerSiteId, MotionAdapterContext $context): OwnershipDecision;

    public function validateTenantAccess(string $siteId, string $resourceRef, MotionAdapterContext $context): AuthorizationDecision;
}
