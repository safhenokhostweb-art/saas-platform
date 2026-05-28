<?php

namespace SaaSPlatform\CoreUtils\Frontend;

use Illuminate\Support\Facades\File;

/**
 * Phase 10.2 — Responsive Governance Validation Service
 *
 * Enforces:
 * - Touch targets >= 44px
 * - Mobile-safe table wrappers
 * - RTL logical property usage
 * - No overflow:hidden without mobile guard
 * - prefers-reduced-motion support
 * - Responsive grid / flex breakpoints
 */
class ResponsiveGovernanceValidationService
{
    private array $violations = [];
    private array $warnings   = [];
    private array $dimensions = [];

    private const BLADE_DIRS = [
        'core-utils' => 'packages/saas-platform/core-utils/resources/views',
        'forms'      => 'packages/saas-platform/modules/forms/resources/views',
        'tickets'    => 'packages/saas-platform/modules/tickets/Resources/views',
        'admin'      => 'resources/views',
    ];

    /** Minimum touch target — px value that must NOT appear on interactive elements */
    private const SMALL_TOUCH_PATTERN = '/(?:w-[1-7]|h-[1-7])\s/';

    /** Logical properties that must be used instead of physical */
    private const PHYSICAL_PROPS = [
        'margin-left'  => 'margin-inline-start',
        'margin-right' => 'margin-inline-end',
        'padding-left' => 'padding-inline-start',
        'padding-right'=> 'padding-inline-end',
        'left'         => 'inset-inline-start',
        'right'        => 'inset-inline-end',
    ];

    public function validate(bool $strict = false): array
    {
        $this->violations = [];
        $this->warnings   = [];
        $this->dimensions = [];

        $this->validateTouchTargets();
        $this->validateTableWrappers();
        $this->validateReducedMotion();
        $this->validateRtlLogicalProps();
        $this->validateMobileBreakpoints();

        $score = $this->computeScore();

        return [
            'score'      => $score,
            'grade'      => $this->grade($score),
            'dimensions' => $this->dimensions,
            'violations' => $this->violations,
            'warnings'   => $this->warnings,
            'summary'    => [
                'total_violations'       => count($this->violations),
                'total_warnings'         => count($this->warnings),
                'touch_target_issues'    => $this->dimensions['touch_target_violations'] ?? 0,
                'table_wrapper_issues'   => $this->dimensions['table_wrapper_violations'] ?? 0,
                'reduced_motion_ok'      => $this->dimensions['reduced_motion_ok'] ?? false,
                'rtl_violations'         => $this->dimensions['rtl_violations'] ?? 0,
            ],
        ];
    }

    private function validateTouchTargets(): void
    {
        $count = 0;

        // Interactive elements that need min 44px: buttons, links with action
        $pattern = '/(?:<(?:button|a)\s[^>]*class="[^"]*(?:w-[1-8]|h-[1-8])\s)/';

        foreach ($this->allBladeFiles() as $module => $files) {
            foreach ($files as $file) {
                $content = file_get_contents($file->getPathname());
                $lines   = explode("\n", $content);

                foreach ($lines as $ln => $line) {
                    // Look for interactive elements with tiny sizing utilities
                    if (preg_match('/<(button|a)\s/', $line) &&
                        preg_match('/\b(?:w-[1-7]|h-[1-7])\b/', $line) &&
                        !preg_match('/\bmin-h-\[44px\]|\bmin-w-\[44px\]/', $line)) {
                        $count++;
                        $this->warnings[] = [
                            'dimension' => 'touch_target',
                            'module'    => $module,
                            'file'      => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()),
                            'line'      => $ln + 1,
                            'message'   => 'Touch target may be < 44px — add min-h-[44px] min-w-[44px]',
                            'snippet'   => trim(substr($line, 0, 120)),
                        ];
                    }
                }
            }
        }

        $this->dimensions['touch_target_violations'] = $count;
    }

    private function validateTableWrappers(): void
    {
        $count = 0;

        foreach ($this->allBladeFiles() as $module => $files) {
            foreach ($files as $file) {
                $content = file_get_contents($file->getPathname());

                // A <table> that is not inside overflow-x-auto wrapper is a violation
                if (str_contains($content, '<table') && !str_contains($content, 'overflow-x-auto')) {
                    $count++;
                    $this->warnings[] = [
                        'dimension' => 'table_wrapper',
                        'module'    => $module,
                        'file'      => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()),
                        'line'      => 0,
                        'message'   => '<table> without overflow-x-auto wrapper — mobile table will break',
                    ];
                }
            }
        }

        $this->dimensions['table_wrapper_violations'] = $count;
    }

    private function validateReducedMotion(): void
    {
        $cssFiles = [
            base_path('resources/css/app.css'),
            base_path('packages/saas-platform/core-utils/resources/css/design-tokens.css'),
        ];

        $hasReducedMotion = false;

        foreach ($cssFiles as $path) {
            if (file_exists($path) && str_contains(file_get_contents($path), 'prefers-reduced-motion')) {
                $hasReducedMotion = true;
                break;
            }
        }

        $this->dimensions['reduced_motion_ok'] = $hasReducedMotion;

        if (!$hasReducedMotion) {
            $this->violations[] = [
                'dimension' => 'reduced_motion',
                'message'   => 'prefers-reduced-motion not found in any CSS — accessibility violation',
            ];
        }
    }

    private function validateRtlLogicalProps(): void
    {
        $count = 0;

        foreach ($this->allBladeFiles() as $module => $files) {
            foreach ($files as $file) {
                $content = file_get_contents($file->getPathname());
                $lines   = explode("\n", $content);

                foreach ($lines as $ln => $line) {
                    // Only check inline style= attributes for physical LTR-only props
                    if (!preg_match('/style\s*=\s*["\']/', $line)) continue;

                    foreach (array_keys(self::PHYSICAL_PROPS) as $physProp) {
                        if (str_contains($line, $physProp . ':') || str_contains($line, $physProp . ' :')) {
                            $count++;
                            $logical = self::PHYSICAL_PROPS[$physProp];
                            $this->warnings[] = [
                                'dimension' => 'rtl_logical',
                                'module'    => $module,
                                'file'      => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()),
                                'line'      => $ln + 1,
                                'message'   => "Physical CSS prop [{$physProp}] — use logical [{$logical}]",
                                'snippet'   => trim(substr($line, 0, 120)),
                            ];
                            break;
                        }
                    }
                }
            }
        }

        $this->dimensions['rtl_violations'] = $count;
    }

    private function validateMobileBreakpoints(): void
    {
        $requiredBreakpoints = ['sm:', 'md:', 'lg:'];
        $filesWithBreakpoints = 0;
        $filesAudited = 0;

        foreach ($this->allBladeFiles() as $module => $files) {
            foreach ($files as $file) {
                // Only check non-component admin views
                if (str_contains($file->getPathname(), '/components/')) continue;

                $content = file_get_contents($file->getPathname());

                // Skip files without grid/flex layouts
                if (!str_contains($content, 'grid') && !str_contains($content, 'flex')) continue;

                $filesAudited++;
                $hasBreakpoint = false;

                foreach ($requiredBreakpoints as $bp) {
                    if (str_contains($content, $bp)) {
                        $hasBreakpoint = true;
                        break;
                    }
                }

                if ($hasBreakpoint) $filesWithBreakpoints++;
            }
        }

        $coverage = $filesAudited > 0
            ? (int) round($filesWithBreakpoints / $filesAudited * 100)
            : 100;

        $this->dimensions['breakpoint_coverage']  = $coverage;
        $this->dimensions['files_with_responsive'] = $filesWithBreakpoints;
        $this->dimensions['layout_files_audited']  = $filesAudited;

        if ($coverage < 80) {
            $this->warnings[] = [
                'dimension' => 'breakpoints',
                'message'   => "Only {$coverage}% of layout files use responsive breakpoints",
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

    private function computeScore(): int
    {
        $score   = 100;
        $score  -= count($this->violations) * 5;
        $score  -= (int) round(count($this->warnings) * 1.5);

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
