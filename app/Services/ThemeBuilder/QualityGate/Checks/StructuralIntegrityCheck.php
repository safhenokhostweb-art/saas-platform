<?php
namespace App\Services\ThemeBuilder\QualityGate\Checks;

use App\Models\Theme;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;

/**
 * The precondition every other check assumes: the theme has authored,
 * well-shaped content to evaluate at all. Mirrors (but does not replace)
 * PagesAuthoredReadinessCheck's minimum bar — this check is broader and
 * belongs to the standalone commercial gate, not the release ledger's own
 * minimum precondition.
 */
class StructuralIntegrityCheck implements ThemeQualityCheck
{
    private const CATEGORY = 'structural_integrity';

    public function run(Theme $theme, string $version, ?array $dependencies): array
    {
        $results = [];
        $pages = $theme->pages ?? [];

        if (empty($pages)) {
            $results[] = ThemeQualityCheckResult::fail(
                self::CATEGORY,
                'structural.no_pages',
                "Theme \"{$theme->slug}\" has no authored pages."
            );
            return $results;
        }

        $anyPageHasBlocks = false;
        foreach ($pages as $key => $page) {
            $blocks = $page['blocks'] ?? [];
            if (!empty($blocks)) {
                $anyPageHasBlocks = true;
            } else {
                $results[] = ThemeQualityCheckResult::warn(
                    self::CATEGORY,
                    'structural.empty_page',
                    "Page \"{$key}\" has no blocks.",
                    ['page' => $key]
                );
            }
            if (empty($page['title'] ?? null)) {
                $results[] = ThemeQualityCheckResult::warn(
                    self::CATEGORY,
                    'structural.page_missing_title',
                    "Page \"{$key}\" has no title.",
                    ['page' => $key]
                );
            }
        }

        if (!$anyPageHasBlocks) {
            $results[] = ThemeQualityCheckResult::fail(
                self::CATEGORY,
                'structural.no_rendered_content',
                "Theme \"{$theme->slug}\" has pages, but none of them contain any blocks — nothing would render."
            );
        }

        foreach (['header_blocks', 'footer_blocks', 'global_styles', 'settings'] as $field) {
            if (!is_array($theme->{$field} ?? null)) {
                $results[] = ThemeQualityCheckResult::fail(
                    self::CATEGORY,
                    'structural.invalid_field_shape',
                    "Theme field \"{$field}\" is not a valid array.",
                    ['field' => $field]
                );
            }
        }

        if (empty($results)) {
            $results[] = ThemeQualityCheckResult::pass(self::CATEGORY, 'structural.ok', 'Structural integrity checks passed.');
        }

        return $results;
    }
}
