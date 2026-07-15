<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support;

use DateTimeImmutable;
use SaaSPlatform\Modules\MotionGraphics\Contracts\ClockContract;

final class SystemClock implements ClockContract
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
