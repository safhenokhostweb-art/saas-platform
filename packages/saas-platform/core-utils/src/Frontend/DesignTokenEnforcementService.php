<?php

namespace SaaSPlatform\CoreUtils\Frontend;

use Illuminate\Support\Facades\File;

/**
 * Phase 10.2 — Design Token Enforcement Service
 *
 * Validates full token authority across 6 dimensions:
 * - Color authority:     no hex/rgba outside CSS vars
 * - Spacing authority:   no raw px/rem values in style= attrs
 * - Typography authority:no raw font-size/weight in style= attrs
 * - Motion authority:    no raw transition/animation values
 * - Z-index authority:   no raw z-index values
 * - Shadow authority:    no raw box-shadow values
 */
class DesignTokenEnforcementService
{
    private array $violations = [];
    private array $dimensions = [];

    private const CSS_FILES = [
        'saas-platform/app.css'      => 'resources/css/app.css',
        'design-tokens.css'          => 'packages/saas-platform/core-utils/resources/css/design-tokens.css',
    ];

    private const BLADE_DIRS = [
        'forms'   => 'packages/saas-platform/modules/forms/resources/views',
        'tickets' => 'packages/saas-platform/modules/tickets/Resources/views',
        'admin'   => 'resources/views',
    ];

    /** Patterns that must NOT appear in inline style= values */
    private const FORBIDDEN_INLINE = [
        'color'      => '/#[0-9a-fA-F]{3,8}|rgba?\s*\(/',
        'spacing'    => '/(?<!\w)(margin|padding)\s*:\s*\d+px/',
        'typography' => '/font-size\s*:\s*\d/',
        'motion'     => '/(?<!\w)(transition|animation)\s*:\s*[^;]+(?:ms|s)\b/',
        'z-index'    => '/z-index\s*:\s*\d/',
        'shadow'     => '/box-shadow\s*:\s*\d/',
    ];

    /** Required --ui-* token references in design-tokens.css */
    private const REQUIRED_TOKEN_PREFIXES = [
        '--ui-primary-', '--ui-indigo', '--ui-purple',
        '--ui-bg', '--ui-surface', '--ui-border',
        '--ui-text-primary', '--ui-shadow-',
        '--ui-radius-', '--ui-space-',
        '--ui-duration-', '--ui-z-',
    ];

    public function validate(): array
    {
        $this->violations = [];
        $this->dimensions = [];

        $this->validateTokenFile();
        $this->validateBladeInlineStyles();
        $this->validateCssTokenUsage();

        $score = $this->computeScore();

        return [
            'score'      => $score,
            'grade'      => $this->grade($score),
            'dimensions' => $this->dimensions,
            'violations' => $this->violations,
            'summary'    => [
                'total_violations'  => count($this->violations),
                'token_file_valid'  => $this->dimensions['token_file'] ?? false,
                'inline_clean'      => ($this->dimensions['inline_violations'] ?? 1) === 0,
                'css_token_usage'   => $this->dimensions['css_token_coverage'] ?? 0,
            ],
        ];
    }

    private function validateTokenFile(): void
    {
        $tokenPath = base_path(self::CSS_FILES['design-tokens.css']);

        if (!file_exists($tokenPath)) {
            $this->violations[] = ['dimension' => 'token_file', 'message' => 'design-tokens.css not found'];
            $this->dimensions['token_file'] = false;
            return;
        }

        $content = file_get_contents($tokenPath);
        $missing = [];

        foreach (self::REQUIRED_TOKEN_PREFIXES as $prefix) {
            if (!str_contains($content, $prefix)) {
                $missing[] = $prefix;
            }
        }

        if ($missing) {
            $this->violations[] = [
                'dimension' => 'token_file',
                'message'   => 'Missing token prefixes: ' . implode(', ', $missing),
            ];
            $this->dimensions['token_file'] = false;
        } else {
            $this->dimensions['token_file'] = true;
        }

        // Count total tokens defined
        preg_match_all('/--ui-[\w-]+\s*:/', $content, $m);
        $this->dimensions['total_tokens_defined'] = count($m[0]);
    }

    private function validateBladeInlineStyles(): void
    {
        $inlineViolations = 0;

        foreach (self::BLADE_DIRS as $module => $relPath) {
            $dir = base_path($relPath);
            if (!is_dir($dir)) continue;

            foreach (File::allFiles($dir) as $file) {
                if ($file->getExtension() !== 'php') continue;

                $content = file_get_contents($file->getPathname());
                $lines   = explode("\n", $content);

                foreach ($lines as $lineNum => $line) {
                    if (!preg_match('/\bstyle\s*=\s*["\']([^"\']+)["\']/', $line, $m)) continue;

                    $styleValue = $m[1];
                    foreach (self::FORBIDDEN_INLINE as $dim => $pattern) {
                        if (preg_match($pattern, $styleValue)) {
                            $inlineViolations++;
                            $this->violations[] = [
                                'dimension' => $dim,
                                'module'    => $module,
                                'file'      => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()),
                                'line'      => $lineNum + 1,
                                'message'   => "Inline {$dim} value — migrate to var(--ui-*)",
                                'snippet'   => trim(substr($line, 0, 120)),
                            ];
                        }
                    }
                }
            }
        }

        $this->dimensions['inline_violations'] = $inlineViolations;
    }

    private function validateCssTokenUsage(): void
    {
        $appCss = base_path(self::CSS_FILES['saas-platform/app.css']);
        if (!file_exists($appCss)) {
            $this->dimensions['css_token_coverage'] = 0;
            return;
        }

        $content = file_get_contents($appCss);

        preg_match_all('/var\(--ui-[\w-]+\)/', $content, $uses);
        preg_match_all('/#[0-9a-fA-F]{3,8}/', $content, $hardcoded);

        $tokenUses    = count($uses[0]);
        $hardcodeUses = count($hardcoded[0]);
        $total        = $tokenUses + $hardcodeUses;

        $coverage = $total > 0 ? (int) round($tokenUses / $total * 100) : 100;
        $this->dimensions['css_token_coverage']   = $coverage;
        $this->dimensions['css_token_uses']       = $tokenUses;
        $this->dimensions['css_hardcoded_colors'] = $hardcodeUses;

        if ($hardcodeUses > 0) {
            $this->violations[] = [
                'dimension' => 'color',
                'message'   => "{$hardcodeUses} hardcoded hex colors remain in app.css",
                'detail'    => array_slice($hardcoded[0], 0, 10),
            ];
        }
    }

    private function computeScore(): int
    {
        $base    = 100;
        $deduct  = count($this->violations) * 4;
        $base   -= min(40, $deduct);

        // Token coverage bonus
        $coverage = $this->dimensions['css_token_coverage'] ?? 0;
        if ($coverage < 70) $base -= 10;
        elseif ($coverage < 85) $base -= 5;

        return max(0, $base);
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
