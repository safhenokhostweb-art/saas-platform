<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts;

interface MotionPreviewGeneratorContract
{
    public function generate(array $source, array $assets, string $versionPath): array;
}
