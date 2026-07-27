<?php

namespace App\Modules\MotionGraphics\Adapters;

use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\OwnershipDecision;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Illuminate\Support\Facades\Auth;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPermissionGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;

/**
 * The single place that turns a Motion Graphics ability check into an
 * explicit AuthorizationDecision. Other adapters in this namespace
 * delegate here rather than re-implementing authorization — see
 * docs/motion-saas-integration/LARAVEL_ADAPTER_CONTRACT.md
 * (`MotionAuthorizationContract`).
 *
 * `actorId`/`siteId` parameters are the caller's claim; the real actor and
 * tenant are always re-derived from Auth::user() and TenantContext, and a
 * mismatch is denied rather than trusted.
 */
final class MotionAuthorizationAdapter extends AbstractMotionAdapter implements MotionAuthorizationInterface
{
    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly PlatformAdminResolverContract $admins,
        private readonly MotionPermissionGatewayContract $permissions,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    public function authorize(string $actorId, string $ability, string $resourceRef, string $siteId): AuthorizationDecision
    {
        if (! $this->tenantContext->hasFeature(self::FEATURE_FLAG)) {
            return AuthorizationDecision::deny('motion_adapter_disabled', 'The Motion Graphics adapter is disabled.');
        }

        $actor = Auth::user();

        if ($actor === null) {
            return AuthorizationDecision::deny('unauthenticated', 'No authenticated actor is present.');
        }

        if ($this->admins->actorIdentifier($actor) !== $actorId) {
            return AuthorizationDecision::deny('actor_context_mismatch', 'The requested actor does not match the authenticated identity.');
        }

        if (! $this->admins->isPlatformAdmin($actor) && $this->trustedSiteId() !== $siteId) {
            return AuthorizationDecision::deny('tenant_context_mismatch', 'The requested site does not match the authenticated tenant context.');
        }

        if (! $this->permissions->allows($actor, $ability)) {
            return AuthorizationDecision::deny('ability_denied', 'The actor does not hold the requested ability.');
        }

        return AuthorizationDecision::allow();
    }

    public function validateOwnership(string $resourceRef, string $expectedOwnerSiteId, MotionAdapterContext $context): OwnershipDecision
    {
        if (! $this->tenantContext->hasFeature(self::FEATURE_FLAG)) {
            return OwnershipDecision::notOwned('motion_adapter_disabled', 'The Motion Graphics adapter is disabled.');
        }

        $actor = Auth::user();

        if ($actor !== null && $this->admins->isPlatformAdmin($actor)) {
            return OwnershipDecision::owned('platform_admin_scope', 'Platform Admin holds global asset authority.');
        }

        if ($this->trustedSiteId() !== $expectedOwnerSiteId) {
            return OwnershipDecision::notOwned('tenant_isolation_violation', 'Resource ownership does not match the requesting tenant.');
        }

        return OwnershipDecision::owned();
    }

    public function validateTenantAccess(string $siteId, string $resourceRef, MotionAdapterContext $context): AuthorizationDecision
    {
        if (! $this->tenantContext->hasFeature(self::FEATURE_FLAG)) {
            return AuthorizationDecision::deny('motion_adapter_disabled', 'The Motion Graphics adapter is disabled.');
        }

        $actor = Auth::user();

        if ($actor !== null && $this->admins->isPlatformAdmin($actor)) {
            return AuthorizationDecision::allow('platform_admin_scope', 'Platform Admin holds global tenant access.');
        }

        if ($this->trustedSiteId() !== $siteId) {
            return AuthorizationDecision::deny('tenant_context_mismatch', 'The requested site does not match the authenticated tenant context.');
        }

        return AuthorizationDecision::allow();
    }
}
