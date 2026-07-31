<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\Modules\SeoAdvanced\Contracts\MetaBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SchemaBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SeoHeadRendererInterface;

class SeoHeadRenderer implements SeoHeadRendererInterface
{
    public function __construct(
        private readonly MetaBuilderInterface $metaBuilder,
        private readonly SchemaBuilderInterface $schemaBuilder,
    ) {}

    public function render(int $siteId, array $overrides = []): string
    {
        $meta = $this->metaBuilder->build($siteId, $overrides);
        $schema = $this->schemaBuilder->build($siteId);

        return trim($this->metaBuilder->renderHtml($meta) . "\n" . $this->schemaBuilder->renderHtml($schema));
    }
}
