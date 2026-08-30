<?php
namespace App\Services\ThemeBuilder;

use App\Services\DesignSystemService;
use SaaSPlatform\ThemeRuntime\ThemeTokenNormalizer;

/**
 * Phase 2h (ADR-029) — thin saas-platform adapter around the shared
 * ThemeTokenNormalizer: this class's only remaining job is supplying
 * saas-platform's own host-specific platform-level base tokens
 * (DesignSystemService's per-site/reseller override) as the normalizer's
 * lowest-priority fallback layer. The actual global_styles → --tb-*
 * normalization logic is no longer duplicated here — it lives in the
 * shared package so customer-site's runtime produces byte-identical
 * --tb-* values from the same global_styles input.
 */
class GlobalStyleEngine
{
    public function __construct(
        protected DesignSystemService $designSystem,
        protected ThemeTokenNormalizer $normalizer = new ThemeTokenNormalizer(),
    ) {}

    /**
     * Generate :root CSS variables from theme global_styles + platform theme (site override).
     */
    public function getCssVariables(array $globalStyles, ?int $siteId = null): array
    {
        $platform = $this->designSystem->getCssVariables($siteId);

        return $this->normalizer->normalize($globalStyles, $platform);
    }

    /**
     * Render a full <style> block for :root and optional dark mode.
     */
    public function renderCssBlock(array $globalStyles, ?int $siteId = null): string
    {
        $platform = $this->designSystem->getCssVariables($siteId);

        return $this->normalizer->renderCssBlock($globalStyles, $platform);
    }
}
