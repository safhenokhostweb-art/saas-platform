<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Contracts;

interface MetaBuilderInterface
{
    /**
     * @param  array<string, mixed>  $overrides  page-specific overrides (title/description/canonical/og) layered on top of the site's saved defaults
     * @return array{title: ?string, description: ?string, keywords: ?string, robots: string, canonical: ?string, og: array{title: ?string, description: ?string, image: ?string}}
     */
    public function build(int $siteId, array $overrides = []): array;

    /**
     * @param  array<string, mixed>  $meta  output of build()
     */
    public function renderHtml(array $meta): string;
}
