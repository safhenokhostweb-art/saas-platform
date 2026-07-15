<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support;

use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPermissionGatewayContract;

final class DenyAllMotionPermissionGateway implements MotionPermissionGatewayContract
{
    public function allows(mixed $actor, string $ability): bool
    {
        return false;
    }
}
