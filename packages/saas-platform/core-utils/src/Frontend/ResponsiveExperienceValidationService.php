<?php

namespace SaaSPlatform\CoreUtils\Frontend;

/**
 * Phase 10.1 — Task 9
 * Validates responsive design compliance, mobile experience, touch targets,
 * RTL integrity, sidebar collapse, modal mobile behavior, and typography scaling.
 */
class ResponsiveExperienceValidationService
{
    private const RESPONSIVE_CHECKS = [
        'touch_targets' => [
            'description' => 'Interactive elements must meet 44px minimum touch target',
            'patterns'    => ['h-6 w-6', 'h-5 w-5', 'p-1 ', 'p-0.5'],
            'severity'    => 'HIGH',
        ],
        'mobile_overflow' => [
            'description' => 'Tables and wide content must have overflow-x scroll wrapper',
            'check'       => 'table_without_overflow',
            'severity'    => 'CRITICAL',
        ],
        'responsive_breakpoints' => [
            'description' => 'Views must use sm: md: lg: responsive variants, not fixed px widths',
            'patterns'    => ['style="width:', 'style="min-width:', 'w-[3', 'w-[4', 'w-[5', 'w-[6', 'w-[7', 'w-[8', 'w-[9'],
            'severity'    => 'HIGH',
        ],
        'sidebar_mobile' => [
            'description' => 'Sidebar must have mobile collapse behavior',
            'check'       => 'sidebar_no_mobile',
            'severity'    => 'CRITICAL',
        ],
        'modal_mobile' => [
            'description' => 'Modals must use bottom-sheet or full-screen on xs viewports',
            'check'       => 'modal_no_mobile',
            'severity'    => 'HIGH',
        ],
        'font_scaling' => [
            'description' => 'Font sizes must not be fixed px — use rem/token vars',
            'patterns'    => ['font-size: 11px', 'font-size: 12px', 'font-size: 13px', 'font-size: 10px'],
            'severity'    => 'MEDIUM',
        ],
        'rtl_integrity' => [
            'description' => 'Views using directional CSS must respect RTL via --ui-dir token',
            'patterns'    => ['margin-left:', 'margin-right:', 'padding-left:', 'padding-right:', 'text-left', 'text-right'],
            'severity'    => 'MEDIUM',
        ],
        'reduced_motion' => [
            'description' => 'Custom animations must check prefers-reduced-motion or use canonical tokens',
            'patterns'    => ['@keyframes ', 'animation:', 'transition: all'],
            'check'       => 'animation_no_reduced_motion',
            'severity'    => 'HIGH',
        ],
        'container_width' => [
            'description' => 'Content containers must use canonical --ui-content-max-width tokens',
            'patterns'    => ['max-w-7xl', 'max-w-6xl', 'max-w-5xl'],
            'severity'    => 'MEDIUM',
        ],
        'keyboard_navigation' => [
            'description' => 'Interactive components must support keyboard navigation',
            'patterns'    => ['onclick=', '@click without @keydown'],
            'severity'    => 'HIGH',
        ],
    ];

    private array $scanPaths;
    private array $violations = [];

    public function __construct()
    {
        $base = base_path('packages/saas-platform');
        $this->scanPaths = [
            'forms'   => $base . '/modules/forms/resources/views',
            'tickets' => $base . '/modules/tickets/Resources/views',
            'layouts' => resource_path('views/layouts'),
        ];
    }

    public function validate(): array
    {
        $this->violations = [];
        $filesScanned     = 0;

        foreach ($this->scanPaths as $module => $path) {
            if (! is_dir($path)) continue;

            $files = $this->getBladeFiles($path);
            $filesScanned += count($files);

            foreach ($files as $file) {
                $content = file_get_contents($file);
                $this->runChecks($file, $content, $module);
            }
        }

        $score = $this->computeScore();

        return [
            'score'          => $score,
            'files_scanned'  => $filesScanned,
            'violations'     => $this->violations,
            'checks'         => array_keys(self::RESPONSIVE_CHECKS),
            'recommendations'=> $this->generateRecommendations(),
            'summary'        => sprintf(
                'Responsive Governance Score: %d/100 | Violations: %d',
                $score, count($this->violations)
            ),
        ];
    }

    private function runChecks(string $file, string $content, string $module): void
    {
        $rel = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);

        foreach (self::RESPONSIVE_CHECKS as $key => $check) {
            if (isset($check['patterns'])) {
                foreach ($check['patterns'] as $pattern) {
                    if (str_contains($content, $pattern)) {
                        $this->violations[] = [
                            'check'       => $key,
                            'file'        => $rel,
                            'module'      => $module,
                            'description' => $check['description'],
                            'pattern'     => $pattern,
                            'severity'    => $check['severity'],
                        ];
                        break; // one violation per check per file
                    }
                }
            }

            // Structural checks
            if (isset($check['check'])) {
                $this->runStructuralCheck($check['check'], $file, $content, $module, $rel, $check['description'], $check['severity']);
            }
        }
    }

    private function runStructuralCheck(string $checkName, string $file, string $content, string $module, string $rel, string $description, string $severity): void
    {
        $violation = match($checkName) {
            'table_without_overflow' =>
                str_contains($content, '<table') && ! str_contains($content, 'overflow-x-auto'),
            'sidebar_no_mobile' =>
                str_contains($content, 'sidebar') && ! str_contains($content, 'sm:block') && ! str_contains($content, 'x-show') && str_contains($rel, 'layout'),
            'modal_no_mobile' =>
                str_contains($content, 'x-core-ui::modal') && ! str_contains($content, 'inset-x-'),
            'animation_no_reduced_motion' =>
                str_contains($content, '@keyframes') && ! str_contains($content, 'prefers-reduced-motion'),
            default => false,
        };

        if ($violation) {
            $this->violations[] = [
                'check'       => $checkName,
                'file'        => $rel,
                'module'      => $module,
                'description' => $description,
                'pattern'     => $checkName,
                'severity'    => $severity,
            ];
        }
    }

    private function computeScore(): int
    {
        $deduction = 0;
        foreach ($this->violations as $v) {
            $deduction += match($v['severity']) {
                'CRITICAL' => 8,
                'HIGH'     => 4,
                'MEDIUM'   => 2,
                default    => 1,
            };
        }
        return max(0, 100 - $deduction);
    }

    private function generateRecommendations(): array
    {
        $recs = [];
        $seen = [];

        foreach ($this->violations as $v) {
            if (in_array($v['check'], $seen)) continue;
            $seen[] = $v['check'];

            $recs[] = match($v['check']) {
                'touch_targets'          => 'Add min-h-[var(--ui-touch-target-min)] to interactive elements smaller than 44px',
                'mobile_overflow'        => 'Wrap all <table> elements with <div class="overflow-x-auto">',
                'sidebar_no_mobile'      => 'Wire x-core-ui::sidebar-group with Alpine mobile collapse to admin layout',
                'modal_no_mobile'        => 'Modal already uses inset-x positioning — verify xs breakpoint fullscreen in x-core-ui::modal',
                'animation_no_reduced_motion' => 'Inline @keyframes should live in design-tokens.css with @media (prefers-reduced-motion) override',
                'rtl_integrity'          => 'Replace directional margin/padding with logical properties: margin-inline-start, padding-inline-end',
                'font_scaling'           => 'Replace px font sizes with --ui-text-* tokens (var(--ui-text-sm) etc.)',
                'container_width'        => 'Replace max-w-7xl with max-w-[var(--ui-content-max-width)]',
                default                  => "Fix: {$v['description']}",
            };
        }

        return $recs;
    }

    private function getBladeFiles(string $path): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }
}
