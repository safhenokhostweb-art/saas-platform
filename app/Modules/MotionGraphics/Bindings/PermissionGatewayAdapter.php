<?php

namespace App\Modules\MotionGraphics\Bindings;

use App\Models\User;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPermissionGatewayContract;

/**
 * Host-app implementation of MotionPermissionGatewayContract, enforcing
 * docs/motion-saas-integration/AUTHORIZATION_MATRIX.md: Platform Admin may
 * exercise every `motion.*` ability; a site-bound Customer may only
 * consume runtime output (`motion.view`, `motion.use`). Anonymous or
 * unrecognized actors are denied. Fails closed while the integration flag
 * is disabled.
 */
final class PermissionGatewayAdapter implements MotionPermissionGatewayContract
{
    private const CUSTOMER_ABILITIES = ['motion.view', 'motion.use'];

    public function __construct(
        private readonly TenantContextContract $tenantContext,
    ) {}

    public function allows(mixed $actor, string $ability): bool
    {
        if (! $this->tenantContext->hasFeature('motion_graphics_integration')) {
            return false;
        }

        if (! $actor instanceof User) {
            return false;
        }

        if ($actor->is_admin === true && $actor->site_id === null) {
            return true;
        }

        return $actor->site_id !== null && in_array($ability, self::CUSTOMER_ABILITIES, true);
    }
}
