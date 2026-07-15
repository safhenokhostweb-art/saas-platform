<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support;

use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionOptimizerContract;

final class PassThroughMotionOptimizer implements MotionOptimizerContract
{
    public function optimize(array $compiledFiles): array
    {
        return $compiledFiles;
    }
}
