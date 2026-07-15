<?php

namespace SaaSPlatform\Modules\MotionGraphics\Policies;

use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPermissionGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;

final class MotionGraphicPolicy
{
    public function __construct(
        private readonly PlatformAdminResolverContract $admins,
        private readonly MotionPermissionGatewayContract $permissions,
    ) {}

    public function before(mixed $actor, string $ability): ?bool
    {
        if (! $this->admins->isPlatformAdmin($actor)) {
            return false;
        }

        return $this->permissions->allows($actor, 'motion.'.$ability);
    }

    public function viewAny(mixed $actor): bool { return false; }
    public function view(mixed $actor, MotionGraphic $motion): bool { return false; }
    public function create(mixed $actor): bool { return false; }
    public function update(mixed $actor, MotionGraphic $motion): bool { return false; }
    public function aiEdit(mixed $actor, MotionGraphic $motion): bool { return false; }
    public function publish(mixed $actor, MotionGraphic $motion): bool { return false; }
    public function rollback(mixed $actor, MotionGraphic $motion): bool { return false; }
    public function delete(mixed $actor, MotionGraphic $motion): bool { return false; }
}
