<?php

namespace SaaSPlatform\CoreUtils\Frontend;

use Illuminate\Support\Facades\File;

/**
 * Phase 10.2 — Accessibility Governance Validation Service (WCAG AA)
 *
 * Enforces:
 * - ARIA labels on interactive elements
 * - focus-visible (not just focus:) on focusable controls
 * - Escape key support on modals/dialogs
 * - role="dialog" + aria-modal on all modals
 * - alt text on images
 * - <label> or aria-label on all inputs
 * - Semantic landmarks (main, nav, header, footer)
 * - prefers-reduced-motion in CSS
 * - No positive tabindex
 * - Skip-to-content link in layouts
 */
class AccessibilityGovernanceValidationService
{
    private array $violations = [];
    private array $warnings   = [];
    private array $scores     = [];

    private const BLADE_DIRS = [
        'core-utils' => 'packages/saas-platform/core-utils/resources/views',
        'forms'      => 'packages/saas-platform/modules/forms/resources/views',
        'tickets'    => 'packages/saas-platform/modules/tickets/Resources/views',
        'admin'      => 'resources/views',
    ];

    public function validate(): array
    {
        $this->violations = [];
        $this->warnings   = [];
        $this->scores     = [];

        $this->checkAriaLabels();
        $this->checkFocusVisible();
        $this->checkModalAccessibility();
        $this->checkImageAlt();
        $this->checkPositiveTabindex();
        $this->checkSemanticLandmarks();
        $this->checkSkipLink();
        $this->checkReducedMotionCss();

        $score = $this->computeScore();

        return [
            'score'      => $score,
            'grade'      => $this->grade($score),
            'checks'     => $this->scores,
            'violations' => $this->violations,
            'warnings'   => $this->warnings,
            'summary'    => [
                'wcag_level'        => $score >= 90 ? 'AA' : ($score >= 75 ? 'A' : 'Fail'),
                'total_violations'  => count($this->violations),
                'total_warnings'    => count($this->warnings),
            ],
        ];
    }

    private function checkAriaLabels(): void
    {
        $missing = 0;

        foreach ($this->allBladeFiles() as $module => $files) {
            foreach ($files as $file) {
                $content = file_get_contents($file->getPathname());
                $lines   = explode("\n", $content);

                foreach ($lines as $ln => $line) {
                    // Icon-only buttons must have aria-label
                    if (preg_match('/<button[^>]*>\s*(?:<svg|<x-core-ui::icon)/i', $line) &&
                        !preg_match('/aria-label\s*=/i', $line)) {
                        $missing++;
                        $this->warnings[] = [
                            'check'   => 'aria_labels',
                            'module'  => $module,
                            'file'    => $this->rel($file->getPathname()),
                            'line'    => $ln + 1,
                            'message' => 'Icon-only button without aria-label',
                            'snippet' => trim(substr($line, 0, 120)),
                        ];
                    }

                    // Inputs without aria-label or associated label
                    if (preg_match('/<input\b/i', $line) &&
                        !preg_match('/aria-label|aria-labelledby|id\s*=/i', $line)) {
                        $missing++;
                        $this->warnings[] = [
                            'check'   => 'aria_labels',
                            'module'  => $module,
                            'file'    => $this->rel($file->getPathname()),
                            'line'    => $ln + 1,
                            'message' => 'Input without aria-label or id (for <label for>)',
                            'snippet' => trim(substr($line, 0, 120)),
                        ];
                    }
                }
            }
        }

        $this->scores['aria_labels'] = ['violations' => $missing, 'status' => $missing === 0 ? 'pass' : 'warn'];
    }

    private function checkFocusVisible(): void
    {
        $count = 0;

        foreach ($this->allBladeFiles() as $module => $files) {
            foreach ($files as $file) {
                $content = file_get_contents($file->getPathname());

                // Detect focus: (non-visible) without focus-visible: equivalent
                if (preg_match_all('/\bfocus:(?!ring|outline|border|visible)/', $content, $m)) {
                    foreach ($m[0] as $match) {
                        $count++;
                        $this->warnings[] = [
                            'check'   => 'focus_visible',
                            'module'  => $module,
                            'file'    => $this->rel($file->getPathname()),
                            'line'    => 0,
                            'message' => "focus: utility without focus-visible: equivalent — breaks keyboard nav",
                        ];
                        break; // one warning per file
                    }
                }
            }
        }

        $this->scores['focus_visible'] = ['violations' => $count, 'status' => $count === 0 ? 'pass' : 'warn'];
    }

    private function checkModalAccessibility(): void
    {
        $missing = 0;

        foreach ($this->allBladeFiles() as $module => $files) {
            foreach ($files as $file) {
                $content = file_get_contents($file->getPathname());

                // Modal must have role="dialog" + aria-modal="true" + @keydown.escape
                if (str_contains($content, 'role="dialog"')) {
                    $hasAriaModal = str_contains($content, 'aria-modal');
                    $hasEscape    = str_contains($content, '@keydown.escape') || str_contains($content, 'keydown.esc');
                    $hasLabelledBy = str_contains($content, 'aria-labelledby');

                    if (!$hasAriaModal || !$hasEscape || !$hasLabelledBy) {
                        $missing++;
                        $this->warnings[] = [
                            'check'   => 'modal_a11y',
                            'module'  => $module,
                            'file'    => $this->rel($file->getPathname()),
                            'line'    => 0,
                            'message' => 'Dialog missing: ' . implode(', ', array_filter([
                                !$hasAriaModal  ? 'aria-modal="true"'   : null,
                                !$hasEscape     ? '@keydown.escape'     : null,
                                !$hasLabelledBy ? 'aria-labelledby'     : null,
                            ])),
                        ];
                    }
                }
            }
        }

        $this->scores['modal_a11y'] = ['violations' => $missing, 'status' => $missing === 0 ? 'pass' : 'warn'];
    }

    private function checkImageAlt(): void
    {
        $missing = 0;

        foreach ($this->allBladeFiles() as $module => $files) {
            foreach ($files as $file) {
                $content = file_get_contents($file->getPathname());
                $lines   = explode("\n", $content);

                foreach ($lines as $ln => $line) {
                    if (preg_match('/<img\b/i', $line) && !preg_match('/\balt\s*=/i', $line)) {
                        $missing++;
                        $this->violations[] = [
                            'check'   => 'image_alt',
                            'module'  => $module,
                            'file'    => $this->rel($file->getPathname()),
                            'line'    => $ln + 1,
                            'message' => '<img> without alt attribute — WCAG violation',
                            'snippet' => trim(substr($line, 0, 120)),
                        ];
                    }
                }
            }
        }

        $this->scores['image_alt'] = ['violations' => $missing, 'status' => $missing === 0 ? 'pass' : 'fail'];
    }

    private function checkPositiveTabindex(): void
    {
        $count = 0;

        foreach ($this->allBladeFiles() as $module => $files) {
            foreach ($files as $file) {
                $content = file_get_contents($file->getPathname());
                $lines   = explode("\n", $content);

                foreach ($lines as $ln => $line) {
                    if (preg_match('/tabindex\s*=\s*["\']?\s*[1-9]/', $line)) {
                        $count++;
                        $this->violations[] = [
                            'check'   => 'tabindex',
                            'module'  => $module,
                            'file'    => $this->rel($file->getPathname()),
                            'line'    => $ln + 1,
                            'message' => 'Positive tabindex disrupts natural focus order',
                            'snippet' => trim(substr($line, 0, 120)),
                        ];
                    }
                }
            }
        }

        $this->scores['tabindex'] = ['violations' => $count, 'status' => $count === 0 ? 'pass' : 'fail'];
    }

    private function checkSemanticLandmarks(): void
    {
        $layoutFiles = array_filter(
            File::allFiles(resource_path('views/layouts')),
            fn($f) => $f->getExtension() === 'php'
        );

        $landmarks = ['<main', '<nav', '<header', '<footer', 'role="main"', 'role="navigation"'];
        $ok = true;

        foreach ($layoutFiles as $file) {
            $content  = file_get_contents($file->getPathname());
            $found    = array_filter($landmarks, fn($l) => str_contains($content, $l));

            if (count($found) < 3) {
                $ok = false;
                $this->warnings[] = [
                    'check'   => 'landmarks',
                    'module'  => 'admin',
                    'file'    => $this->rel($file->getPathname()),
                    'line'    => 0,
                    'message' => 'Layout lacks sufficient semantic landmarks (main/nav/header)',
                ];
            }
        }

        $this->scores['landmarks'] = ['status' => $ok ? 'pass' : 'warn'];
    }

    private function checkSkipLink(): void
    {
        $adminLayout = resource_path('views/layouts/admin.blade.php');
        $hasSkipLink = false;

        if (file_exists($adminLayout)) {
            $content     = file_get_contents($adminLayout);
            $hasSkipLink = str_contains($content, 'skip') && str_contains($content, 'main-content');
        }

        $this->scores['skip_link'] = ['status' => $hasSkipLink ? 'pass' : 'warn'];

        if (!$hasSkipLink) {
            $this->warnings[] = [
                'check'   => 'skip_link',
                'module'  => 'admin',
                'file'    => 'resources/views/layouts/admin.blade.php',
                'line'    => 0,
                'message' => 'No skip-to-content link found in admin layout',
            ];
        }
    }

    private function checkReducedMotionCss(): void
    {
        $tokenCss = base_path('packages/saas-platform/core-utils/resources/css/design-tokens.css');
        $appCss   = resource_path('css/app.css');

        $found = (file_exists($tokenCss) && str_contains(file_get_contents($tokenCss), 'prefers-reduced-motion'))
              || (file_exists($appCss)   && str_contains(file_get_contents($appCss),   'prefers-reduced-motion'));

        $this->scores['reduced_motion'] = ['status' => $found ? 'pass' : 'fail'];

        if (!$found) {
            $this->violations[] = [
                'check'   => 'reduced_motion',
                'module'  => 'css',
                'message' => '@media (prefers-reduced-motion) not found in design system CSS',
            ];
        }
    }

    private function allBladeFiles(): array
    {
        $result = [];

        foreach (self::BLADE_DIRS as $module => $relPath) {
            $dir = base_path($relPath);
            if (!is_dir($dir)) continue;

            $result[$module] = array_filter(
                File::allFiles($dir),
                fn($f) => $f->getExtension() === 'php'
            );
        }

        return $result;
    }

    private function rel(string $path): string
    {
        return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
    }

    private function computeScore(): int
    {
        $score  = 100;
        $score -= count($this->violations) * 6;
        $score -= (int) round(count($this->warnings) * 2);

        return max(0, $score);
    }

    private function grade(int $score): string
    {
        return match(true) {
            $score >= 97 => 'A+',
            $score >= 93 => 'A',
            $score >= 90 => 'A-',
            $score >= 87 => 'B+',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            default      => 'F',
        };
    }
}
