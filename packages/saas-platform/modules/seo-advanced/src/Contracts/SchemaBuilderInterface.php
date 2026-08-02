<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Contracts;

interface SchemaBuilderInterface
{
    /**
     * @return array<string, mixed> JSON-LD structure (@context/@type + fields for the site's configured schema_type)
     */
    public function build(int $siteId): array;

    /**
     * Phase 18-E — Part 5: Dynamic, Module-friendly schema construction —
     * any caller (this module's own admin UI, or a future module like a
     * shop/blog) supplies $type + raw $data and gets back valid JSON-LD,
     * without SEO Advanced needing to know what a "Product" or "Article"
     * even is. See SchemaBuilder::SUPPORTED_TYPES for the full list
     * (Organization, LocalBusiness, WebSite, Product, Article, FAQPage,
     * HowTo, Event, Review).
     *
     * @param  array<string, mixed>  $data  type-specific fields — see SchemaBuilder's per-type builder methods for what each type reads
     * @return array<string, mixed>  empty array if $type is unsupported or required fields are missing
     */
    public function buildType(string $type, array $data): array;

    /**
     * @param  array<string, mixed>  $schema  output of build()
     */
    public function renderHtml(array $schema): string;
}
