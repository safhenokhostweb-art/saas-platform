<?php

namespace SaaSPlatform\Modules\Forms\Services;

/**
 * Phase 10.2.7 — Builder Alpine Validation
 *
 * Scans the builder Blade view for Alpine expressions that are mistakenly
 * bound with Blade's PHP-evaluated `:` prefix on Blade component props.
 *
 * The failure mode: `:disabled="historyIndex <= 0"` on <x-core-ui::button>
 * causes PHP to evaluate `historyIndex` as a constant → Fatal Error in PHP 8.
 *
 * Rule: Alpine variables must use `x-bind:attr` on Blade components, never `:attr`.
 */
class BuilderAlpineValidationService
{
    /**
     * Known Blade component props that can only receive PHP values.
     * Any Alpine variable passed via `:prop` to these will crash PHP 8.
     */
    private const COMPONENT_PROPS = [
        'core-ui::button'  => ['disabled', 'loading', 'variant', 'size', 'type', 'href', 'icon', 'ariaLabel'],
        'core-ui::input'   => ['name', 'type', 'disabled', 'required', 'label', 'error', 'placeholder'],
        'core-ui::select'  => ['name', 'disabled', 'required', 'label'],
        'core-ui::checkbox'=> ['name', 'disabled', 'required', 'label', 'checked'],
    ];

    /**
     * Alpine variable names that will crash PHP when used as Blade prop values.
     * These are JS runtime variables — they do not exist in PHP scope.
     */
    private const ALPINE_VARIABLES = [
        'historyIndex', 'history', 'fields', 'selectedField', 'selectedFieldId',
        'form', 'saveStatus', 'toasts', 'activeTab', 'paletteSearch',
        'isDraggingOver', 'draggingType', 'showTemplateModal', 'showDeleteConfirm',
        'showKeyboardHelp', 'previewMode', 'hasDraft', 'draftTimestamp',
    ];

    /** @var array<int, array{type: string, line: int, attr: string, expression: string, fix: string}> */
    private array $violations = [];

    private int $checkedLines = 0;

    public function validate(bool $strict = false): array
    {
        $this->violations = [];
        $this->checkedLines = 0;

        $viewPath = $this->resolveBuilderViewPath();

        if (! $viewPath || ! file_exists($viewPath)) {
            return $this->buildReport(false, $strict, 'Builder view not found: ' . ($viewPath ?? 'null'));
        }

        $content = file_get_contents($viewPath);
        $this->scanForPhpEvaluatedAlpineBindings($content, $viewPath);
        $this->scanForUnsafeBladeInterpolation($content);
        $this->scanForMalformedXBindExpressions($content);
        $this->scanForInvalidXModelOnComponents($content);

        $passed = count($this->violations) === 0;
        $score  = $passed ? 100 : max(0, 100 - (count($this->violations) * 15));

        return $this->buildReport($passed, $strict, null, $score);
    }

    /**
     * Find `:propName="expr"` on Blade components where `expr` contains Alpine variables.
     */
    private function scanForPhpEvaluatedAlpineBindings(string $content, string $path): void
    {
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $this->checkedLines++;

            // Detect component opening tags  <x-component-name ...>
            if (! preg_match('/<x-[\w::-]+/', $line)) {
                continue;
            }

            // Extract all :attr="expr" bindings on this line
            if (preg_match_all('/:([\w-]+)="([^"]+)"/', $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    [, $attr, $expr] = $match;

                    // Skip safe PHP expressions (true/false/null/numbers/strings)
                    if ($this->isSafePhpExpression($expr)) {
                        continue;
                    }

                    // Check if the expression contains Alpine variables
                    foreach (self::ALPINE_VARIABLES as $alpineVar) {
                        if (str_contains($expr, $alpineVar)) {
                            $this->violations[] = [
                                'type'       => 'php_evaluated_alpine_binding',
                                'line'       => $lineNum + 1,
                                'attr'       => $attr,
                                'expression' => $expr,
                                'fix'        => "Change `:$attr=\"$expr\"` to `x-bind:$attr=\"$expr\"`",
                                'severity'   => 'CRITICAL — causes PHP Fatal Error in PHP 8',
                            ];
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * Find {{ $phpVar }} inside Alpine x-bind expressions (injection risk / wrong value).
     */
    private function scanForUnsafeBladeInterpolation(string $content): void
    {
        // Pattern: x-bind:attr="{{ $phpVar }}" — PHP var echoed into Alpine expression
        // This is sometimes OK (route, CSRF) but risky inside conditional Alpine expressions
        if (preg_match_all('/x-bind:[^=]+="\{\{[^}]+\}\}[^"]*"/', $content, $matches)) {
            foreach ($matches[0] as $m) {
                // Flag if the interpolation is mixed with Alpine vars (hybrid risk)
                if (preg_match('/\$[a-z]+.*\?|.*\?.*\$[a-z]+/', $m)) {
                    $this->violations[] = [
                        'type'       => 'unsafe_blade_interpolation_in_alpine',
                        'attr'       => 'mixed',
                        'expression' => substr($m, 0, 80),
                        'fix'        => 'Separate PHP values into JS globals or data-* attributes; do not mix Blade {{ }} inside Alpine ternaries',
                        'severity'   => 'WARNING',
                    ];
                }
            }
        }
    }

    /**
     * Find x-bind expressions with obvious syntax errors (unmatched brackets etc).
     */
    private function scanForMalformedXBindExpressions(string $content): void
    {
        if (preg_match_all('/x-bind:[^=]+="([^"]+)"/', $content, $matches)) {
            foreach ($matches[1] as $expr) {
                $open  = substr_count($expr, '(') + substr_count($expr, '{') + substr_count($expr, '[');
                $close = substr_count($expr, ')') + substr_count($expr, '}') + substr_count($expr, ']');
                if ($open !== $close) {
                    $this->violations[] = [
                        'type'       => 'malformed_x_bind_expression',
                        'attr'       => 'unknown',
                        'expression' => substr($expr, 0, 60),
                        'fix'        => 'Balance all brackets/braces in x-bind expression',
                        'severity'   => 'ERROR',
                    ];
                }
            }
        }
    }

    /**
     * Detect x-model on Blade component props that won't wire correctly
     * (x-model must target an HTML element, not a Blade component directly).
     */
    private function scanForInvalidXModelOnComponents(string $content): void
    {
        // x-model on x-core-ui::select / checkbox is OK because they pass through $attributes.
        // Flag x-model on x-core-ui::button (which renders <button>, not an input).
        if (preg_match_all('/<x-core-ui::button[^>]+x-model[^>]+>/', $content, $matches)) {
            foreach ($matches[0] as $m) {
                $this->violations[] = [
                    'type'       => 'x_model_on_button_component',
                    'attr'       => 'x-model',
                    'expression' => substr($m, 0, 80),
                    'fix'        => 'x-model on <button> has no effect — use @click to toggle state',
                    'severity'   => 'WARNING',
                ];
            }
        }
    }

    private function isSafePhpExpression(string $expr): bool
    {
        $expr = trim($expr);
        // Pure booleans, null, integers, floats
        if (in_array(strtolower($expr), ['true', 'false', 'null', '1', '0'], true)) {
            return true;
        }
        // Pure numeric
        if (is_numeric($expr)) {
            return true;
        }
        // PHP variable (starts with $)
        if (str_starts_with($expr, '$')) {
            return true;
        }
        // String literals
        if ((str_starts_with($expr, "'") && str_ends_with($expr, "'")) ||
            (str_starts_with($expr, '"') && str_ends_with($expr, '"'))) {
            return true;
        }
        return false;
    }

    private function resolveBuilderViewPath(): ?string
    {
        $paths = [
            base_path('packages/saas-platform/modules/forms/resources/views/admin/forms/builder.blade.php'),
            resource_path('views/vendor/forms/admin/forms/builder.blade.php'),
        ];

        foreach ($paths as $p) {
            if (file_exists($p)) {
                return $p;
            }
        }
        return null;
    }

    private function buildReport(bool $passed, bool $strict, ?string $error = null, int $score = 100): array
    {
        return [
            'passed'      => $passed && (! $strict || $score === 100),
            'score'       => $score,
            'grade'       => $score >= 95 ? 'A+' : ($score >= 85 ? 'A' : ($score >= 75 ? 'B' : 'C')),
            'violations'  => $this->violations,
            'checked_lines' => $this->checkedLines,
            'error'       => $error,
            'summary'     => $passed
                ? "No Alpine/Blade binding violations found ({$this->checkedLines} lines checked)"
                : count($this->violations) . " violation(s) found — builder will crash on open",
        ];
    }
}
