<?php
namespace App\Services\ThemeBuilder\QualityGate\Checks;

use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityPreviewUrlFactory;
use App\Services\ThemeBuilder\ReverseEngineering\PlaywrightExtractionService;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Phase 2g — real browser-backed responsive verification. Two genuinely
 * different kinds of evidence, kept honestly separate:
 *
 *   1. A static scan of the theme's actual rendered HTML for fixed pixel
 *      widths with no max-width escape hatch — real, always runs, no
 *      browser needed.
 *   2. Real multi-viewport capture via a real headless browser (Chromium/
 *      Playwright), against a real signed internal preview URL generated
 *      automatically for this theme (ThemeQualityPreviewUrlFactory — the
 *      same production render path CaptureThemeScreenshotsCommand already
 *      uses). No caller needs to supply anything.
 *
 * Two failure modes are deliberately NOT the same severity:
 *   - The Node/Chromium process itself failing to run (crash, timeout,
 *     invalid JSON) is an INFRASTRUCTURE gap — reported UNVERIFIED, never a
 *     failure of the theme. See ADR-028.
 *   - The browser successfully running and observing the theme's own page
 *     fail to render, overflow catastrophically, throw uncaught JS, or fail
 *     to load a required local asset IS real evidence against the theme —
 *     reported as a genuine failure, at BLOCKING severity when the task's
 *     "browser cannot render page" bar is met.
 */
class ResponsiveReadinessCheck implements ThemeQualityCheck
{
    private const CATEGORY = 'responsive_readiness';
    private const VIEWPORTS = ['desktop', 'tablet', 'mobile'];

    /** overflowPx / viewport width beyond this fraction is catastrophic, not minor. */
    private const CATASTROPHIC_OVERFLOW_RATIO = 0.2;

    public function __construct(
        private readonly BlockRenderer $renderer,
        private readonly PlaywrightExtractionService $playwright,
        private readonly ThemeQualityPreviewUrlFactory $previewUrlFactory,
    ) {
    }

    public function run(Theme $theme, string $version, ?array $dependencies): array
    {
        $results = $this->staticFixedWidthScan($theme);

        if (!$this->playwright->isAvailable()) {
            $report = $this->playwright->availabilityReport();
            foreach (self::VIEWPORTS as $viewport) {
                $results[] = ThemeQualityCheckResult::unverified(
                    self::CATEGORY,
                    "responsive.{$viewport}.capture_unavailable",
                    "Headless browser infrastructure is not available in this environment — {$viewport} rendering was not executed by a real browser.",
                    ['availability' => $report]
                );
            }
            return $results;
        }

        $url = $this->previewUrlFactory->make($theme);
        if ($url === null) {
            foreach (self::VIEWPORTS as $viewport) {
                $results[] = ThemeQualityCheckResult::unverified(
                    self::CATEGORY,
                    "responsive.{$viewport}.capture_unavailable",
                    "Theme \"{$theme->slug}\" has no page to generate a preview target from."
                );
            }
            return $results;
        }

        foreach (self::VIEWPORTS as $viewport) {
            foreach ($this->runViewport($theme, $version, $url, $viewport) as $result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /** @return ThemeQualityCheckResult[] */
    private function runViewport(Theme $theme, string $version, string $url, string $viewport): array
    {
        try {
            $evidence = $this->playwright->runQualityCheck($url, $viewport, 30000);
        } catch (Throwable $e) {
            return [ThemeQualityCheckResult::unverified(
                self::CATEGORY,
                "responsive.{$viewport}.capture_failed",
                "Real browser capture for {$viewport} could not be executed (infrastructure failure, not a theme defect): {$e->getMessage()}"
            )];
        }

        $results = [];
        $blocking = false;

        if (empty($evidence['navigation']['ok'])) {
            $results[] = ThemeQualityCheckResult::fail(
                self::CATEGORY,
                "responsive.{$viewport}.navigation_failed",
                "The {$viewport} viewport failed to navigate/render the theme's page at all: " . ($evidence['navigation']['error'] ?? 'unknown error'),
                ['viewport' => $viewport, 'navigation' => $evidence['navigation'] ?? null]
            );
            return $results;
        }

        if (empty($evidence['dom']['rootPresent'] ?? true)) {
            $results[] = ThemeQualityCheckResult::fail(
                self::CATEGORY,
                "responsive.{$viewport}.dom_root_missing",
                "The {$viewport} viewport rendered a page with no document body content.",
                ['viewport' => $viewport, 'dom' => $evidence['dom'] ?? null]
            );
            $blocking = true;
        }

        $overflowPx = (int) ($evidence['overflow']['overflowPx'] ?? 0);
        if (!empty($evidence['overflow']['measured']) && $overflowPx > 0) {
            $vw = (int) ($evidence['viewport_dimensions']['width'] ?? 0);
            $catastrophic = $vw > 0 && ($overflowPx / $vw) > self::CATASTROPHIC_OVERFLOW_RATIO;
            $details = ['viewport' => $viewport, 'overflow_px' => $overflowPx, 'viewport_width' => $vw];
            $results[] = $catastrophic
                ? ThemeQualityCheckResult::fail(
                    self::CATEGORY,
                    "responsive.horizontal_overflow",
                    "The {$viewport} viewport has catastrophic horizontal overflow of {$overflowPx}px (page content wider than the viewport).",
                    $details
                )
                : ThemeQualityCheckResult::warn(
                    self::CATEGORY,
                    "responsive.horizontal_overflow",
                    "The {$viewport} viewport has minor horizontal overflow of {$overflowPx}px.",
                    $details
                );
            $blocking = $blocking || $catastrophic;
        }

        $pageErrorTotal = (int) ($evidence['page_errors']['total'] ?? 0);
        if ($pageErrorTotal > 0) {
            $results[] = ThemeQualityCheckResult::fail(
                self::CATEGORY,
                "browser.page_error",
                "{$pageErrorTotal} uncaught JavaScript exception(s) occurred while rendering the {$viewport} viewport.",
                ['viewport' => $viewport, 'entries' => $evidence['page_errors']['entries'] ?? []]
            );
            $blocking = true;
        }

        $consoleErrorTotal = (int) ($evidence['console_errors']['total'] ?? 0);
        if ($consoleErrorTotal > 0) {
            $results[] = ThemeQualityCheckResult::warn(
                self::CATEGORY,
                "browser.console_error",
                "{$consoleErrorTotal} browser console error(s) were logged while rendering the {$viewport} viewport.",
                ['viewport' => $viewport, 'entries' => $evidence['console_errors']['entries'] ?? []]
            );
        }

        $resourceFailures = $evidence['resource_failures']['entries'] ?? [];
        if (!empty($resourceFailures)) {
            // sameOriginAsTarget is computed browser-side against the exact
            // preview URL navigated to — required-local vs optional-remote.
            $localFailures = array_values(array_filter($resourceFailures, fn ($r) => ($r['sameOriginAsTarget'] ?? false) === true));
            $remoteFailures = array_values(array_filter($resourceFailures, fn ($r) => ($r['sameOriginAsTarget'] ?? false) !== true));

            if (!empty($localFailures)) {
                $results[] = ThemeQualityCheckResult::fail(
                    self::CATEGORY,
                    "browser.resource_failure",
                    count($localFailures) . " required local asset(s) failed to load while rendering the {$viewport} viewport.",
                    ['viewport' => $viewport, 'entries' => $localFailures]
                );
                $blocking = true;
            }
            if (!empty($remoteFailures)) {
                $results[] = ThemeQualityCheckResult::warn(
                    self::CATEGORY,
                    "browser.resource_failure",
                    count($remoteFailures) . " optional remote resource(s) failed to load while rendering the {$viewport} viewport.",
                    ['viewport' => $viewport, 'entries' => $remoteFailures]
                );
            }
        }

        $screenshotPath = $this->storeScreenshotEvidence($theme, $version, $viewport, $evidence['screenshot_base64'] ?? null);

        if (!$blocking) {
            $results[] = ThemeQualityCheckResult::pass(
                self::CATEGORY,
                "responsive.{$viewport}.captured",
                "{$viewport} viewport rendered successfully via a real headless browser (Chromium/Playwright).",
                array_filter([
                    'viewport' => $viewport,
                    'viewport_dimensions' => $evidence['viewport_dimensions'] ?? null,
                    'screenshot_path' => $screenshotPath,
                ])
            );
        }

        return $results;
    }

    /**
     * Quality-evidence screenshots, NOT Marketplace marketing screenshots —
     * a deliberately distinct storage path from CaptureThemeScreenshotsCommand's
     * theme-screenshots/ (which feeds Theme.thumbnail/ThemeMarketplace.screenshots).
     * Deterministic, non-timestamped naming (one file per theme+version+viewport)
     * so re-running the gate overwrites in place instead of growing without bound.
     */
    private function storeScreenshotEvidence(Theme $theme, string $version, string $viewport, ?string $base64): ?string
    {
        if (empty($base64)) {
            return null;
        }

        $path = "theme-quality-evidence/{$theme->slug}/{$version}/{$viewport}.png";
        Storage::disk('public')->put($path, base64_decode($base64));

        return $path;
    }

    /** @return ThemeQualityCheckResult[] */
    private function staticFixedWidthScan(Theme $theme): array
    {
        $results = [];
        $html = $this->renderer->renderBlocks($theme->header_blocks ?? [])
            . $this->renderer->renderBlocks($theme->footer_blocks ?? []);
        foreach (($theme->pages ?? []) as $page) {
            $html .= $this->renderer->renderBlocks($page['blocks'] ?? []);
        }

        if (preg_match_all('/width:\s*(\d{3,})px/i', $html, $matches)) {
            $offenders = array_unique($matches[1]);
            $results[] = ThemeQualityCheckResult::warn(
                self::CATEGORY,
                'responsive.fixed_width_detected',
                'Rendered output contains fixed pixel widths of 100px or more with no max-width escape hatch scanned for — review for mobile overflow.',
                ['widths_px' => array_values($offenders)]
            );
        } else {
            $results[] = ThemeQualityCheckResult::pass(
                self::CATEGORY,
                'responsive.no_fixed_width_detected',
                'No large fixed pixel widths detected in rendered output.'
            );
        }

        return $results;
    }
}
