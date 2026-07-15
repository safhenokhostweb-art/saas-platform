<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support;

use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPreviewGeneratorContract;

final class NullMotionPreviewGenerator implements MotionPreviewGeneratorContract
{
    public function generate(array $source, array $assets, string $versionPath): array
    {
        return ['status' => 'not-generated', 'version_path' => $versionPath];
    }
}
