<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\Modules\SeoAdvanced\Contracts\RobotsBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;

class RobotsBuilder implements RobotsBuilderInterface
{
    public function generate(int $siteId, ?string $sitemapUrl = null): string
    {
        $settings = SeoAdvancedSetting::where('site_id', $siteId)->first();
        $robots = $settings?->robots_directive ?? 'index,follow';

        $disallowAll = str_contains($robots, 'noindex');

        $lines = ['User-agent: *'];
        $lines[] = $disallowAll ? 'Disallow: /' : 'Allow: /';

        if ($sitemapUrl) {
            $lines[] = '';
            $lines[] = "Sitemap: {$sitemapUrl}";
        }

        return implode("\n", $lines) . "\n";
    }
}
