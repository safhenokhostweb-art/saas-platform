<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts;

interface MotionCompilerContract
{
    public function compile(array $normalizedInput): array;
}
