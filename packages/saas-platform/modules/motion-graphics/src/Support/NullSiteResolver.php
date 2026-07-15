<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support;

use SaaSPlatform\Modules\MotionGraphics\Contracts\SiteResolverContract;

final class NullSiteResolver implements SiteResolverContract
{
    public function exists(string $siteIdentifier): bool
    {
        return false;
    }

    public function currentSiteIdentifier(): ?string
    {
        return null;
    }
}
