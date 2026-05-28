<?php

namespace SaaSPlatform\CoreUtils\Frontend;

/**
 * Phase 10.0 — Design Token Governance Service
 * Verifies canonical token usage, detects drift, and reports token coverage.
 */
class DesignTokenGovernanceService
{
    /** All canonical --ui-* token names that should be in design-tokens.css */
    private const CANONICAL_TOKEN_GROUPS = [
        'color'     => ['--ui-primary-', '--ui-indigo', '--ui-purple', '--ui-success', '--ui-danger', '--ui-warning', '--ui-info', '--ui-neutral'],
        'surface'   => ['--ui-bg', '--ui-surface', '--ui-border', '--ui-text-'],
        'radius'    => ['--ui-radius-'],
        'shadow'    => ['--ui-shadow-'],
        'spacing'   => ['--ui-space-'],
        'typography'=> ['--ui-font-', '--ui-text-', '--ui-weight-', '--ui-leading-', '--ui-tracking-'],
        'motion'    => ['--ui-duration-', '--ui-ease-', '--ui-transition'],
        'z-index'   => ['--ui-z-'],
        'icon'      => ['--ui-icon-'],
        'glass'     => ['--ui-glass-'],
        'admin'     => ['--ui-admin-', '--ui-sidebar-'],
        'compat'    => ['--forms-', '--admin-'],
    ];

    public function auditTokenFile(): array
    {
        $path = realpath(__DIR__ . '/../../resources/css/design-tokens.css');
        if (! $path || ! file_exists($path)) {
            return ['error' => 'design-tokens.css not found'];
        }

        $content  = file_get_contents($path);
        $defined  = [];
        preg_match_all('/--[\w-]+\s*:/m', $content, $matches);

        foreach ($matches[0] as $token) {
            $defined[] = trim(str_replace(':', '', $token));
        }

        $groups = [];
        foreach (self::CANONICAL_TOKEN_GROUPS as $group => $prefixes) {
            $count = 0;
            foreach ($defined as $token) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($token, $prefix)) { $count++; break; }
                }
            }
            $groups[$group] = $count;
        }

        return [
            'total_tokens'  => count($defined),
            'token_groups'  => $groups,
            'has_light_vars'=> str_contains($content, ':root'),
            'has_dark_mode' => str_contains($content, 'prefers-color-scheme'),
            'has_rtl'       => str_contains($content, '--ui-text-align') || str_contains($content, '--ui-dir'),
            'has_keyframes' => str_contains($content, '@keyframes'),
            'has_compat'    => str_contains($content, '--forms-') && str_contains($content, '--admin-'),
        ];
    }

    public function detectHardcodedColors(string $directory): array
    {
        $pattern = '/#[0-9a-fA-F]{3,6}\b/';
        $violations = [];

        if (! is_dir($directory)) return [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $content = file_get_contents($file->getRealPath());
                if (preg_match($pattern, $content, $m)) {
                    // Only flag hex colors NOT inside comment blocks
                    if (! preg_match('/\/\*.*' . preg_quote($m[0], '/') . '.*\*\//s', $content)) {
                        $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                    }
                }
            }
        }

        return array_unique($violations);
    }

    public function tokenCoverageScore(): int
    {
        $audit = $this->auditTokenFile();
        if (isset($audit['error'])) return 0;

        $score = 0;
        if ($audit['total_tokens'] >= 80)  $score += 25;
        elseif ($audit['total_tokens'] >= 50) $score += 15;
        if ($audit['has_light_vars'])       $score += 15;
        if ($audit['has_dark_mode'])        $score += 10;
        if ($audit['has_rtl'])              $score += 10;
        if ($audit['has_keyframes'])        $score += 10;
        if ($audit['has_compat'])           $score += 15;
        if ($audit['total_tokens'] >= 100)  $score += 15;

        return min(100, $score);
    }
}
