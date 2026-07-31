<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Contracts;

interface SeoHeadRendererInterface
{
    /**
     * Phase 18 — Part 8: Customer Runtime entry point. Combines
     * MetaBuilder + SchemaBuilder into the full <head> fragment for one
     * page — the single method a theme needs to call, without ever
     * knowing about the module's internal Meta/Schema split.
     *
     * @param  array<string, mixed>  $overrides  page-specific overrides (title/description/canonical/og), same shape as MetaBuilderInterface::build()
     */
    public function render(int $siteId, array $overrides = []): string;
}
