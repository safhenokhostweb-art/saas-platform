<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Contracts;

interface SchemaBuilderInterface
{
    /**
     * @return array<string, mixed> JSON-LD structure (@context/@type + fields for the site's configured schema_type)
     */
    public function build(int $siteId): array;

    /**
     * @param  array<string, mixed>  $schema  output of build()
     */
    public function renderHtml(array $schema): string;
}
