<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts;

use DateTimeImmutable;

interface ClockContract
{
    public function now(): DateTimeImmutable;
}
