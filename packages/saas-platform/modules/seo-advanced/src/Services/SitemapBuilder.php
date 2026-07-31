<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\Modules\SeoAdvanced\Contracts\SitemapBuilderInterface;

class SitemapBuilder implements SitemapBuilderInterface
{
    public function generate(array $urls): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        foreach ($urls as $entry) {
            if (empty($entry['loc'])) {
                continue;
            }

            $node = $xml->addChild('url');
            $node->addChild('loc', htmlspecialchars($entry['loc'], ENT_XML1));

            if (!empty($entry['lastmod'])) {
                $node->addChild('lastmod', $entry['lastmod']);
            }
            if (!empty($entry['changefreq'])) {
                $node->addChild('changefreq', $entry['changefreq']);
            }
            if (isset($entry['priority'])) {
                $node->addChild('priority', (string) $entry['priority']);
            }
        }

        return $xml->asXML();
    }
}
