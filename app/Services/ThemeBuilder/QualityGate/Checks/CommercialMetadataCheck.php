<?php
namespace App\Services\ThemeBuilder\QualityGate\Checks;

use App\Models\Theme;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;

/**
 * Whether the theme has the catalog/listing information a commercial
 * Marketplace product needs. All warning-level: incomplete marketing copy
 * is a listing-quality problem, not a reason to block a release the way a
 * broken render or missing dependency is — a theme can be a valid, working
 * commercial product while its description still needs editing.
 *
 * Phase 2l (ADR-032 addendum): this check is deliberately about
 * MARKETPLACE MERCHANDISING completeness — a separate concept from both
 * (a) commercial ELIGIBILITY (Theme::isCommerciallyEligible(), fail-closed,
 * unaffected by anything here) and (b) real release-content correctness
 * (structural rendering, required dependencies, contrast — all handled by
 * other, BLOCKING-capable checks). A theme can fail every check here and
 * still be a perfectly valid, installable commercial release; it just
 * won't present well in the catalog yet.
 */
class CommercialMetadataCheck implements ThemeQualityCheck
{
    private const CATEGORY = 'commercial_metadata';

    public function run(Theme $theme, string $version, ?array $dependencies): array
    {
        $results = [];

        if (empty($theme->name)) {
            $results[] = ThemeQualityCheckResult::warn(self::CATEGORY, 'metadata.missing_name', 'Theme has no name.');
        }
        if (empty($theme->category)) {
            $results[] = ThemeQualityCheckResult::warn(self::CATEGORY, 'metadata.missing_category', 'Theme has no category.');
        }
        if (empty($theme->description)) {
            $results[] = ThemeQualityCheckResult::warn(self::CATEGORY, 'metadata.missing_description', 'Theme has no description.');
        }

        if ($theme->is_marketplace) {
            $meta = $theme->marketplaceMeta;
            if (!$meta) {
                $results[] = ThemeQualityCheckResult::warn(
                    self::CATEGORY,
                    'metadata.missing_marketplace_row',
                    'Theme is marked is_marketplace but has no ThemeMarketplace listing row.'
                );
            } else {
                if (empty($meta->screenshots)) {
                    $results[] = ThemeQualityCheckResult::warn(self::CATEGORY, 'metadata.missing_screenshots', 'Marketplace listing has no screenshots.');
                }
                if (empty($meta->marketplace_description)) {
                    $results[] = ThemeQualityCheckResult::warn(self::CATEGORY, 'metadata.missing_marketplace_description', 'Marketplace listing has no marketplace_description (falls back to the internal Theme.description, which may not be customer-facing copy).');
                }
            }
            // Phase 2l: was entirely unchecked — a theme could be
            // commercially eligible and even have a marketplace row while
            // still showing no visual presentation at all in the catalog.
            // Distinct from "screenshots" above: this is the storefront
            // thumbnail specifically (CaptureThemeScreenshotsCommand's
            // real-preview output), not quality-evidence imagery.
            if (empty($theme->thumbnail)) {
                $results[] = ThemeQualityCheckResult::warn(self::CATEGORY, 'metadata.missing_thumbnail', 'Theme has no marketing thumbnail — visually unmarketable in the catalog even if otherwise listable.');
            }
            if (empty($theme->preview_image)) {
                $results[] = ThemeQualityCheckResult::warn(self::CATEGORY, 'metadata.missing_preview_image', 'Theme has no marketing preview image.');
            }
        }

        if (empty($results)) {
            $results[] = ThemeQualityCheckResult::pass(self::CATEGORY, 'metadata.complete', 'Commercial metadata is complete.');
        }

        return $results;
    }
}
