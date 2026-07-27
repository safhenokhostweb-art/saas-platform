<?php

namespace App\Modules\MotionGraphics\Bindings;

use App\Models\User;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;

/**
 * Host-app implementation of PlatformAdminResolverContract. A Platform
 * Admin is an authenticated user with is_admin=true and site_id=null —
 * the same boundary defined in docs/architecture/MOTION_GRAPHICS_OWNERSHIP.md.
 * Fails closed (like the module's own DenyAllPlatformAdminResolver)
 * whenever the integration flag is disabled.
 */
final class PlatformAdminResolverAdapter implements PlatformAdminResolverContract
{
    public function __construct(
        private readonly TenantContextContract $tenantContext,
    ) {}

    public function isPlatformAdmin(mixed $actor): bool
    {
        if (! $this->tenantContext->hasFeature('motion_graphics_integration')) {
            return false;
        }

        return $actor instanceof User
            && $actor->is_admin === true
            && $actor->site_id === null;
    }

    public function actorIdentifier(mixed $actor): string
    {
        if ($actor instanceof User) {
            return 'user:'.$actor->id;
        }

        return 'unknown';
    }
}
