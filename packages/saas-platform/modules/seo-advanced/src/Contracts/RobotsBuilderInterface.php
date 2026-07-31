<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Contracts;

interface RobotsBuilderInterface
{
    public function generate(int $siteId, ?string $sitemapUrl = null): string;
}
