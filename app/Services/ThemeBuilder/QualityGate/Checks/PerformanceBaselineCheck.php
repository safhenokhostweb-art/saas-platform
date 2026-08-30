<?php
namespace App\Services\ThemeBuilder\QualityGate\Checks;

use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;

/**
 * HEURISTIC by nature, and labeled as such — this is not Lighthouse, no
 * Core Web Vitals are measured (nothing in this codebase can), and it is
 * never presented as a real performance score. What it measures is real:
 * the actual byte size and block count of the theme's own rendered HTML.
 * The threshold that turns that real measurement into a warning is a
 * judgment call, not a proof — hence WARNING severity only, never blocking.
 */
class PerformanceBaselineCheck implements ThemeQualityCheck
{
    private const CATEGORY = 'performance_baseline';
    private const LARGE_PAGE_BYTES = 500_000;
    private const MANY_BLOCKS_THRESHOLD = 150;

    public function __construct(private readonly BlockRenderer $renderer)
    {
    }

    public function run(Theme $theme, string $version, ?array $dependencies): array
    {
        $results = [];

        foreach (($theme->pages ?? []) as $pageKey => $page) {
            $blocks = $page['blocks'] ?? [];
            $html = $this->renderer->renderBlocks($blocks);
            $bytes = strlen($html);
            $blockCount = $this->countBlocksRecursive($blocks);

            if ($bytes > self::LARGE_PAGE_BYTES) {
                $results[] = ThemeQualityCheckResult::warn(
                    self::CATEGORY,
                    'performance.large_page_weight',
                    "Page \"{$pageKey}\" renders to {$bytes} bytes of HTML — heuristic threshold is " . self::LARGE_PAGE_BYTES . ' bytes.',
                    ['page' => $pageKey, 'bytes' => $bytes]
                );
            }

            if ($blockCount > self::MANY_BLOCKS_THRESHOLD) {
                $results[] = ThemeQualityCheckResult::warn(
                    self::CATEGORY,
                    'performance.high_block_count',
                    "Page \"{$pageKey}\" has {$blockCount} blocks — heuristic threshold is " . self::MANY_BLOCKS_THRESHOLD . '.',
                    ['page' => $pageKey, 'block_count' => $blockCount]
                );
            }
        }

        if (empty($results)) {
            $results[] = ThemeQualityCheckResult::pass(self::CATEGORY, 'performance.within_heuristic_bounds', 'Rendered page weight and block counts are within heuristic bounds. Not a real performance measurement (no Lighthouse/Core Web Vitals capability exists in this codebase).');
        }

        return $results;
    }

    private function countBlocksRecursive(array $blocks): int
    {
        $count = 0;
        foreach ($blocks as $block) {
            $count++;
            $children = $block['children'] ?? $block['blocks'] ?? ($block['props']['blocks'] ?? []);
            if (is_array($children)) {
                $count += $this->countBlocksRecursive($children);
            }
        }
        return $count;
    }
}
