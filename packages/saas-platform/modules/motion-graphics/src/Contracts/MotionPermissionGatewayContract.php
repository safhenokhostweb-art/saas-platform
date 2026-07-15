<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts;

interface MotionPermissionGatewayContract
{
    public function allows(mixed $actor, string $ability): bool;
}
