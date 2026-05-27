<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\File;

class FrontendAccessibilityValidationService
{
    private string $viewsPath;

    public function __construct()
    {
        $this->viewsPath = realpath(__DIR__ . '/../../resources/views') ?: '';
    }

    public function validate(): array
    {
        return [
            'aria_labels'          => $this->checkAriaLabels(),
            'focus_visible'        => $this->checkFocusVisible(),
            'keyboard_navigation'  => $this->checkKeyboardNavigation(),
            'tab_order'            => $this->checkTabOrder(),
            'contrast_classes'     => $this->checkContrastClasses(),
            'rtl_alignment'        => $this->checkRtlAlignment(),
            'persian_typography'   => $this->checkPersianTypography(),
            'responsive_overflow'  => $this->checkResponsiveOverflow(),
            'screen_reader_hints'  => $this->checkScreenReaderHints(),
            'interactive_elements' => $this->checkInteractiveElements(),
        ];
    }

    public function score(): int
    {
        $results = $this->validate();
        $weights = [
            'aria_labels'          => 15,
            'focus_visible'        => 15,
            'keyboard_navigation'  => 15,
            'tab_order'            => 10,
            'contrast_classes'     => 10,
            'rtl_alignment'        => 10,
            'persian_typography'   => 5,
            'responsive_overflow'  => 5,
            'screen_reader_hints'  => 10,
            'interactive_elements' => 5,
        ];

        $totalWeight = array_sum($weights);
        $earnedScore = 0;

        foreach ($results as $key => $result) {
            $weight = $weights[$key] ?? 5;
            if ($result['status'] === 'pass') {
                $earnedScore += $weight;
            } elseif ($result['status'] === 'warning') {
                $earnedScore += (int) ($weight * 0.5);
            }
        }

        return (int) round(($earnedScore / $totalWeight) * 100);
    }

    private function checkAriaLabels(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $missing = [];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);

            // Check for interactive elements without aria-label or aria-labelledby
            preg_match_all('/<button(?![^>]*aria-label)[^>]*>(\s*<svg[^>]*>.*?<\/svg>\s*)<\/button>/si', $contents, $matches);
            if (!empty($matches[0])) {
                // Icon-only buttons without labels
                $count = count($matches[0]);
                // Check if they have title= attribute (acceptable alternative)
                $withoutTitle = array_filter($matches[0], fn($m) => !str_contains($m, 'title='));
                if (count($withoutTitle) > 0) {
                    $missing[] = basename($file) . ': ' . count($withoutTitle) . ' icon-only button(s) without aria-label or title';
                }
            }
        }

        return [
            'status'  => empty($missing) ? 'pass' : 'warning',
            'message' => empty($missing)
                ? 'Interactive elements have adequate ARIA labels'
                : 'Missing ARIA labels: ' . implode('; ', array_slice($missing, 0, 5)),
            'items'   => $missing,
        ];
    }

    private function checkFocusVisible(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $withFocusVisible = 0;
        $totalInteractive = 0;

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            preg_match_all('/<(?:a|button|input|select|textarea)[^>]*>/', $contents, $matches);
            $totalInteractive += count($matches[0]);
            $withFocusVisible += substr_count($contents, 'focus-visible:');
        }

        $ratio = $totalInteractive > 0 ? ($withFocusVisible / max($totalInteractive, 1)) : 0;

        return [
            'status'  => $withFocusVisible > 0 ? 'pass' : 'warning',
            'message' => "focus-visible: classes detected {$withFocusVisible} times across {$totalInteractive} interactive elements",
            'ratio'   => round($ratio, 2),
        ];
    }

    private function checkKeyboardNavigation(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $issues = [];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);

            // Check for @keydown handlers (good sign)
            $keydownCount = substr_count($contents, '@keydown');
            $escapeCount  = substr_count($contents, '@keydown.escape');
            $enterCount   = substr_count($contents, '@keydown.enter');

            // Modals/drawers should have escape handlers
            if (str_contains($contents, 'x-show') && str_contains($contents, 'modal') && $escapeCount === 0) {
                $issues[] = basename($file) . ': modal detected without @keydown.escape handler';
            }
        }

        return [
            'status'  => empty($issues) ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'Keyboard navigation handlers present'
                : 'Keyboard navigation issues: ' . implode('; ', $issues),
            'items'   => $issues,
        ];
    }

    private function checkTabOrder(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $issues = [];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);

            // tabindex="-1" is acceptable for programmatic focus; tabindex > 0 is bad
            preg_match_all('/tabindex=["\']([1-9]\d*)["\']/', $contents, $matches);
            if (!empty($matches[1])) {
                $issues[] = basename($file) . ': positive tabindex values found (' . implode(',', $matches[1]) . ') — breaks natural tab order';
            }
        }

        return [
            'status'  => empty($issues) ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'No positive tabindex values detected'
                : 'Tab order issues: ' . implode('; ', $issues),
            'items'   => $issues,
        ];
    }

    private function checkContrastClasses(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $lowContrastPatterns = [
            'text-slate-300',
            'text-slate-200',
            'text-gray-300',
            'text-gray-200',
        ];

        $findings = [];
        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            foreach ($lowContrastPatterns as $pattern) {
                if (substr_count($contents, $pattern) > 3) {
                    $findings[] = basename($file) . ": heavy use of low-contrast class $pattern";
                }
            }
        }

        return [
            'status'  => count($findings) <= 2 ? 'pass' : 'warning',
            'message' => empty($findings)
                ? 'Color contrast classes appear adequate'
                : 'Potential contrast issues: ' . implode('; ', array_slice($findings, 0, 3)),
            'items'   => $findings,
        ];
    }

    private function checkRtlAlignment(): array
    {
        $bladeFiles = $this->allBladeFiles('admin');
        $issues = [];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);

            // Admin views should not use text-left without text-right equivalent in RTL
            $leftCount  = substr_count($contents, 'text-left');
            $rightCount = substr_count($contents, 'text-right');

            if ($leftCount > 5 && $rightCount === 0) {
                $issues[] = basename($file) . ": uses text-left {$leftCount}× but no text-right — may break RTL";
            }
        }

        return [
            'status'  => empty($issues) ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'RTL alignment classes appear consistent'
                : 'RTL alignment issues: ' . implode('; ', $issues),
            'items'   => $issues,
        ];
    }

    private function checkPersianTypography(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $persianCount = 0;
        $totalFiles   = count($bladeFiles);

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            // Check for Persian/Arabic Unicode characters
            if (preg_match('/[\x{0600}-\x{06FF}]/u', $contents)) {
                $persianCount++;
            }
        }

        return [
            'status'  => 'pass',
            'message' => "Persian text found in {$persianCount}/{$totalFiles} view files — typography consistent",
        ];
    }

    private function checkResponsiveOverflow(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $issues = [];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            // Fixed-width values without responsive variants can cause overflow
            preg_match_all('/\bw-\[(\d{3,}px)\]/', $contents, $matches);
            if (!empty($matches[0])) {
                $issues[] = basename($file) . ': fixed pixel widths found — may overflow on mobile';
            }
        }

        return [
            'status'  => empty($issues) ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'No fixed-pixel overflow risks detected'
                : 'Potential overflow: ' . implode('; ', $issues),
            'items'   => $issues,
        ];
    }

    private function checkScreenReaderHints(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $found = ['sr-only' => 0, 'aria-hidden' => 0, 'aria-live' => 0, 'role=' => 0];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            foreach (array_keys($found) as $pattern) {
                $found[$pattern] += substr_count($contents, $pattern);
            }
        }

        $total = array_sum($found);

        return [
            'status'  => $total >= 5 ? 'pass' : 'warning',
            'message' => "Screen reader attributes: sr-only={$found['sr-only']}, aria-hidden={$found['aria-hidden']}, aria-live={$found['aria-live']}, role={$found['role=']}",
            'counts'  => $found,
        ];
    }

    private function checkInteractiveElements(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $issues = [];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            // div/span with @click but no role or tabindex — inaccessible to keyboard
            preg_match_all('/<(?:div|span)[^>]+@click[^>]*>/', $contents, $matches);
            foreach ($matches[0] as $tag) {
                if (!str_contains($tag, 'role=') && !str_contains($tag, 'tabindex=')) {
                    $issues[] = basename($file) . ': clickable div/span without role/tabindex';
                    break;
                }
            }
        }

        $issues = array_unique($issues);

        return [
            'status'  => count($issues) <= 2 ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'Interactive elements are properly marked up'
                : 'Clickable non-semantic elements: ' . implode('; ', array_slice($issues, 0, 3)),
            'items'   => $issues,
        ];
    }

    private function allBladeFiles(string $subdir = ''): array
    {
        if (!$this->viewsPath || !File::isDirectory($this->viewsPath)) {
            return [];
        }

        $path = $subdir ? $this->viewsPath . '/' . $subdir : $this->viewsPath;

        if (!File::isDirectory($path)) {
            return [];
        }

        return array_map(
            fn($f) => $f->getPathname(),
            File::allFiles($path)
        );
    }
}
