<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support;

use LogicException;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;

final class DenyAllPlatformAdminResolver implements PlatformAdminResolverContract
{
    public function isPlatformAdmin(mixed $actor): bool
    {
        return false;
    }

    public function actorIdentifier(mixed $actor): string
    {
        throw new LogicException('No platform actor resolver is configured.');
    }
}
