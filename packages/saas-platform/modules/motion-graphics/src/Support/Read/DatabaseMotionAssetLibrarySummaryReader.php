<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetLibrarySummaryContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;

final class DatabaseMotionAssetLibrarySummaryReader implements MotionAssetLibrarySummaryContract
{
    public function countPublishedAssets(): int
    {
        return MotionGraphic::query()->whereNotNull('published_version')->count();
    }
}
