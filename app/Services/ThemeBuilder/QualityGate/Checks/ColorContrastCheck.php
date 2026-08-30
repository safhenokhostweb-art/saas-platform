<?php
namespace App\Services\ThemeBuilder\QualityGate\Checks;

use App\Models\Theme;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityPreviewUrlFactory;
use App\Services\ThemeBuilder\ReverseEngineering\PlaywrightExtractionService;
use Throwable;

/**
 * Phase 2g — real, browser-computed WCAG AA contrast verification. This is
 * NOT a full WCAG audit: it samples visible text nodes on the theme's home
 * page at the desktop viewport, using the browser's own computed styles
 * (getComputedStyle) to resolve foreground color and walk the ancestor
 * chain for an effective background. Whenever that resolution is not
 * reliable (background-image, gradient, partial-transparency compositing)
 * the node is reported UNVERIFIED, never guessed — see quality-check.mjs's
 * resolveEffectiveBackground().
 *
 * Kept separate from AccessibilityBaselineCheck (static DOM-structure
 * checks: alt text, form labels) because this one requires a real browser
 * and can itself be UNVERIFIED end-to-end when browser infrastructure is
 * unavailable — a genuinely different failure mode from a static check.
 */
class ColorContrastCheck implements ThemeQualityCheck
{
    private const CATEGORY = 'accessibility';

    public function __construct(
        private readonly PlaywrightExtractionService $playwright,
        private readonly ThemeQualityPreviewUrlFactory $previewUrlFactory,
    ) {
    }

    public function run(Theme $theme, string $version, ?array $dependencies): array
    {
        if (!$this->playwright->isAvailable()) {
            return [ThemeQualityCheckResult::unverified(
                self::CATEGORY,
                'accessibility.contrast_unavailable',
                'Headless browser infrastructure is not available in this environment — color contrast requires real computed styles and was not verified.',
                ['availability' => $this->playwright->availabilityReport()]
            )];
        }

        $url = $this->previewUrlFactory->make($theme);
        if ($url === null) {
            return [ThemeQualityCheckResult::unverified(
                self::CATEGORY,
                'accessibility.contrast_unavailable',
                "Theme \"{$theme->slug}\" has no page to generate a preview target from."
            )];
        }

        try {
            $evidence = $this->playwright->runQualityCheck($url, 'desktop', 30000);
        } catch (Throwable $e) {
            return [ThemeQualityCheckResult::unverified(
                self::CATEGORY,
                'accessibility.contrast_unavailable',
                "Real browser contrast verification could not be executed (infrastructure failure, not a theme defect): {$e->getMessage()}"
            )];
        }

        $contrast = $evidence['contrast'] ?? null;
        if (!is_array($contrast)) {
            return [ThemeQualityCheckResult::unverified(
                self::CATEGORY,
                'accessibility.contrast_unavailable',
                'The browser did not return contrast evidence for this page.'
            )];
        }

        $results = [];
        $checked = (int) ($contrast['checked'] ?? 0);
        $failed = (int) ($contrast['failed'] ?? 0);
        $unverified = (int) ($contrast['unverified'] ?? 0);

        foreach ($contrast['failures'] ?? [] as $failure) {
            $results[] = ThemeQualityCheckResult::fail(
                self::CATEGORY,
                'accessibility.contrast_insufficient',
                "Insufficient contrast on a <{$failure['tag']}> element: {$failure['ratio']}:1 measured, {$failure['required']}:1 required (WCAG AA, " . ($failure['isLargeText'] ? 'large text' : 'normal text') . ').',
                $failure
            );
        }

        if ($unverified > 0) {
            $results[] = ThemeQualityCheckResult::unverified(
                self::CATEGORY,
                'accessibility.contrast_partially_unresolvable',
                "{$unverified} sampled text node(s) had a background that could not be reliably resolved (image/gradient/transparency compositing) — never guessed, reported as unverified.",
                ['unresolvable_count' => $unverified, 'sampled' => $contrast['sampled'] ?? 0]
            );
        }

        if ($checked > 0 && $failed === 0) {
            $results[] = ThemeQualityCheckResult::pass(
                self::CATEGORY,
                'accessibility.contrast_verified',
                "{$checked} sampled text node(s) on the home page (desktop viewport) meet WCAG AA contrast. Not an exhaustive audit — sampling is bounded and pages other than home are not evaluated.",
                ['checked' => $checked, 'sampled' => $contrast['sampled'] ?? 0]
            );
        }

        if ($checked === 0 && $unverified === 0) {
            $results[] = ThemeQualityCheckResult::unverified(
                self::CATEGORY,
                'accessibility.contrast_no_samples',
                'No visible text nodes were found to sample for contrast on the home page.'
            );
        }

        return $results;
    }
}
