<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\Route;

/**
 * Phase 10.2.6 — Builder Parity Validation
 *
 * Validates that the Forms Builder runtime is structurally identical
 * between saas-platform and customer-site contexts.
 */
class FormsBuilderParityValidationService
{
    /** @var array<string, array{pass: bool, message: string}> */
    protected array $results = [];

    protected int $score = 0;

    /**
     * Run all parity checks and return a scored report.
     *
     * @return array{score: int, grade: string, results: array, passed: bool}
     */
    public function validate(bool $strict = false): array
    {
        $this->results = [];

        $this->checkBuilderRouteRegistered();
        $this->checkBuilderFieldRoutes();
        $this->checkBuilderSettingsRoute();
        $this->checkBuilderSortRoute();
        $this->checkBuilderViewExists();
        $this->checkAlpineStateInitializationGuard();
        $this->checkSortableJsLoadGuard();
        $this->checkModalStateVariables();
        $this->checkAbortControllerPattern();
        $this->checkAutosaveDebouncePattern();

        $passed = array_filter($this->results, fn ($r) => $r['pass']);
        $this->score = (int) round((count($passed) / max(count($this->results), 1)) * 100);

        return [
            'score'   => $this->score,
            'grade'   => $this->scoreToGrade($this->score),
            'results' => $this->results,
            'passed'  => $this->score >= ($strict ? 100 : 95),
        ];
    }

    // ─────────────────────── Checks ───────────────────────

    private function checkBuilderRouteRegistered(): void
    {
        $exists = Route::has('admin.forms.builder');
        $this->record(
            'builder_route_registered',
            $exists,
            $exists
                ? 'Route admin.forms.builder is registered'
                : 'Route admin.forms.builder NOT found — builder will 404'
        );
    }

    private function checkBuilderFieldRoutes(): void
    {
        $required = [
            'admin.forms.builder.fields.store',
            'admin.forms.builder.fields.update',
            'admin.forms.builder.fields.destroy',
            'admin.forms.builder.fields.duplicate',
        ];
        $missing = array_filter($required, fn ($name) => ! Route::has($name));
        $this->record(
            'builder_field_routes',
            empty($missing),
            empty($missing)
                ? 'All builder field CRUD routes registered'
                : 'Missing builder field routes: ' . implode(', ', $missing)
        );
    }

    private function checkBuilderSettingsRoute(): void
    {
        $exists = Route::has('admin.forms.builder.settings');
        $this->record(
            'builder_settings_route',
            $exists,
            $exists
                ? 'Route admin.forms.builder.settings registered'
                : 'Route admin.forms.builder.settings MISSING — settings save will fail'
        );
    }

    private function checkBuilderSortRoute(): void
    {
        $exists = Route::has('admin.forms.builder.sort');
        $this->record(
            'builder_sort_route',
            $exists,
            $exists
                ? 'Route admin.forms.builder.sort registered'
                : 'Route admin.forms.builder.sort MISSING — drag/drop order won\'t persist'
        );
    }

    private function checkBuilderViewExists(): void
    {
        $exists = view()->exists('forms::admin.forms.builder');
        $this->record(
            'builder_view_exists',
            $exists,
            $exists
                ? 'Builder Blade view forms::admin.forms.builder found'
                : 'Builder view NOT found — FormsServiceProvider may not have booted'
        );
    }

    private function checkAlpineStateInitializationGuard(): void
    {
        $viewPath = __DIR__ . '/../../resources/views/admin/forms/builder.blade.php';
        if (! file_exists($viewPath)) {
            $this->record('alpine_init_guard', false, 'Builder view file not found');
            return;
        }
        $content = file_get_contents($viewPath);
        $hasAlpineInit  = str_contains($content, "alpine:init");
        $hasFormBuilder = str_contains($content, "Alpine.data('formBuilder'");
        $hasXCloak      = str_contains($content, 'x-cloak');
        $pass = $hasAlpineInit && $hasFormBuilder && $hasXCloak;
        $this->record(
            'alpine_init_guard',
            $pass,
            $pass
                ? 'Alpine.data registration wrapped in alpine:init listener + x-cloak present'
                : 'Missing: ' . implode(', ', array_filter([
                    $hasAlpineInit  ? null : 'alpine:init listener',
                    $hasFormBuilder ? null : 'Alpine.data formBuilder registration',
                    $hasXCloak      ? null : 'x-cloak on root element',
                ]))
        );
    }

    private function checkSortableJsLoadGuard(): void
    {
        $viewPath = __DIR__ . '/../../resources/views/admin/forms/builder.blade.php';
        if (! file_exists($viewPath)) {
            $this->record('sortablejs_guard', false, 'Builder view file not found');
            return;
        }
        $content = file_get_contents($viewPath);
        $hasSortableCheck = str_contains($content, "typeof Sortable === 'undefined'");
        $this->record(
            'sortablejs_guard',
            $hasSortableCheck,
            $hasSortableCheck
                ? 'SortableJS CDN load guard present (graceful fallback if CDN blocked)'
                : 'SortableJS guard MISSING — builder will crash if CDN is unavailable'
        );
    }

    private function checkModalStateVariables(): void
    {
        $viewPath = __DIR__ . '/../../resources/views/admin/forms/builder.blade.php';
        if (! file_exists($viewPath)) {
            $this->record('modal_state_vars', false, 'Builder view file not found');
            return;
        }
        $content = file_get_contents($viewPath);
        $required = [
            'showDeleteConfirm', 'showTemplateModal',
            'showKeyboardHelp', 'showTemplateApplyConfirm',
        ];
        $missing = array_filter($required, fn ($v) => ! str_contains($content, $v));
        $this->record(
            'modal_state_vars',
            empty($missing),
            empty($missing)
                ? 'All modal state variables present'
                : 'Missing modal state variables: ' . implode(', ', $missing)
        );
    }

    private function checkAbortControllerPattern(): void
    {
        $viewPath = __DIR__ . '/../../resources/views/admin/forms/builder.blade.php';
        if (! file_exists($viewPath)) {
            $this->record('abort_controller', false, 'Builder view file not found');
            return;
        }
        $content = file_get_contents($viewPath);
        $hasSaveController  = str_contains($content, '_saveController');
        $hasFieldController = str_contains($content, '_fieldSaveController');
        $hasAbort           = str_contains($content, 'new AbortController()');
        $pass = $hasSaveController && $hasFieldController && $hasAbort;
        $this->record(
            'abort_controller',
            $pass,
            $pass
                ? 'AbortController pattern implemented for both settings and field saves'
                : 'AbortController pattern incomplete — stale requests may cause race conditions'
        );
    }

    private function checkAutosaveDebouncePattern(): void
    {
        $viewPath = __DIR__ . '/../../resources/views/admin/forms/builder.blade.php';
        if (! file_exists($viewPath)) {
            $this->record('autosave_debounce', false, 'Builder view file not found');
            return;
        }
        $content = file_get_contents($viewPath);
        $hasTimer        = str_contains($content, '_saveTimer');
        $hasFieldTimer   = str_contains($content, '_fieldSaveTimer');
        $hasClearTimeout = str_contains($content, 'clearTimeout');
        $pass = $hasTimer && $hasFieldTimer && $hasClearTimeout;
        $this->record(
            'autosave_debounce',
            $pass,
            $pass
                ? 'Debounced autosave implemented with separate timers for settings and fields'
                : 'Autosave debounce incomplete — may cause excessive API calls'
        );
    }

    // ─────────────────────── Helpers ───────────────────────

    private function record(string $key, bool $pass, string $message): void
    {
        $this->results[$key] = ['pass' => $pass, 'message' => $message];
    }

    private function scoreToGrade(int $score): string
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
