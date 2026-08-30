<?php
namespace App\Services\ThemeBuilder;

use App\Models\Theme;
use RuntimeException;

/**
 * The current minimum release-readiness rule: a theme with no authored pages
 * cannot render anything on a live customer site (ThemeCanvasPageController
 * requires pages), so it cannot become a commercial release. Deliberately
 * not a full quality gate — see ThemeReleaseReadinessCheck for how future
 * gates (dependencies, rendering, accessibility, performance) are added
 * alongside this one, not in place of it.
 */
class PagesAuthoredReadinessCheck implements ThemeReleaseReadinessCheck
{
    public function check(Theme $theme): void
    {
        if (empty($theme->pages)) {
            throw new RuntimeException(
                "Theme \"{$theme->slug}\" has no authored pages and is not release-ready."
            );
        }
    }
}
