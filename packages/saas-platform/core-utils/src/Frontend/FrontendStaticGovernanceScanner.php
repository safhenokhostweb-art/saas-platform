<?php

namespace SaaSPlatform\CoreUtils\Frontend;

/**
 * Phase 10.2.5 — Task 3
 * Multi-language static governance scanner.
 *
 * Expands governance scanning beyond Blade to cover ALL frontend artifact types:
 *  - Blade (.blade.php)
 *  - PHP (controller/livewire inline HTML)
 *  - Alpine.js (inline x-* directives in HTML)
 *  - JavaScript (.js)
 *  - TypeScript (.ts)
 *  - CSS / PostCSS (.css)
 *  - Tailwind config (tailwind.config.js)
 *  - Livewire components
 *  - Future Vue/React compatibility stubs
 *
 * Detects:
 *  - Raw controls outside canonical components
 *  - Inline styles
 *  - Hardcoded hex / named colors
 *  - Forbidden z-index values
 *  - Forbidden animation/transition definitions
 *  - Unsafe custom components
 *  - Design token bypasses
 *  - Unauthorized CSS custom properties
 *  - Duplicate UI primitives
 *  - Direct Tailwind color classes that bypass tokens
 */
class FrontendStaticGovernanceScanner
{
    private const SCAN_EXTENSIONS = ['blade.php', 'php', 'js', 'ts', 'css', 'vue', 'jsx', 'tsx'];

    private const FORBIDDEN_RAW_TAGS = ['button', 'input', 'select', 'textarea'];

    private const FORBIDDEN_TAILWIND_COLORS = [
        'text-red-', 'text-green-', 'text-blue-', 'text-yellow-', 'text-gray-',
        'text-purple-', 'text-pink-', 'text-indigo-', 'text-orange-',
        'bg-red-', 'bg-green-', 'bg-blue-', 'bg-yellow-', 'bg-gray-',
        'bg-purple-', 'bg-pink-', 'bg-indigo-', 'bg-orange-',
        'border-red-', 'border-green-', 'border-blue-', 'border-yellow-',
        'border-gray-', 'border-purple-', 'border-pink-', 'border-indigo-',
    ];

    private const FORBIDDEN_Z_INDEX_VALUES = [
        'z-index: 100', 'z-index: 200', 'z-index: 300', 'z-index: 400',
        'z-index: 500', 'z-index: 1000', 'z-index: 9999', 'z-index: 99999',
    ];

    private const FORBIDDEN_ANIMATION_PATTERNS = [
        '/animation:\s+[^v][^\n;]+[0-9]+(ms|s)\s/i',
        '/transition:\s+[^v][^\n;]+[0-9]+(ms|s)\s/i',
        '/animation-duration:\s+[0-9]+(ms|s)/i',
        '/transition-duration:\s+[0-9]+(ms|s)/i',
    ];

    private const CANONICAL_CSS_VAR_PREFIX = '--ui-';

    private const ALLOWED_NON_TOKEN_CSS_VARS = [
        '--tw-', '--vp-', '--forms-',
    ];

    private array $violations = [];
    private array $scannedFiles = [];

    public function scan(array $paths, bool $strict = false): array
    {
        $this->violations  = [];
        $this->scannedFiles = [];

        foreach ($paths as $path) {
            if (!file_exists($path)) continue;

            if (is_dir($path)) {
                $this->scanDirectory($path, $strict);
            } else {
                $this->scanFile($path, $strict);
            }
        }

        $totalViolations = count($this->violations);
        $byType          = $this->groupByType();
        $bySeverity      = $this->groupBySeverity();
        $passed          = $strict
            ? ($totalViolations === 0)
            : (count(array_filter($this->violations, fn($v) => $v['severity'] === 'CRITICAL')) === 0);

        return [
            'passed'        => $passed,
            'total_files'   => count($this->scannedFiles),
            'total_violations' => $totalViolations,
            'violations'    => $this->violations,
            'by_type'       => $byType,
            'by_severity'   => $bySeverity,
            'scanned_paths' => $paths,
            'summary'       => $this->buildSummary($totalViolations, $passed, $bySeverity),
        ];
    }

    private function scanDirectory(string $dir, bool $strict): void
    {
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                $dir,
                \RecursiveDirectoryIterator::SKIP_DOTS
            ));

            foreach ($it as $file) {
                if (!$file->isFile()) continue;

                $name = $file->getFilename();

                // Check if extension matches any we scan
                foreach (self::SCAN_EXTENSIONS as $ext) {
                    if (str_ends_with($name, '.' . $ext) || $name === 'tailwind.config.js') {
                        $this->scanFile($file->getPathname(), $strict);
                        break;
                    }
                }
            }
        } catch (\Throwable) {
            // Directory unreadable — skip
        }
    }

    private function scanFile(string $path, bool $strict): void
    {
        if (!is_readable($path) || filesize($path) > 1_000_000) return;

        $content = file_get_contents($path);
        if ($content === false) return;

        $this->scannedFiles[] = $path;
        $ext  = $this->getExtension($path);
        $rel  = $this->relativePath($path);

        // Route to extension-specific scanners
        if (in_array($ext, ['blade.php', 'php', 'vue', 'jsx', 'tsx', 'html'])) {
            $this->scanHtmlArtifact($rel, $content, $strict);
        }

        if (in_array($ext, ['css'])) {
            $this->scanCssArtifact($rel, $content, $strict);
        }

        if (in_array($ext, ['js', 'ts', 'jsx', 'tsx'])) {
            $this->scanJsArtifact($rel, $content, $strict);
        }

        if (str_ends_with($path, 'tailwind.config.js') || str_ends_with($path, 'tailwind.config.ts')) {
            $this->scanTailwindConfig($rel, $content);
        }

        // Universal checks (all file types)
        $this->scanUniversal($rel, $content, $ext, $strict);
    }

    private function scanHtmlArtifact(string $path, string $content, bool $strict): void
    {
        // Skip core-ui component definitions (they define the controls themselves)
        if (str_contains($path, 'core-utils/resources/views/components/')) return;

        // Raw control detection
        foreach (self::FORBIDDEN_RAW_TAGS as $tag) {
            preg_match_all("/<{$tag}[\s>\/]/i", $content, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as [$match, $offset]) {
                // Skip Alpine x-for template blocks
                $surroundingContext = substr($content, max(0, $offset - 200), 400);
                if (str_contains($surroundingContext, '<template x-for')) continue;
                // Skip Livewire method calls that contain tag names as strings
                if (str_contains($surroundingContext, "'{$tag}'") || str_contains($surroundingContext, "\"{$tag}\"")) continue;

                $this->addViolation($path, 'raw_control', 'CRITICAL',
                    "Raw <{$tag}> element — use x-core-ui::{$tag}",
                    "Replace <{$tag}> with <x-core-ui::{$tag}>"
                );
            }
        }

        // Inline style with hardcoded hex color
        preg_match_all('/style\s*=\s*["\'][^"\']*#[0-9a-fA-F]{3,8}/i', $content, $matches);
        foreach ($matches[0] as $match) {
            $this->addViolation($path, 'hardcoded_color', 'HIGH',
                "Inline style with hardcoded hex color: {$match}",
                'Use var(--ui-*) CSS custom properties instead of hex values'
            );
        }

        // Direct Tailwind color classes bypassing tokens (in class= attributes)
        if ($strict) {
            foreach (self::FORBIDDEN_TAILWIND_COLORS as $colorClass) {
                if (str_contains($content, $colorClass)) {
                    $this->addViolation($path, 'tailwind_color_bypass', 'MEDIUM',
                        "Direct Tailwind color class '{$colorClass}*' bypasses design tokens",
                        "Use token-mapped utility or CSS var: e.g., text-[color:var(--ui-primary)]"
                    );
                }
            }
        }
    }

    private function scanCssArtifact(string $path, string $content, bool $strict): void
    {
        // Skip canonical token file itself
        if (str_contains($path, 'design-tokens.css')) return;

        // Hardcoded hex colors in CSS
        preg_match_all('/(color|background|border|fill|stroke)\s*:\s*#[0-9a-fA-F]{3,8}/i', $content, $matches);
        foreach ($matches[0] as $match) {
            $this->addViolation($path, 'hardcoded_color', 'HIGH',
                "Hardcoded hex color in CSS: {$match}",
                'Replace with var(--ui-*) token'
            );
        }

        // Forbidden z-index hardcoded values
        foreach (self::FORBIDDEN_Z_INDEX_VALUES as $forbidden) {
            if (str_contains($content, $forbidden)) {
                $this->addViolation($path, 'hardcoded_z_index', 'HIGH',
                    "Hardcoded z-index value: {$forbidden}",
                    'Use var(--ui-z-*) tokens'
                );
            }
        }

        // Forbidden animation definitions (not using tokens)
        foreach (self::FORBIDDEN_ANIMATION_PATTERNS as $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $this->addViolation($path, 'hardcoded_animation', 'MEDIUM',
                    "Hardcoded animation/transition duration: {$m[0]}",
                    'Use var(--ui-duration-*) or var(--ui-ease-*) tokens; wrap in prefers-reduced-motion'
                );
            }
        }

        // Unauthorized CSS custom properties (not --ui-* or --tw-* or --forms-*)
        preg_match_all('/--[a-z][a-z0-9-]+\s*:/i', $content, $matches);
        foreach ($matches[0] as $var) {
            $varName = rtrim($var, ':');
            $allowed = false;
            foreach ([self::CANONICAL_CSS_VAR_PREFIX, ...self::ALLOWED_NON_TOKEN_CSS_VARS] as $prefix) {
                if (str_starts_with($varName, $prefix)) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed && $strict) {
                $this->addViolation($path, 'unauthorized_css_var', 'MEDIUM',
                    "Unauthorized CSS variable definition: {$varName} (not --ui-* namespace)",
                    "Prefix module-specific variables with --ui-* or request token addition"
                );
            }
        }
    }

    private function scanJsArtifact(string $path, string $content, bool $strict): void
    {
        // Skip vendor/node_modules
        if (str_contains($path, 'node_modules') || str_contains($path, '/vendor/')) return;

        // Hardcoded hex in JS (in template literals or string assignments)
        preg_match_all('/["\']#[0-9a-fA-F]{6}["\']/', $content, $matches);
        foreach ($matches[0] as $match) {
            $this->addViolation($path, 'hardcoded_color', 'MEDIUM',
                "Hardcoded hex color string in JS/TS: {$match}",
                'Use CSS var references: getComputedStyle(el).getPropertyValue("--ui-*")'
            );
        }

        // Detecting inline document.createElement of raw controls
        foreach (self::FORBIDDEN_RAW_TAGS as $tag) {
            if (str_contains($content, "createElement('{$tag}')") ||
                str_contains($content, "createElement(\"{$tag}\")")) {
                $this->addViolation($path, 'raw_control', 'HIGH',
                    "JS dynamically creates raw <{$tag}> element",
                    'Use Alpine.js x-core-ui components or Livewire canonical components'
                );
            }
        }
    }

    private function scanTailwindConfig(string $path, string $content): void
    {
        // Tailwind config must extend theme, not override colors
        if (preg_match('/colors\s*:\s*\{[^}]{20,}/s', $content) &&
            !str_contains($content, 'extend')) {
            $this->addViolation($path, 'tailwind_color_override', 'CRITICAL',
                'tailwind.config overrides the entire colors palette — canonical token classes will be lost',
                "Use theme.extend.colors to ADD colors rather than replacing the palette"
            );
        }

        // Must not define custom animation without reduced-motion consideration
        if (str_contains($content, 'animation') && !str_contains($content, 'prefers-reduced-motion')) {
            $this->addViolation($path, 'animation_no_motion_preference', 'MEDIUM',
                'Tailwind config defines animations without prefers-reduced-motion variants',
                'Add motion-safe / motion-reduce variant support to animation definitions'
            );
        }
    }

    private function scanUniversal(string $path, string $content, string $ext, bool $strict): void
    {
        // Duplicate UI primitive detection: local re-implementations of canonical components
        $duplicatePrimitives = [
            'class UIButton',
            'class AppButton',
            'class CustomInput',
            'const Button = ',
            'const Input = ',
            'const Modal = ',
            'function Button(',
            'function Input(',
        ];

        foreach ($duplicatePrimitives as $pattern) {
            if (str_contains($content, $pattern) && !str_contains($path, 'node_modules')) {
                $this->addViolation($path, 'duplicate_ui_primitive', 'HIGH',
                    "Duplicate UI primitive detected: '{$pattern}' — shadows canonical x-core-ui component",
                    'Remove local primitive; use x-core-ui::* canonical component system'
                );
            }
        }
    }

    private function addViolation(string $path, string $type, string $severity, string $message, string $fix): void
    {
        $this->violations[] = compact('path', 'type', 'severity', 'message', 'fix');
    }

    private function groupByType(): array
    {
        $groups = [];
        foreach ($this->violations as $v) {
            $groups[$v['type']] = ($groups[$v['type']] ?? 0) + 1;
        }
        arsort($groups);
        return $groups;
    }

    private function groupBySeverity(): array
    {
        $groups = ['CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
        foreach ($this->violations as $v) {
            $groups[$v['severity']] = ($groups[$v['severity']] ?? 0) + 1;
        }
        return $groups;
    }

    private function buildSummary(int $total, bool $passed, array $bySeverity): string
    {
        $status = $passed ? 'PASS' : 'FAIL';
        return sprintf(
            '[%s] %d violation(s) — CRITICAL: %d, HIGH: %d, MEDIUM: %d',
            $status, $total,
            $bySeverity['CRITICAL'] ?? 0,
            $bySeverity['HIGH'] ?? 0,
            $bySeverity['MEDIUM'] ?? 0
        );
    }

    private function getExtension(string $path): string
    {
        if (str_ends_with($path, '.blade.php')) return 'blade.php';
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    private function relativePath(string $path): string
    {
        $base = base_path();
        return str_replace([$base . DIRECTORY_SEPARATOR, $base . '/'], '', $path);
    }
}
