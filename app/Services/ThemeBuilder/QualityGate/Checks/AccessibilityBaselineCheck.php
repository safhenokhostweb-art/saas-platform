<?php
namespace App\Services\ThemeBuilder\QualityGate\Checks;

use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;
use DOMDocument;
use DOMXPath;

/**
 * A real, narrow-scope accessibility check — it parses the theme's actual
 * rendered HTML (via BlockRenderer, the same renderer production uses) with
 * PHP's DOMDocument/DOMXPath and finds concrete, provable violations. This
 * is not a WCAG audit and must never be presented as one: no color-contrast
 * check is performed here (that requires a real browser computing rendered
 * styles — see ColorContrastCheck, Phase 2g), and no ARIA-semantics/
 * keyboard-navigation check is performed either.
 *
 * What IS real and enforced:
 *   - every <img> has a non-empty alt attribute (BLOCKING)
 *   - every <input>/<textarea>/<select> has an associated label, aria-label,
 *     aria-labelledby, or title (BLOCKING)
 *   - at most one <h1> per page (WARNING — common practice, not universally
 *     "critical")
 */
class AccessibilityBaselineCheck implements ThemeQualityCheck
{
    private const CATEGORY = 'accessibility';

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
            $this->scanPage($html, "page:{$pageKey}", $results);
        }

        if (!array_filter($results, fn ($r) => $r->isBlockingFailure())) {
            $results[] = ThemeQualityCheckResult::pass(self::CATEGORY, 'accessibility.baseline_ok', 'Baseline accessibility checks (image alt text, form labels) passed.');
        }

        return $results;
    }

    private function scanPage(string $html, string $location, array &$results): void
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        // Wrap fragment in a body so DOMDocument parses loose block HTML consistently.
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//img') as $img) {
            $alt = $img->getAttribute('alt');
            if (trim($alt) === '' && !$img->hasAttribute('alt')) {
                $results[] = ThemeQualityCheckResult::fail(
                    self::CATEGORY,
                    'accessibility.image_missing_alt',
                    "An <img> in {$location} has no alt attribute at all.",
                    ['location' => $location, 'src' => $img->getAttribute('src')]
                );
            } elseif (trim($alt) === '') {
                $results[] = ThemeQualityCheckResult::warn(
                    self::CATEGORY,
                    'accessibility.image_empty_alt',
                    "An <img> in {$location} has an empty alt attribute (acceptable only for purely decorative images).",
                    ['location' => $location, 'src' => $img->getAttribute('src')]
                );
            }
        }

        foreach ($xpath->query('//input[@type!="hidden"]|//textarea|//select') as $field) {
            $id = $field->getAttribute('id');
            $hasAriaLabel = $field->hasAttribute('aria-label') && trim($field->getAttribute('aria-label')) !== '';
            $hasAriaLabelledBy = $field->hasAttribute('aria-labelledby') && trim($field->getAttribute('aria-labelledby')) !== '';
            $hasTitle = $field->hasAttribute('title') && trim($field->getAttribute('title')) !== '';
            $hasLabelFor = $id !== '' && $xpath->query("//label[@for='{$id}']")->length > 0;

            if (!$hasAriaLabel && !$hasAriaLabelledBy && !$hasTitle && !$hasLabelFor) {
                $results[] = ThemeQualityCheckResult::fail(
                    self::CATEGORY,
                    'accessibility.form_field_missing_label',
                    "A form field ({$field->nodeName}) in {$location} has no associated label, aria-label, aria-labelledby, or title.",
                    ['location' => $location, 'tag' => $field->nodeName]
                );
            }
        }

        $h1Count = $xpath->query('//h1')->length;
        if ($h1Count > 1) {
            $results[] = ThemeQualityCheckResult::warn(
                self::CATEGORY,
                'accessibility.multiple_h1',
                "{$location} has {$h1Count} <h1> elements — a single top-level heading is the common accessible pattern.",
                ['location' => $location, 'count' => $h1Count]
            );
        }
    }
}
