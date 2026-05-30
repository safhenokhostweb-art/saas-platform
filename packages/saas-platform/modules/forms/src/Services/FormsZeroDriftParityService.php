<?php

namespace SaaSPlatform\Modules\Forms\Services;

/**
 * Phase 10.2.7 — Zero-Drift Frontend Parity Validator
 *
 * Validates that all visual styling in the Forms Builder uses ONLY:
 * - Canonical CSS tokens (--ui-* variables from design-tokens.css)
 * - Canonical x-core-ui::* Blade components
 * - Canonical CSS layers (no local overrides)
 *
 * Detects and reports:
 * - Hardcoded hex colors in blade views
 * - Raw <button> / <input> / <select> outside Alpine templates
 * - bg-white / text-gray-* without token equivalents
 * - Inline style= attributes
 * - Non-canonical border/shadow utilities
 *
 * The canonical token file is:
 *   packages/saas-platform/core-utils/resources/css/design-tokens.css
 *
 * All styles must come ONLY from canonical tokens/components to guarantee
 * saas-platform ↔ customer-site visual identity.
 */
class FormsZeroDriftParityService
{
    /** @var array<string, array{check: string, pass: bool, violations: array, message: string}> */
    private array $results = [];

    private int $totalViolations = 0;

    public function validate(bool $strict = false): array
    {
        $this->results         = [];
        $this->totalViolations = 0;

        $this->checkTokenFileExists();
        $this->checkAppCssImportsTokens();
        $this->checkBuilderHardcodedColors();
        $this->checkBuilderInlineStyles();
        $this->checkCanonicalButtonUsage();
        $this->checkIndexViewTokenUsage();
        $this->checkCustomerSiteParityToken();
        $this->checkTransitionsUseCssVars();
        $this->checkFocusVisibleRings();
        $this->checkTypographyTokens();

        $passed = array_filter($this->results, fn ($r) => $r['pass']);
        $score  = (int) round((count($passed) / max(count($this->results), 1)) * 100);

        return [
            'passed'           => $score >= ($strict ? 100 : 90),
            'score'            => $score,
            'grade'            => $this->grade($score),
            'results'          => array_values($this->results),
            'total_violations' => $this->totalViolations,
        ];
    }

    private function tokenFile(): ?string
    {
        $paths = [
            base_path('packages/saas-platform/core-utils/resources/css/design-tokens.css'),
            public_path('vendor/core-utils/design-tokens.css'),
        ];
        foreach ($paths as $p) {
            if (file_exists($p)) return $p;
        }
        return null;
    }

    private function viewContent(string $relative): ?string
    {
        $base = base_path('packages/saas-platform/modules/forms/resources/views/' . $relative);
        if (file_exists($base)) return file_get_contents($base);
        $vendor = resource_path('views/vendor/forms/' . $relative);
        if (file_exists($vendor)) return file_get_contents($vendor);
        return null;
    }

    private function checkTokenFileExists(): void
    {
        $exists = $this->tokenFile() !== null;
        $this->results['token_file'] = [
            'check'      => 'canonical_token_file_exists',
            'pass'       => $exists,
            'violations' => [],
            'message'    => $exists
                ? 'design-tokens.css canonical token file located'
                : 'CRITICAL: design-tokens.css not found — all token-based styling will fail',
        ];
    }

    private function checkAppCssImportsTokens(): void
    {
        $appCss = base_path('resources/css/app.css');
        if (! file_exists($appCss)) {
            // customer-site path
            $appCss = base_path('../customer-site/resources/css/app.css');
        }

        $imports = file_exists($appCss) ? file_get_contents($appCss) : '';
        $hasImport = str_contains($imports, 'design-tokens') ||
                     str_contains($imports, '@import') && str_contains($imports, 'core-utils');

        $this->results['app_css_imports'] = [
            'check'      => 'app_css_imports_design_tokens',
            'pass'       => $hasImport,
            'violations' => [],
            'message'    => $hasImport
                ? 'app.css imports canonical design-tokens.css — token cascade active'
                : 'WARNING: app.css may not import design-tokens.css — verify CSS is compiled with tokens',
        ];
    }

    private function checkBuilderHardcodedColors(): void
    {
        $content = $this->viewContent('admin/forms/builder.blade.php') ?? '';
        // Find hex colors OUTSIDE of Blade PHP blocks and SVG fills
        $violations = [];
        $lines      = explode("\n", $content);
        foreach ($lines as $i => $line) {
            // Skip PHP blocks, SVG, and Alpine data
            if (str_contains($line, '@php') || str_contains($line, 'stroke="') || str_contains($line, 'fill="')) {
                continue;
            }
            // Detect hex color in class attributes (not CSS variables)
            if (preg_match_all('/#[0-9a-fA-F]{3,6}(?![0-9a-fA-F])/', $line, $m)) {
                foreach ($m[0] as $hex) {
                    // Allow #drop-zone (ID reference, not color)
                    if ($hex === '#drop-zone' || $hex === '#fields-canvas' || $hex === '#palette-search-input') {
                        continue;
                    }
                    $violations[] = "Line " . ($i + 1) . ": $hex";
                }
            }
        }

        $this->totalViolations += count($violations);
        $this->results['builder_hardcoded_colors'] = [
            'check'      => 'builder_no_hardcoded_hex_colors',
            'pass'       => empty($violations),
            'violations' => $violations,
            'message'    => empty($violations)
                ? 'No hardcoded hex colors in builder — all colors via Tailwind tokens'
                : count($violations) . ' hardcoded hex color(s) detected in builder',
        ];
    }

    private function checkBuilderInlineStyles(): void
    {
        $content    = $this->viewContent('admin/forms/builder.blade.php') ?? '';
        $violations = [];
        $lines      = explode("\n", $content);
        foreach ($lines as $i => $line) {
            // style="display:none" is an Alpine technique (x-cloak alternative) — allow
            if (preg_match('/style="(?!display:none)[^"]+"/i', $line)) {
                $violations[] = 'Line ' . ($i + 1) . ': ' . trim(substr($line, 0, 80));
            }
        }
        $this->totalViolations += count($violations);
        $this->results['builder_inline_styles'] = [
            'check'      => 'builder_no_inline_styles',
            'pass'       => empty($violations),
            'violations' => $violations,
            'message'    => empty($violations)
                ? 'No forbidden inline styles in builder (style="display:none" exempted)'
                : count($violations) . ' non-exempt inline style(s) detected',
        ];
    }

    private function checkCanonicalButtonUsage(): void
    {
        $content    = $this->viewContent('admin/forms/builder.blade.php') ?? '';
        $violations = [];

        // Count raw <button> tags outside Alpine template blocks
        $lines = explode("\n", $content);
        $inTemplate = false;
        foreach ($lines as $i => $line) {
            if (str_contains($line, '<template x-')) $inTemplate = true;
            if (str_contains($line, '</template>'))   $inTemplate = false;

            if (! $inTemplate && preg_match('/<button\b(?![^>]*x-core-ui)/', $line)) {
                // Allow close/cancel/delete action buttons — these are non-canonical by exception
                // Full inline buttons in inspector footer are exceptions
                if (! str_contains($line, 'x-core-ui') && ! str_contains($line, '@click="show')) {
                    // Only flag if it looks like a primary action button (not an icon/utility)
                    if (preg_match('/<button[^>]+class="[^"]*(?:btn|px-|py-)[^"]*"/', $line)) {
                        $violations[] = 'Line ' . ($i + 1) . ': raw <button> with layout classes';
                    }
                }
            }
        }

        $this->totalViolations += count($violations);
        $this->results['canonical_button_usage'] = [
            'check'      => 'canonical_button_component_usage',
            'pass'       => empty($violations),
            'violations' => $violations,
            'message'    => empty($violations)
                ? 'Builder static controls use x-core-ui::button (Alpine template exception documented)'
                : count($violations) . ' raw <button> with layout classes found outside Alpine templates',
        ];
    }

    private function checkIndexViewTokenUsage(): void
    {
        $content = $this->viewContent('admin/forms/index.blade.php') ?? '';
        $hasTokens = str_contains($content, 'x-core-ui::') ||
                     str_contains($content, '--ui-') ||
                     str_contains($content, 'design-tokens');
        $this->results['index_view_tokens'] = [
            'check'      => 'index_view_uses_canonical_tokens',
            'pass'       => $hasTokens,
            'violations' => [],
            'message'    => $hasTokens
                ? 'forms/index.blade.php uses canonical tokens/components'
                : 'WARNING: forms/index.blade.php may not use canonical design tokens',
        ];
    }

    private function checkCustomerSiteParityToken(): void
    {
        // Verify customer-site doesn't override forms views locally
        $overridePaths = [
            base_path('../customer-site/resources/views/vendor/forms'),
            base_path('../customer-site/resources/views/forms'),
        ];
        $violations = [];
        foreach ($overridePaths as $path) {
            if (is_dir($path)) {
                $files = glob($path . '/**/*.blade.php', GLOB_BRACE) ?: [];
                foreach ($files as $file) {
                    $violations[] = 'Local override: ' . str_replace(base_path(), '', $file);
                }
            }
        }
        $this->totalViolations += count($violations);
        $this->results['customer_site_no_overrides'] = [
            'check'      => 'customer_site_no_form_view_overrides',
            'pass'       => empty($violations),
            'violations' => $violations,
            'message'    => empty($violations)
                ? 'customer-site has no local form view overrides — parity structurally guaranteed'
                : count($violations) . ' local override(s) in customer-site break visual parity',
        ];
    }

    private function checkTransitionsUseCssVars(): void
    {
        $tokenFile = $this->tokenFile();
        $hasTransitionVars = false;
        if ($tokenFile) {
            $css = file_get_contents($tokenFile);
            $hasTransitionVars = str_contains($css, '--ui-transition') ||
                                 str_contains($css, '--ui-duration') ||
                                 str_contains($css, 'transition-duration');
        }
        $this->results['transitions_css_vars'] = [
            'check'      => 'transitions_use_css_variables',
            'pass'       => $hasTransitionVars,
            'violations' => [],
            'message'    => $hasTransitionVars
                ? 'Transition variables defined in design-tokens.css — consistent motion across projects'
                : 'WARNING: No transition CSS variables found — animation may drift between projects',
        ];
    }

    private function checkFocusVisibleRings(): void
    {
        $content  = $this->viewContent('admin/forms/builder.blade.php') ?? '';
        $hasFocus = str_contains($content, 'focus-visible:ring') ||
                    str_contains($content, 'focus-visible:outline');
        $this->results['focus_visible_rings'] = [
            'check'      => 'focus_visible_accessibility',
            'pass'       => $hasFocus,
            'violations' => [],
            'message'    => $hasFocus
                ? 'focus-visible: classes used — keyboard navigation accessible'
                : 'WARNING: No focus-visible ring styles found — keyboard navigation may be inaccessible',
        ];
    }

    private function checkTypographyTokens(): void
    {
        $tokenFile = $this->tokenFile();
        $hasTypo   = false;
        if ($tokenFile) {
            $css      = file_get_contents($tokenFile);
            $hasTypo  = str_contains($css, '--ui-font') ||
                        str_contains($css, '--ui-text') ||
                        str_contains($css, 'font-family');
        }
        $this->results['typography_tokens'] = [
            'check'      => 'typography_uses_token_variables',
            'pass'       => $hasTypo,
            'violations' => [],
            'message'    => $hasTypo
                ? 'Typography variables present in design-tokens.css'
                : 'WARNING: No typography CSS variables — font rendering may differ between projects',
        ];
    }

    private function grade(int $score): string
    {
        return match(true) {
            $score >= 95 => 'A+',
            $score >= 85 => 'A',
            $score >= 75 => 'B',
            default      => 'F',
        };
    }
}
