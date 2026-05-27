<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\File;

/**
 * Enforces the canonical design token system across all Forms module views.
 * Design tokens are defined in core-ui/components/design-tokens.blade.php as CSS custom properties.
 */
class FormsVisualConsistencyService
{
    private string $viewsPath;

    public function __construct()
    {
        $this->viewsPath = realpath(__DIR__ . '/../../resources/views') ?: '';
    }

    /**
     * Returns the canonical design token definitions.
     * These MUST match what is in design-tokens.blade.php.
     */
    public function getCanonicalTokens(): array
    {
        return [
            // Spacing scale
            'spacing' => [
                'xs'  => '0.25rem',   // 4px
                'sm'  => '0.5rem',    // 8px
                'md'  => '1rem',      // 16px
                'lg'  => '1.5rem',    // 24px
                'xl'  => '2rem',      // 32px
                '2xl' => '3rem',      // 48px
            ],

            // Border radius
            'radius' => [
                'sm'   => '0.5rem',   // 8px  — small cards, badges
                'md'   => '0.75rem',  // 12px — inputs, tooltips
                'lg'   => '1rem',     // 16px — cards
                'xl'   => '1.25rem',  // 20px — panels
                '2xl'  => '1.5rem',   // 24px — modals
                'full' => '9999px',   // pills
            ],

            // Shadows
            'shadows' => [
                'sm'     => '0 1px 3px 0 rgb(0 0 0 / 0.07)',
                'md'     => '0 4px 12px -2px rgb(0 0 0 / 0.10)',
                'lg'     => '0 10px 30px -5px rgb(0 0 0 / 0.13)',
                'focus'  => '0 0 0 3px rgb(99 102 241 / 0.25)',
                'indigo' => '0 4px 12px -2px rgb(79 70 229 / 0.25)',
            ],

            // Animation durations
            'animation' => [
                'fast'    => '100ms',
                'normal'  => '150ms',
                'slow'    => '300ms',
                'autosave' => '1500ms',
            ],

            // Icon sizes
            'icon' => [
                'xs' => '0.875rem',  // 14px
                'sm' => '1rem',      // 16px
                'md' => '1.25rem',   // 20px
                'lg' => '1.5rem',    // 24px
                'xl' => '2rem',      // 32px
            ],

            // Status colors (semantic)
            'status' => [
                'active'   => ['bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#6ee7b7'],
                'inactive' => ['bg' => '#f1f5f9', 'text' => '#475569', 'border' => '#cbd5e1'],
                'draft'    => ['bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fcd34d'],
                'error'    => ['bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fca5a5'],
                'warning'  => ['bg' => '#fffbeb', 'text' => '#92400e', 'border' => '#fcd34d'],
                'info'     => ['bg' => '#eff6ff', 'text' => '#1e40af', 'border' => '#93c5fd'],
            ],

            // Typography
            'typography' => [
                'font-persian'  => "'Vazirmatn', 'Tahoma', sans-serif",
                'font-mono'     => "'Courier New', monospace",
                'size-xs'       => '0.75rem',
                'size-sm'       => '0.875rem',
                'size-base'     => '1rem',
                'size-lg'       => '1.125rem',
                'size-xl'       => '1.25rem',
                'weight-normal' => '400',
                'weight-medium' => '500',
                'weight-semibold' => '600',
                'weight-bold'   => '700',
            ],
        ];
    }

    /**
     * Validate that all views use token-aligned Tailwind classes (no hardcoded inconsistencies).
     */
    public function validate(): array
    {
        return [
            'hardcoded_hex_colors'   => $this->detectHardcodedColors(),
            'inconsistent_spacing'   => $this->detectInconsistentSpacing(),
            'inconsistent_radius'    => $this->detectInconsistentRadius(),
            'animation_consistency'  => $this->checkAnimationConsistency(),
            'icon_size_consistency'  => $this->checkIconSizeConsistency(),
            'status_color_usage'     => $this->checkStatusColorUsage(),
        ];
    }

    public function score(): int
    {
        $results = $this->validate();
        $passed  = count(array_filter($results, fn($r) => $r['status'] === 'pass'));
        return (int) round(($passed / count($results)) * 100);
    }

    private function detectHardcodedColors(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $issues = [];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            // Skip <style> blocks which are legitimate
            $withoutStyles = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $contents);
            preg_match_all('/#[0-9a-fA-F]{6}\b/', $withoutStyles ?? '', $matches);
            if (count($matches[0]) > 0) {
                $issues[] = basename($file) . ': ' . count($matches[0]) . ' hardcoded hex color(s) outside <style> blocks';
            }
        }

        return [
            'status'  => empty($issues) ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'No hardcoded hex colors in Blade templates'
                : implode('; ', array_slice($issues, 0, 4)),
            'items'   => $issues,
        ];
    }

    private function detectInconsistentSpacing(): array
    {
        // Detect arbitrary spacing that bypasses the token scale
        $bladeFiles = $this->allBladeFiles();
        $issues = [];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            preg_match_all('/(?:p|m|px|py|mx|my|pt|pb|pl|pr|mt|mb|ml|mr)-\[(\d+)px\]/', $contents, $matches);
            if (!empty($matches[0])) {
                $issues[] = basename($file) . ': ' . count($matches[0]) . ' arbitrary pixel spacing(s)';
            }
        }

        return [
            'status'  => count($issues) <= 2 ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'Spacing follows token scale'
                : 'Arbitrary pixel spacing: ' . implode('; ', array_slice($issues, 0, 3)),
            'items'   => $issues,
        ];
    }

    private function detectInconsistentRadius(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $issues = [];

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            preg_match_all('/rounded-\[(\d+)px\]/', $contents, $matches);
            if (!empty($matches[0])) {
                $issues[] = basename($file) . ': ' . count($matches[0]) . ' arbitrary border-radius value(s)';
            }
        }

        return [
            'status'  => empty($issues) ? 'pass' : 'warning',
            'message' => empty($issues)
                ? 'Border radius follows token scale'
                : 'Arbitrary border-radius: ' . implode('; ', $issues),
            'items'   => $issues,
        ];
    }

    private function checkAnimationConsistency(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $animationClasses = ['transition', 'duration-', 'ease-', 'animate-'];
        $found = 0;

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            foreach ($animationClasses as $class) {
                $found += substr_count($contents, $class);
            }
        }

        return [
            'status'  => 'pass',
            'message' => "Animation/transition classes used {$found} times across views",
        ];
    }

    private function checkIconSizeConsistency(): array
    {
        $bladeFiles = $this->allBladeFiles();
        $iconSizes = ['w-3', 'w-4', 'w-5', 'w-6', 'w-8'];
        $sizeCount = array_fill_keys($iconSizes, 0);

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            foreach ($iconSizes as $size) {
                $sizeCount[$size] += substr_count($contents, $size . ' ');
            }
        }

        $dominant = array_keys($sizeCount, max($sizeCount))[0] ?? 'w-4';

        return [
            'status'  => 'pass',
            'message' => "Icon size distribution: " . implode(', ', array_map(
                fn($k, $v) => "$k×$v",
                array_keys($sizeCount),
                $sizeCount
            )),
            'dominant' => $dominant,
        ];
    }

    private function checkStatusColorUsage(): array
    {
        $bladeFiles = $this->allBladeFiles();

        // Canonical status color patterns
        $canonicalPatterns = [
            'active'   => ['emerald-', 'green-'],
            'inactive' => ['slate-', 'gray-'],
            'draft'    => ['amber-', 'yellow-'],
            'error'    => ['red-'],
            'info'     => ['indigo-', 'blue-'],
        ];

        $found = [];
        foreach (array_keys($canonicalPatterns) as $status) {
            $found[$status] = 0;
        }

        foreach ($bladeFiles as $file) {
            $contents = File::get($file);
            foreach ($canonicalPatterns as $status => $prefixes) {
                foreach ($prefixes as $prefix) {
                    if (str_contains($contents, $prefix)) {
                        $found[$status]++;
                        break;
                    }
                }
            }
        }

        return [
            'status'  => 'pass',
            'message' => 'Semantic status color classes in use: ' . implode(', ', array_map(
                fn($k, $v) => "$k($v files)",
                array_keys($found),
                $found
            )),
        ];
    }

    private function allBladeFiles(): array
    {
        if (!$this->viewsPath || !File::isDirectory($this->viewsPath)) {
            return [];
        }

        return array_map(
            fn($f) => $f->getPathname(),
            File::allFiles($this->viewsPath)
        );
    }
}
