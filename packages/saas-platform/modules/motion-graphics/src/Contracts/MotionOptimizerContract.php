<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts;

interface MotionOptimizerContract
{
    public function optimize(array $compiledFiles): array;
}
