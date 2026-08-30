<?php
namespace App\Services\ThemeBuilder\QualityGate\Checks;

use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;
use DOMDocument;
use DOMXPath;

/**
 * Real, DOM-based checks against the theme's actual rendered output for
 * provably broken asset references (an empty/placeholder src is a factual
 * defect, not a judgment call). Catalog-completeness signals (thumbnail,
 * preview image) are warnings — missing marketing imagery is a listing
 * quality issue, not a functional break.
 */
class AssetIntegrityCheck implements ThemeQualityCheck
{
    private const CATEGORY = 'asset_integrity';
    private const BROKEN_SRC_VALUES = ['', 'null', 'undefined', '#'];

    public function __construct(private readonly BlockRenderer $renderer)
    {
    }

    public function run(Theme $theme, string $version, ?array $dependencies): array
    {
        $results = [];

        foreach (($theme->pages ?? []) as $pageKey => $page) {
            $html = $this->renderer->renderBlocks($page['blocks'] ?? []);
            if (trim($html) === '') {
                continue;
            }
            $this->scanForBrokenAssets($html, "page:{$pageKey}", $results);
        }

        if (empty($theme->thumbnail) && empty($theme->preview_image)) {
            $results[] = ThemeQualityCheckResult::warn(
                self::CATEGORY,
                'asset.missing_marketing_image',
                "Theme \"{$theme->slug}\" has neither a thumbnail nor a preview image for the Marketplace listing."
            );
        }

        if (!array_filter($results, fn ($r) => $r->isBlockingFailure())) {
            $results[] = ThemeQualityCheckResult::pass(self::CATEGORY, 'asset.ok', 'No broken asset references detected in rendered output.');
        }

        return $results;
    }

    private function scanForBrokenAssets(string $html, string $location, array &$results): void
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//img') as $img) {
            $src = trim($img->getAttribute('src'));
            if (in_array($src, self::BROKEN_SRC_VALUES, true)) {
                $results[] = ThemeQualityCheckResult::fail(
                    self::CATEGORY,
                    'asset.broken_image_reference',
                    "An <img> in {$location} has a broken/empty src (\"{$src}\")."
                );
            }
        }
    }
}
