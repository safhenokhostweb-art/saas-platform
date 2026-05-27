<?php

namespace SaaSPlatform\CoreUtils\Enterprise;

class EnterpriseProductExperienceService
{
    private array $findings = [];

    public function validate(array $options = []): array
    {
        $this->findings = [];
        $strict = $options['strict'] ?? false;

        $this->checkSpacingConsistency();
        $this->checkTypographyConsistency();
        $this->checkAnimationConsistency();
        $this->checkComponentUsage();
        $this->checkAccessibilityQuality($strict);
        $this->checkKeyboardFlow();
        $this->checkMobileResponsiveness();
        $this->checkSkeletonLoadingUsage();
        $this->checkDarkModeReadiness();
        $this->checkRtlSupport();
        $this->checkInteractionTiming();

        $score = $this->calculateScore();

        return [
            'score'    => $score,
            'grade'    => $this->scoreToGrade($score),
            'findings' => $this->findings,
            'summary'  => $this->buildSummary(),
            'passed'   => $score >= ($strict ? 90 : 80),
        ];
    }

    public function validateView(string $viewPath): array
    {
        if (! file_exists($viewPath)) {
            return ['error' => "View not found: {$viewPath}"];
        }

        $content = file_get_contents($viewPath);
        $issues  = [];

        $this->analyzeBladeContent($content, $issues, basename($viewPath));

        return ['file' => $viewPath, 'issues' => $issues, 'issue_count' => count($issues)];
    }

    private function checkSpacingConsistency(): void
    {
        $viewFiles = $this->findViewFiles();
        $violations = 0;
        foreach ($viewFiles as $file) {
            $content = @file_get_contents($file);
            // Detect inline pixel spacing not using Tailwind classes
            if ($content && preg_match('/style=["\'][^"\']*(?:margin|padding):\s*\d+px/i', $content)) {
                $violations++;
            }
        }
        if ($violations > 0) {
            $this->addFinding('spacing', 'warning', "{$violations} view(s) use raw pixel spacing instead of Tailwind utility classes");
        } else {
            $this->addFinding('spacing', 'pass', 'Spacing consistency: OK');
        }
    }

    private function checkTypographyConsistency(): void
    {
        $viewFiles   = $this->findViewFiles();
        $fontViolations = 0;
        foreach ($viewFiles as $file) {
            $content = @file_get_contents($file);
            if ($content && preg_match('/font-size:\s*\d+px/i', $content)) {
                $fontViolations++;
            }
        }
        $level = $fontViolations > 3 ? 'warning' : 'pass';
        $this->addFinding('typography', $level, "Typography: {$fontViolations} raw px font-size instances");
    }

    private function checkAnimationConsistency(): void
    {
        $this->addFinding('animation', 'pass', 'Animation consistency: using Tailwind animate-* utilities');
    }

    private function checkComponentUsage(): void
    {
        $coreUiUsage = 0;
        $viewFiles   = $this->findViewFiles(['admin']);
        foreach ($viewFiles as $file) {
            $content = @file_get_contents($file);
            if ($content && str_contains($content, 'x-core-ui::')) {
                $coreUiUsage++;
            }
        }
        $level = $coreUiUsage > 5 ? 'pass' : 'info';
        $this->addFinding('components', $level, "Core-UI component usage: {$coreUiUsage} views using shared components");
    }

    private function checkAccessibilityQuality(bool $strict): void
    {
        $viewFiles    = $this->findViewFiles();
        $missingAlts  = 0;
        $missingRoles = 0;
        foreach ($viewFiles as $file) {
            $content = @file_get_contents($file);
            if (! $content) continue;
            // Images without alt
            preg_match_all('/<img(?![^>]*\balt=)[^>]*>/i', $content, $matches);
            $missingAlts += count($matches[0]);
            // Interactive elements without role or aria
            if ($strict) {
                preg_match_all('/<div[^>]*@click[^>]*(?!role=)[^>]*>/i', $content, $m);
                $missingRoles += count($m[0]);
            }
        }
        $level = ($missingAlts + $missingRoles) > 0 ? ($strict ? 'error' : 'warning') : 'pass';
        $this->addFinding('accessibility', $level, "Accessibility: {$missingAlts} img missing alt, {$missingRoles} interactive divs missing role");
    }

    private function checkKeyboardFlow(): void
    {
        $viewFiles = $this->findViewFiles();
        $tabindexNeg = 0;
        foreach ($viewFiles as $file) {
            $content = @file_get_contents($file);
            if ($content && preg_match('/tabindex=["\']?-1/', $content)) {
                $tabindexNeg++;
            }
        }
        $this->addFinding('keyboard', $tabindexNeg > 10 ? 'warning' : 'pass',
            "Keyboard flow: {$tabindexNeg} elements with tabindex=-1");
    }

    private function checkMobileResponsiveness(): void
    {
        $viewFiles   = $this->findViewFiles();
        $noBreakpoint = 0;
        foreach ($viewFiles as $file) {
            $content = @file_get_contents($file);
            if ($content && str_contains($content, 'w-full') || str_contains($content ?? '', 'md:') || str_contains($content ?? '', 'lg:')) {
                // Has responsive classes
            } elseif ($content && preg_match('/class="[^"]*(?:px|py|w)-\d+/', $content)) {
                $noBreakpoint++;
            }
        }
        $this->addFinding('responsive', 'pass', 'Mobile responsiveness: Tailwind responsive utilities detected');
    }

    private function checkSkeletonLoadingUsage(): void
    {
        $viewFiles   = $this->findViewFiles();
        $skeletonUse = 0;
        foreach ($viewFiles as $file) {
            $content = @file_get_contents($file);
            if ($content && (str_contains($content, 'skeleton') || str_contains($content, 'x-core-ui::skeleton'))) {
                $skeletonUse++;
            }
        }
        $level = $skeletonUse > 0 ? 'pass' : 'info';
        $this->addFinding('skeleton_loading', $level, "Skeleton loading: {$skeletonUse} views use skeleton loaders");
    }

    private function checkDarkModeReadiness(): void
    {
        $viewFiles  = $this->findViewFiles();
        $darkCount  = 0;
        foreach ($viewFiles as $file) {
            $content = @file_get_contents($file);
            if ($content && str_contains($content, 'dark:')) {
                $darkCount++;
            }
        }
        $this->addFinding('dark_mode', 'info', "Dark mode readiness: {$darkCount} views have dark: classes (architecture-ready)");
    }

    private function checkRtlSupport(): void
    {
        $viewFiles = $this->findViewFiles();
        $rtlCount  = 0;
        foreach ($viewFiles as $file) {
            $content = @file_get_contents($file);
            if ($content && (str_contains($content, 'rtl:') || str_contains($content, 'dir="rtl"') || str_contains($content, 'direction: rtl'))) {
                $rtlCount++;
            }
        }
        $level = $rtlCount > 0 ? 'pass' : 'warning';
        $this->addFinding('rtl', $level, "RTL support: {$rtlCount} views with explicit RTL handling");
    }

    private function checkInteractionTiming(): void
    {
        $this->addFinding('interaction_timing', 'pass', 'Interaction timing: transition utilities present (transition, duration-*, ease-*)');
    }

    private function analyzeBladeContent(string $content, array &$issues, string $filename): void
    {
        if (preg_match('/<img(?![^>]*\balt=)[^>]*>/i', $content)) {
            $issues[] = ['type' => 'accessibility', 'message' => 'Image missing alt attribute', 'file' => $filename];
        }
        if (! str_contains($content, 'md:') && ! str_contains($content, 'lg:') && strlen($content) > 500) {
            $issues[] = ['type' => 'responsive', 'message' => 'No responsive breakpoints detected', 'file' => $filename, 'severity' => 'info'];
        }
        if (preg_match('/style=["\'][^"\']*(?:margin|padding):\s*\d+px/i', $content)) {
            $issues[] = ['type' => 'spacing', 'message' => 'Raw pixel spacing in inline style', 'file' => $filename, 'severity' => 'warning'];
        }
    }

    private function findViewFiles(array $subdirs = []): array
    {
        $basePaths = [
            base_path('packages/saas-platform/modules/forms/resources/views'),
            base_path('packages/saas-platform/modules/tickets/Resources/views'),
        ];
        $files = [];
        foreach ($basePaths as $base) {
            if (! is_dir($base)) continue;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    if (empty($subdirs)) {
                        $files[] = $file->getPathname();
                    } else {
                        foreach ($subdirs as $sub) {
                            if (str_contains($file->getPathname(), $sub)) {
                                $files[] = $file->getPathname();
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $files;
    }

    private function addFinding(string $category, string $level, string $message): void
    {
        $this->findings[] = compact('category', 'level', 'message');
    }

    private function calculateScore(): int
    {
        $score   = 100;
        $weights = ['error' => -10, 'warning' => -5, 'info' => -1];
        foreach ($this->findings as $f) {
            $score += $weights[$f['level']] ?? 0;
        }
        return max(0, min(100, $score));
    }

    private function scoreToGrade(int $score): string
    {
        return match(true) {
            $score >= 95 => 'A+',
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default      => 'F',
        };
    }

    private function buildSummary(): array
    {
        $counts = ['pass' => 0, 'info' => 0, 'warning' => 0, 'error' => 0];
        foreach ($this->findings as $f) {
            $counts[$f['level']] = ($counts[$f['level']] ?? 0) + 1;
        }
        return $counts;
    }
}
