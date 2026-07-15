<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support;

use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionCompilerContract;

final class PassThroughMotionCompiler implements MotionCompilerContract
{
    public function compile(array $normalizedInput): array
    {
        return $normalizedInput['files'] ?? [];
    }
}
