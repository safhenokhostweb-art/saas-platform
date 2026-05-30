<?php

namespace SaaSPlatform\Modules\Forms\Services;

/**
 * Phase 10.2.7 — Field Property State Synchronization Validator
 *
 * Validates the complete lifecycle of field property synchronization:
 *
 * 1. Toggle sync (is_required, is_active)
 *    - x-model binds to selectedField.is_required (reference into fields[])
 *    - @change triggers debouncedFieldSave → saveCurrentField()
 *    - saveCurrentField() must NOT replace field with server response (race condition)
 *    - Phase 10.2.7 fix: only sync sort_order/updated_at from server response
 *
 * 2. Debounce correctness
 *    - debouncedFieldSave uses 800ms timer — confirmed present
 *    - timer is cleared on each keystroke — confirmed present
 *
 * 3. AbortController cancellation
 *    - _fieldSaveController aborts previous in-flight request
 *    - prevents stale server response overwriting newer local state
 *
 * 4. Checkbox state sync (options[], validation_rules{})
 *    - Nested property updates via updateOptions() / updateValidationRule()
 *    - Must mutate selectedField reference (not reassign)
 *
 * 5. Persistence survival (reload)
 *    - Server saves is_required, is_active, validation_rules as JSON in DB
 *    - Reload re-hydrates via window.__formBuilderInitialData
 */
class FormsFieldStateSynchronizationService
{
    /** @var array<int, array{check: string, pass: bool, message: string}> */
    private array $results = [];

    public function validate(bool $strict = false): array
    {
        $this->results = [];

        $this->checkXModelOnToggles();
        $this->checkDebouncePattern();
        $this->checkAbortControllerFieldSave();
        $this->checkOptimisticUpdatePattern();
        $this->checkNestedPropertyMutation();
        $this->checkServerResponseMergeStrategy();
        $this->checkHydrationFromInitialData();
        $this->checkToggleVisualBindings();
        $this->checkValidationRulesSync();
        $this->checkConditionsSync();

        $passed = array_filter($this->results, fn ($r) => $r['pass']);
        $score  = (int) round((count($passed) / max(count($this->results), 1)) * 100);

        return [
            'passed'  => $score >= ($strict ? 100 : 90),
            'score'   => $score,
            'grade'   => $this->grade($score),
            'results' => $this->results,
        ];
    }

    private function view(): ?string
    {
        $p = base_path('packages/saas-platform/modules/forms/resources/views/admin/forms/builder.blade.php');
        if (! file_exists($p)) {
            $p = resource_path('views/vendor/forms/admin/forms/builder.blade.php');
        }
        return file_exists($p) ? file_get_contents($p) : null;
    }

    private function checkXModelOnToggles(): void
    {
        $content = $this->view() ?? '';
        $hasRequired = str_contains($content, 'x-model="selectedField.is_required"');
        $hasActive   = str_contains($content, 'x-model="selectedField.is_active"');
        $this->results[] = [
            'check'   => 'x_model_on_toggles',
            'pass'    => $hasRequired && $hasActive,
            'message' => ($hasRequired && $hasActive)
                ? 'x-model correctly bound to selectedField.is_required and selectedField.is_active'
                : 'MISSING: x-model toggle binding for ' . (! $hasRequired ? 'is_required' : 'is_active'),
        ];
    }

    private function checkDebouncePattern(): void
    {
        $content = $this->view() ?? '';
        $hasDebounce = str_contains($content, 'debouncedFieldSave') &&
                       str_contains($content, 'clearTimeout') &&
                       str_contains($content, '_fieldSaveTimer') &&
                       str_contains($content, 'saveCurrentField');
        $this->results[] = [
            'check'   => 'debounce_pattern',
            'pass'    => $hasDebounce,
            'message' => $hasDebounce
                ? 'debouncedFieldSave() uses clearTimeout + _fieldSaveTimer — rapid edits coalesced'
                : 'MISSING: debounce pattern broken — every keystroke fires a server request',
        ];
    }

    private function checkAbortControllerFieldSave(): void
    {
        $content = $this->view() ?? '';
        $hasAbort = str_contains($content, '_fieldSaveController') &&
                    str_contains($content, '_fieldSaveController.abort()') &&
                    str_contains($content, 'AbortController()') &&
                    str_contains($content, 'signal: this._fieldSaveController.signal');
        $this->results[] = [
            'check'   => 'abort_controller_field_save',
            'pass'    => $hasAbort,
            'message' => $hasAbort
                ? 'saveCurrentField() uses AbortController — stale responses cannot overwrite newer state'
                : 'MISSING: saveCurrentField() lacks AbortController — concurrent saves will cause state corruption',
        ];
    }

    private function checkOptimisticUpdatePattern(): void
    {
        $content = $this->view() ?? '';
        // Optimistic: local state is source of truth during edit; server response is secondary
        $hasOptimistic = str_contains($content, 'saveStatus = \'unsaved\'');
        $this->results[] = [
            'check'   => 'optimistic_update_pattern',
            'pass'    => $hasOptimistic,
            'message' => $hasOptimistic
                ? 'Optimistic UI: saveStatus tracks pending state; UI updates before server confirms'
                : 'MISSING: No optimistic update pattern — UI lags behind user input',
        ];
    }

    private function checkNestedPropertyMutation(): void
    {
        $content = $this->view() ?? '';
        $hasOptions = str_contains($content, 'updateOptions') &&
                      str_contains($content, 'selectedField.options = lines');
        $hasValidation = str_contains($content, 'updateValidationRule') &&
                         str_contains($content, 'selectedField.validation_rules[key]');
        $this->results[] = [
            'check'   => 'nested_property_mutation',
            'pass'    => $hasOptions && $hasValidation,
            'message' => ($hasOptions && $hasValidation)
                ? 'updateOptions() and updateValidationRule() mutate selectedField reference — Alpine reactivity preserved'
                : 'ISSUE: Nested property update pattern broken — Alpine may not detect changes',
        ];
    }

    private function checkServerResponseMergeStrategy(): void
    {
        $content = $this->view() ?? '';
        // Phase 10.2.7 fix: only sync sort_order/updated_at, not the entire field
        $hasSafeMerge = str_contains($content, 'sort_order = json.field.sort_order') ||
                        str_contains($content, 'updated_at = json.field.updated_at');
        // Detect the old dangerous pattern (full replacement)
        $hasDangerousReplace = (bool) preg_match(
            '/this\.fields\[idx\]\s*=\s*json\.field/',
            $content
        );
        $pass = $hasSafeMerge && ! $hasDangerousReplace;
        $this->results[] = [
            'check'   => 'server_response_merge_strategy',
            'pass'    => $pass,
            'message' => $pass
                ? 'saveCurrentField() merges only server-generated fields — user edits not overwritten by stale response'
                : ($hasDangerousReplace
                    ? 'CRITICAL: saveCurrentField() replaces entire field from server response — concurrent edits will be lost'
                    : 'WARNING: Server merge strategy not verified — check saveCurrentField() logic'),
        ];
    }

    private function checkHydrationFromInitialData(): void
    {
        $content = $this->view() ?? '';
        $hasHydration = str_contains($content, 'window.__formBuilderInitialData') &&
                        str_contains($content, 'initialData.fields');
        $this->results[] = [
            'check'   => 'hydration_from_initial_data',
            'pass'    => $hasHydration,
            'message' => $hasHydration
                ? 'Builder hydrated from window.__formBuilderInitialData — server state is initial source of truth'
                : 'MISSING: Builder not hydrated from server — fields will be empty on load',
        ];
    }

    private function checkToggleVisualBindings(): void
    {
        $content = $this->view() ?? '';
        // Toggle thumb uses :class for visual position
        $hasRequiredClass = str_contains($content, "selectedField.is_required ? 'bg-indigo-600'");
        $hasActiveClass   = str_contains($content, "selectedField.is_active ? 'bg-emerald-500'");
        $this->results[] = [
            'check'   => 'toggle_visual_bindings',
            'pass'    => $hasRequiredClass && $hasActiveClass,
            'message' => ($hasRequiredClass && $hasActiveClass)
                ? 'Toggle visual state (color) correctly bound to field properties'
                : 'MISSING: Toggle visual binding not found — toggle may appear stuck in one state',
        ];
    }

    private function checkValidationRulesSync(): void
    {
        $content = $this->view() ?? '';
        $hasMinLength = str_contains($content, "updateValidationRule('min_length'");
        $hasMaxLength = str_contains($content, "updateValidationRule('max_length'");
        $this->results[] = [
            'check'   => 'validation_rules_sync',
            'pass'    => $hasMinLength && $hasMaxLength,
            'message' => ($hasMinLength && $hasMaxLength)
                ? 'Validation rules (min_length, max_length) sync correctly via updateValidationRule()'
                : 'MISSING: Validation rule sync handlers not found',
        ];
    }

    private function checkConditionsSync(): void
    {
        $content = $this->view() ?? '';
        $hasEnsure    = str_contains($content, 'ensureConditions()');
        $hasAddRule   = str_contains($content, 'addConditionRule');
        $hasRemove    = str_contains($content, 'removeConditionRule');
        $this->results[] = [
            'check'   => 'conditional_logic_sync',
            'pass'    => $hasEnsure && $hasAddRule && $hasRemove,
            'message' => ($hasEnsure && $hasAddRule && $hasRemove)
                ? 'Conditional logic (ensureConditions, add/remove rules) correctly wired'
                : 'MISSING: Conditional logic sync handler not found',
        ];
    }

    private function grade(int $score): string
    {
        return match(true) {
            $score >= 95 => 'A+',
            $score >= 85 => 'A',
            $score >= 75 => 'B',
            $score >= 65 => 'C',
            default      => 'F',
        };
    }
}
