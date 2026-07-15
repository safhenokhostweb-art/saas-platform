<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts;

interface MotionAssetPipelineContract
{
    public function prepare(array $input, string $slug, int $version): array;
}
