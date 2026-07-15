<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts;

interface PlatformAdminResolverContract
{
    public function isPlatformAdmin(mixed $actor): bool;

    public function actorIdentifier(mixed $actor): string;
}
