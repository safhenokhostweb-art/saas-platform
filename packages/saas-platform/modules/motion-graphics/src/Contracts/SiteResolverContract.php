<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts;

interface SiteResolverContract
{
    public function exists(string $siteIdentifier): bool;

    public function currentSiteIdentifier(): ?string;
}
