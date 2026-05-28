<?php

namespace SaaSPlatform\CoreUtils\Frontend;

use Illuminate\Support\Facades\File;

/**
 * Phase 10.2 — Canonical Component Authority Service
 *
 * Detects every category of frontend governance violation:
 * - Unauthorized raw HTML controls (button/input/select/textarea)
 * - Hardcoded hex / rgba colors
 * - Forbidden inline style= attributes
 * - Duplicated modal implementations
 * - Forbidden Tailwind color utilities
 * - Non-canonical component structures
 */
class CanonicalComponentAuthorityService
{
    private array $violations  = [];
    private array $warnings    = [];
    private array $passed      = [];

    /** Raw HTML tags that must be replaced with x-core-ui::* equivalents */
    private const RAW_CONTROLS = [
        'button' => 'x-core-ui::button',
        'input'  => 'x-core-ui::input',
        'select' => 'x-core-ui::select',
        'textarea' => 'x-core-ui::textarea',
    ];

    /** Patterns that indicate hardcoded colors */
    private const COLOR_PATTERNS = [
        '/#[0-9a-fA-F]{3,8}\b/',
        '/rgba?\s*\(\s*\d/',
        '/hsl\s*\(/',
    ];

    /** Tailwind utilities that must be migrated to token-driven classes */
    private const FORBIDDEN_TW_COLORS = [
        'bg-blue-500','bg-blue-600','bg-blue-700',
        'bg-red-500','bg-red-600','bg-red-700',
        'bg-green-500','bg-green-600',
        'text-blue-500','text-blue-600','text-blue-700',
        'text-red-500','text-red-600',
        'text-green-500','text-green-600',
        'border-blue-500','border-red-500','border-green-500',
    ];

    /** Blade view paths to audit */
    private function viewPaths(): array
    {
        return array_filter([
            'core-utils'   => base_path('packages/saas-platform/core-utils/resources/views'),
            'forms'        => base_path('packages/saas-platform/modules/forms/resources/views'),
            'tickets'      => base_path('packages/saas-platform/modules/tickets/Resources/views'),
            'admin'        => resource_path('views'),
            'customer-site'=> base_path('../customer-site/resources/views'),
        ], 'is_dir');
    }

    public function validate(bool $strict = false): array
    {
        $this->violations = [];
        $this->warnings   = [];
        $this->passed     = [];

        foreach ($this->viewPaths() as $module => $path) {
            $this->auditDirectory($module, $path);
        }

        $score = $this->calculateScore();

        return [
            'score'      => $score,
            'grade'      => $this->grade($score),
            'violations' => $this->violations,
            'warnings'   => $this->warnings,
            'passed'     => $this->passed,
            'summary'    => [
                'total_violations' => count($this->violations),
                'total_warnings'   => count($this->warnings),
                'total_passed'     => count($this->passed),
                'raw_control_violations'  => $this->countCategory('raw_control'),
                'hardcoded_color_violations' => $this->countCategory('hardcoded_color'),
                'inline_style_violations' => $this->countCategory('inline_style'),
                'forbidden_tw_violations' => $this->countCategory('forbidden_tw'),
            ],
        ];
    }

    private function auditDirectory(string $module, string $path): void
    {
        $files = File::allFiles($path);
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $this->auditFile($module, $file->getPathname());
        }
    }

    private function auditFile(string $module, string $filepath): void
    {
        $content  = file_get_contents($filepath);
        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $filepath);
        $lines    = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $ln = $lineNum + 1;

            // Skip Blade comment lines and PHP comment lines
            if (preg_match('/^\s*({{--|\/\/)/', $line)) continue;

            $this->checkRawControls($module, $relative, $ln, $line);
            $this->checkInlineStyles($module, $relative, $ln, $line);
            $this->checkHardcodedColors($module, $relative, $ln, $line);
            $this->checkForbiddenTailwind($module, $relative, $ln, $line);
        }

        $this->checkDuplicateModals($module, $relative, $content);
    }

    private function checkRawControls(string $module, string $file, int $line, string $content): void
    {
        // Allow raw controls inside <x-core-ui::*> component definitions themselves
        if (str_contains($file, 'core-utils/resources/views/components/')) return;

        foreach (array_keys(self::RAW_CONTROLS) as $tag) {
            if (preg_match('/<' . $tag . '[\s>\/]/', $content)) {
                $canonical = self::RAW_CONTROLS[$tag];
                $this->violations[] = [
                    'category' => 'raw_control',
                    'module'   => $module,
                    'file'     => $file,
                    'line'     => $line,
                    'message'  => "Raw <{$tag}> found — replace with {$canonical}",
                    'snippet'  => trim(substr($content, 0, 120)),
                ];
            }
        }
    }

    private function checkInlineStyles(string $module, string $file, int $line, string $content): void
    {
        // Allow in CSS files and migration comment lines
        if (str_ends_with($file, '.css')) return;

        if (preg_match('/\bstyle\s*=\s*["\']/', $content)) {
            $this->violations[] = [
                'category' => 'inline_style',
                'module'   => $module,
                'file'     => $file,
                'line'     => $line,
                'message'  => 'Inline style= attribute detected — use --ui-* CSS vars',
                'snippet'  => trim(substr($content, 0, 120)),
            ];
        }
    }

    private function checkHardcodedColors(string $module, string $file, int $line, string $content): void
    {
        // Only scan <style> blocks and style= attributes in blade files
        if (!preg_match('/style\s*=\s*["\']|<style/', $content)) return;

        foreach (self::COLOR_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->violations[] = [
                    'category' => 'hardcoded_color',
                    'module'   => $module,
                    'file'     => $file,
                    'line'     => $line,
                    'message'  => 'Hardcoded color value detected — migrate to var(--ui-*)',
                    'snippet'  => trim(substr($content, 0, 120)),
                ];
                break;
            }
        }
    }

    private function checkForbiddenTailwind(string $module, string $file, int $line, string $content): void
    {
        foreach (self::FORBIDDEN_TW_COLORS as $cls) {
            if (str_contains($content, $cls)) {
                $this->warnings[] = [
                    'category' => 'forbidden_tw',
                    'module'   => $module,
                    'file'     => $file,
                    'line'     => $line,
                    'message'  => "Forbidden Tailwind utility [{$cls}] — use token-driven class",
                    'snippet'  => trim(substr($content, 0, 120)),
                ];
                break;
            }
        }
    }

    private function checkDuplicateModals(string $module, string $file, string $content): void
    {
        // Detect raw modal patterns outside canonical component files
        if (str_contains($file, 'core-utils/resources/views/components/')) return;

        $modalPatterns = [
            '/role\s*=\s*["\']dialog["\']/',
            '/aria-modal\s*=\s*["\']true["\']/',
        ];

        $matches = 0;
        foreach ($modalPatterns as $p) {
            if (preg_match($p, $content)) $matches++;
        }

        if ($matches >= 2) {
            $this->warnings[] = [
                'category' => 'duplicate_modal',
                'module'   => $module,
                'file'     => $file,
                'line'     => 0,
                'message'  => 'Possible raw modal implementation — use x-core-ui::modal',
                'snippet'  => '',
            ];
        }
    }

    private function countCategory(string $category): int
    {
        return count(array_filter(
            array_merge($this->violations, $this->warnings),
            fn($v) => $v['category'] === $category
        ));
    }

    private function calculateScore(): int
    {
        $totalFiles = 0;
        foreach ($this->viewPaths() as $path) {
            $totalFiles += count(array_filter(
                File::allFiles($path),
                fn($f) => $f->getExtension() === 'php'
            ));
        }

        if ($totalFiles === 0) return 100;

        $violationWeight = count($this->violations) * 3;
        $warningWeight   = count($this->warnings);
        $deduction       = min(100, $violationWeight + $warningWeight);

        return max(0, 100 - (int) round($deduction / max(1, $totalFiles / 10)));
    }

    private function grade(int $score): string
    {
        return match(true) {
            $score >= 97 => 'A+',
            $score >= 93 => 'A',
            $score >= 90 => 'A-',
            $score >= 87 => 'B+',
            $score >= 83 => 'B',
            $score >= 80 => 'B-',
            $score >= 70 => 'C',
            default      => 'F',
        };
    }
}
