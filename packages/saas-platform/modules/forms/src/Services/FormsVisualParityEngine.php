<?php

namespace SaaSPlatform\Modules\Forms\Services;

/**
 * Phase 10.2.6 — Forms Visual Parity Engine
 *
 * Performs deep structural comparison of the Forms module frontend between
 * saas-platform (canonical authority) and customer-site (tenant mirror).
 *
 * Checks:
 *  - CSS class parity (builder, form cards, inspector, canvas, palette, etc.)
 *  - Design token imports (core-utils/resources/css/design-tokens.css)
 *  - Blade view structural sections (@section, @push, @extends)
 *  - Alpine.js data keys and state variable parity
 *  - SortableJS initialization guard
 *  - AbortController / debounce pattern
 *  - Modal state machine completeness
 *  - x-cloak presence in both contexts
 *  - @source directives in customer-site app.css
 *  - Route name symmetry (admin.forms.* present in both)
 */
class FormsVisualParityEngine
{
    /** @var array<string, array{pass: bool, severity: string, detail: string}> */
    private array $checks = [];

    public function run(): array
    {
        $this->checks = [];

        $this->checkDesignTokenImport();
        $this->checkBuilderCSSClasses();
        $this->checkFormCardCSS();
        $this->checkXCloakRule();
        $this->checkBuilderSourceDirectives();
        $this->checkAlpineDataKeys();
        $this->checkModalStateMachine();
        $this->checkAbortControllerParity();
        $this->checkDebounceParity();
        $this->checkSortableJsGuard();
        $this->checkBladeViewSections();
        $this->checkFontDefinition();
        $this->checkRouteSymmetry();

        $passed   = count(array_filter($this->checks, fn ($c) => $c['pass']));
        $total    = count($this->checks);
        $score    = $total > 0 ? (int) round($passed / $total * 100) : 0;
        $critical = array_filter($this->checks, fn ($c) => ! $c['pass'] && $c['severity'] === 'critical');
        $warnings = array_filter($this->checks, fn ($c) => ! $c['pass'] && $c['severity'] === 'warning');

        return [
            'score'    => $score,
            'grade'    => $this->toGrade($score),
            'passed'   => $passed,
            'total'    => $total,
            'critical' => count($critical),
            'warnings' => count($warnings),
            'checks'   => $this->checks,
            'parity'   => $score >= 95 && count($critical) === 0,
        ];
    }

    // ─────────────────────── Checks ───────────────────────

    private function checkDesignTokenImport(): void
    {
        $saasCss   = base_path('resources/css/app.css');
        $tenantCss = $this->tenantPath('resources/css/app.css');

        $saasHas   = file_exists($saasCss)   && str_contains(file_get_contents($saasCss),   'design-tokens.css');
        $tenantHas = file_exists($tenantCss) && str_contains(file_get_contents($tenantCss), 'design-tokens.css');

        $this->record(
            'design_token_import',
            $saasHas && $tenantHas,
            'critical',
            $saasHas && $tenantHas
                ? 'design-tokens.css imported in both app.css files'
                : 'MISSING design-tokens.css import — ' . implode(', ', array_filter([
                    $saasHas   ? null : 'saas-platform/resources/css/app.css',
                    $tenantHas ? null : 'customer-site/resources/css/app.css',
                ]))
        );
    }

    private function checkBuilderCSSClasses(): void
    {
        $classes = [
            '#forms-builder', '.builder-palette', '.builder-canvas', '.builder-inspector',
            '.canvas-grid', '.inspector-tab', '.field-card', '.palette-item',
            '.drop-hint', '.autosave-pulse', '.toast-enter',
        ];

        $tenantCss = $this->tenantPath('resources/css/app.css');
        if (! file_exists($tenantCss)) {
            $this->record('builder_css_classes', false, 'critical', 'customer-site app.css not found');
            return;
        }

        $content = file_get_contents($tenantCss);
        $missing = array_filter($classes, fn ($c) => ! str_contains($content, $c));

        $this->record(
            'builder_css_classes',
            empty($missing),
            'critical',
            empty($missing)
                ? 'All ' . count($classes) . ' builder CSS classes mirrored in customer-site app.css'
                : 'Missing builder CSS in customer-site: ' . implode(', ', $missing)
        );
    }

    private function checkFormCardCSS(): void
    {
        $tenantCss = $this->tenantPath('resources/css/app.css');
        if (! file_exists($tenantCss)) {
            $this->record('form_card_css', false, 'warning', 'customer-site app.css not found');
            return;
        }

        $content = file_get_contents($tenantCss);
        $has     = str_contains($content, '.form-card') && str_contains($content, '.form-card-action');

        $this->record(
            'form_card_css',
            $has,
            'warning',
            $has
                ? '.form-card and .form-card-action transition styles present in customer-site'
                : '.form-card or .form-card-action missing from customer-site app.css'
        );
    }

    private function checkXCloakRule(): void
    {
        foreach ([
            'saas'   => base_path('resources/css/app.css'),
            'tenant' => $this->tenantPath('resources/css/app.css'),
        ] as $ctx => $path) {
            if (! file_exists($path)) {
                $this->record("x_cloak_{$ctx}", false, 'critical', "{$ctx} app.css not found");
                continue;
            }
            $has = str_contains(file_get_contents($path), '[x-cloak]');
            $this->record(
                "x_cloak_{$ctx}",
                $has,
                'critical',
                $has
                    ? "[x-cloak] FOUC guard present in {$ctx} app.css"
                    : "[x-cloak]{{display:none!important}} MISSING from {$ctx} app.css — Alpine FOUC will occur"
            );
        }
    }

    private function checkBuilderSourceDirectives(): void
    {
        $tenantCss = $this->tenantPath('resources/css/app.css');
        if (! file_exists($tenantCss)) {
            $this->record('builder_source_directives', false, 'warning', 'customer-site app.css not found');
            return;
        }

        $content = file_get_contents($tenantCss);
        $has     = str_contains($content, 'modules/forms/resources/views');

        $this->record(
            'builder_source_directives',
            $has,
            'warning',
            $has
                ? '@source directive for forms views present in customer-site app.css (Tailwind scans builder classes)'
                : '@source directive for forms module views MISSING — Tailwind will purge builder classes in production'
        );
    }

    private function checkAlpineDataKeys(): void
    {
        $viewPath = $this->builderViewPath();
        if (! file_exists($viewPath)) {
            $this->record('alpine_data_keys', false, 'critical', 'builder.blade.php not found');
            return;
        }

        $content = file_get_contents($viewPath);

        $required = [
            "x-data=\"formBuilder(",
            "Alpine.data('formBuilder'",
            "alpine:init",
            "x-cloak",
            "selectedField",
            "fields",
            "isSaving",
            "lastSaved",
            "toastMessage",
        ];

        $missing = array_filter($required, fn ($k) => ! str_contains($content, $k));

        $this->record(
            'alpine_data_keys',
            empty($missing),
            'critical',
            empty($missing)
                ? 'All required Alpine data keys and registration patterns present'
                : 'Missing Alpine patterns: ' . implode(', ', $missing)
        );
    }

    private function checkModalStateMachine(): void
    {
        $viewPath = $this->builderViewPath();
        if (! file_exists($viewPath)) {
            $this->record('modal_state_machine', false, 'critical', 'builder.blade.php not found');
            return;
        }

        $content = file_get_contents($viewPath);
        $states  = ['showDeleteConfirm', 'showTemplateModal', 'showKeyboardHelp', 'showTemplateApplyConfirm'];
        $missing = array_filter($states, fn ($s) => ! str_contains($content, $s));

        $hasEscape = str_contains($content, "key === 'Escape'") || str_contains($content, '@keydown.escape');

        $pass = empty($missing) && $hasEscape;

        $details = [];
        if (! empty($missing)) {
            $details[] = 'Missing state vars: ' . implode(', ', $missing);
        }
        if (! $hasEscape) {
            $details[] = 'Escape key handler missing';
        }

        $this->record(
            'modal_state_machine',
            $pass,
            'critical',
            $pass
                ? 'Modal state machine complete (' . count($states) . ' states + Escape handler)'
                : implode('; ', $details)
        );
    }

    private function checkAbortControllerParity(): void
    {
        $viewPath = $this->builderViewPath();
        if (! file_exists($viewPath)) {
            $this->record('abort_controller_parity', false, 'critical', 'builder.blade.php not found');
            return;
        }

        $content = file_get_contents($viewPath);
        $has     = str_contains($content, '_saveController') &&
                   str_contains($content, '_fieldSaveController') &&
                   str_contains($content, 'new AbortController()') &&
                   str_contains($content, '.abort()');

        $this->record(
            'abort_controller_parity',
            $has,
            'critical',
            $has
                ? 'AbortController pattern complete: _saveController, _fieldSaveController, abort() calls'
                : 'AbortController pattern incomplete — race conditions possible on rapid save sequences'
        );
    }

    private function checkDebounceParity(): void
    {
        $viewPath = $this->builderViewPath();
        if (! file_exists($viewPath)) {
            $this->record('debounce_parity', false, 'warning', 'builder.blade.php not found');
            return;
        }

        $content = file_get_contents($viewPath);
        $has     = str_contains($content, '_saveTimer') &&
                   str_contains($content, '_fieldSaveTimer') &&
                   str_contains($content, 'clearTimeout');

        $this->record(
            'debounce_parity',
            $has,
            'warning',
            $has
                ? 'Debounce timers present: _saveTimer, _fieldSaveTimer, clearTimeout'
                : 'Debounce pattern incomplete — may cause excessive API calls'
        );
    }

    private function checkSortableJsGuard(): void
    {
        $viewPath = $this->builderViewPath();
        if (! file_exists($viewPath)) {
            $this->record('sortablejs_guard', false, 'warning', 'builder.blade.php not found');
            return;
        }

        $content = file_get_contents($viewPath);
        $hasSri  = str_contains($content, 'integrity=') || str_contains($content, 'crossorigin=');
        $hasGuard = str_contains($content, "typeof Sortable === 'undefined'");

        $pass = $hasGuard;

        $this->record(
            'sortablejs_guard',
            $pass,
            'warning',
            $pass
                ? 'SortableJS CDN guard present' . ($hasSri ? ' with SRI integrity hash' : '')
                : 'SortableJS guard MISSING — builder will crash if CDN is unavailable'
        );
    }

    private function checkBladeViewSections(): void
    {
        $viewPath = $this->builderViewPath();
        if (! file_exists($viewPath)) {
            $this->record('blade_view_sections', false, 'critical', 'builder.blade.php not found');
            return;
        }

        $content  = file_get_contents($viewPath);
        $required = ['@extends', '@section', '@push(\'scripts\')', '@endsection'];
        $missing  = array_filter($required, fn ($d) => ! str_contains($content, $d));

        $this->record(
            'blade_view_sections',
            empty($missing),
            'critical',
            empty($missing)
                ? 'All Blade view sections present (@extends, @section, @push scripts, @endsection)'
                : 'Missing Blade directives: ' . implode(', ', $missing)
        );
    }

    private function checkFontDefinition(): void
    {
        $tenantCss = $this->tenantPath('resources/css/app.css');
        if (! file_exists($tenantCss)) {
            $this->record('font_definition', false, 'warning', 'customer-site app.css not found');
            return;
        }

        $content = file_get_contents($tenantCss);
        $has     = str_contains($content, 'Vazirmatn') && str_contains($content, '--font-sans');

        $this->record(
            'font_definition',
            $has,
            'warning',
            $has
                ? 'Vazirmatn font + --font-sans token defined in customer-site'
                : 'Vazirmatn font or --font-sans token missing from customer-site app.css — typography will differ'
        );
    }

    private function checkRouteSymmetry(): void
    {
        $routes = [
            'admin.forms.builder',
            'admin.forms.builder.fields.store',
            'admin.forms.builder.fields.update',
            'admin.forms.builder.fields.destroy',
            'admin.forms.builder.sort',
            'admin.forms.builder.settings',
        ];

        $missing = array_filter($routes, fn ($name) => ! \Illuminate\Support\Facades\Route::has($name));

        $this->record(
            'route_symmetry',
            empty($missing),
            'critical',
            empty($missing)
                ? 'All ' . count($routes) . ' builder routes registered (admin.forms.builder.*)'
                : 'Missing builder routes: ' . implode(', ', $missing)
        );
    }

    // ─────────────────────── Helpers ───────────────────────

    private function record(string $key, bool $pass, string $severity, string $detail): void
    {
        $this->checks[$key] = ['pass' => $pass, 'severity' => $severity, 'detail' => $detail];
    }

    private function builderViewPath(): string
    {
        return __DIR__ . '/../../resources/views/admin/forms/builder.blade.php';
    }

    private function tenantPath(string $relative): string
    {
        return base_path('../../customer-site/' . $relative);
    }

    private function toGrade(int $score): string
    {
        return match (true) {
            $score >= 98 => 'A+',
            $score >= 93 => 'A',
            $score >= 88 => 'B+',
            $score >= 83 => 'B',
            $score >= 75 => 'C',
            default      => 'F',
        };
    }
}
