<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Contracts;

use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedAudit;

interface SeoAuditorInterface
{
    /**
     * Phase 18-C — Part 4 / Phase 18-D — Part 2 & 3: every parameter after
     * $imageAltTexts is optional and additive — a caller that doesn't pass
     * it (every Phase 18/18-C call site) simply doesn't get that specific
     * check applied, so this stays backward compatible.
     *
     * $titleMin/$titleMax/$descriptionMin/$descriptionMax override the
     * engine's default length thresholds (see SeoAdvancedSetting's
     * configurable per-site columns, Phase 18-D — Part 2); null falls
     * back to the built-in 30-60 / 70-160 convention.
     *
     * $headings is the page's full ordered heading list (H1..H6) for the
     * hierarchy check; $og is the page's OpenGraph tag values; $urlPath
     * is the page's URL path for the URL-structure check.
     *
     * @param  string[]  $imageAltTexts  alt text of every <img> found on the page (empty string entries mean a missing alt)
     * @param  array<int, array{level: int, text?: string}>|null  $headings
     * @param  array{title?: ?string, description?: ?string, image?: ?string}|null  $og
     * @return array{title_length: int, description_length: int, missing_meta: string[], missing_alt_images: string[], issues: array, score: int}
     */
    public function audit(
        ?string $title,
        ?string $description,
        array $imageAltTexts = [],
        ?string $heading = null,
        ?string $canonical = null,
        ?int $titleMin = null,
        ?int $titleMax = null,
        ?int $descriptionMin = null,
        ?int $descriptionMax = null,
        ?array $headings = null,
        ?array $og = null,
        ?string $urlPath = null,
    ): array;

    /**
     * Phase 18-C — Part 4: site-level Technical checks (robots/schema
     * configured; sitemap always available once this module is
     * installed) — distinct from the page-level checks above.
     *
     * @return array{robots_configured: bool, schema_configured: bool, sitemap_available: bool}
     */
    public function technicalChecks(int $siteId): array;

    /**
     * @param  array<int, array{level: int, text?: string}>|null  $headings
     * @param  array{title?: ?string, description?: ?string, image?: ?string}|null  $og
     */
    public function auditAndStore(
        int $siteId,
        string $pageUrl,
        ?string $title,
        ?string $description,
        array $imageAltTexts = [],
        ?string $heading = null,
        ?string $canonical = null,
        ?array $headings = null,
        ?array $og = null,
        ?string $urlPath = null,
    ): SeoAdvancedAudit;
}
