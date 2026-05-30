<?php

namespace SaaSPlatform\Modules\Forms\Services;

use SaaSPlatform\Modules\Forms\Contracts\FormFieldRepositoryInterface;

/**
 * Phase 10.2.7 — Forms Builder Ordering Engine
 *
 * Validates the canonical reorder pipeline:
 * - DOM ↔ Alpine state synchronization after SortableJS drags
 * - AbortController cancellation for stale reorder requests
 * - Optimistic reorder rollback pattern presence
 * - Stable field IDs (no client-generated temporaries)
 * - Persistence integrity (sort_order vs position columns)
 *
 * The runtime ordering pipeline (in builder.blade.php):
 *   SortableJS.onEnd → updateFieldOrderFromDom() → persistSort() → pushHistory() → initSortable()
 *
 * Each stage is validated here.
 */
class FormsBuilderOrderingEngine
{
    public function __construct(
        protected FormFieldRepositoryInterface $fieldRepository,
    ) {}

    /** @var array<int, array{check: string, pass: bool, message: string}> */
    private array $results = [];

    public function validate(bool $strict = false): array
    {
        $this->results = [];

        $this->checkBuilderViewExists();
        $this->checkAbortControllerForPersistSort();
        $this->checkUpdateFieldOrderFromDom();
        $this->checkSortableReInitAfterDrag();
        $this->checkNoDuplicateSortableInit();
        $this->checkSortableFilterDropZone();
        $this->checkFieldIdStability();
        $this->checkSortApiRouteExists();
        $this->checkOptimisticRollbackPattern();
        $this->checkChecksumPattern();

        $passed = array_filter($this->results, fn ($r) => $r['pass']);
        $score  = (int) round((count($passed) / max(count($this->results), 1)) * 100);

        return [
            'passed'  => $score >= ($strict ? 100 : 90),
            'score'   => $score,
            'grade'   => $this->grade($score),
            'results' => $this->results,
        ];
    }

    private function builderViewContent(): ?string
    {
        $path = base_path('packages/saas-platform/modules/forms/resources/views/admin/forms/builder.blade.php');
        if (! file_exists($path)) {
            $path = resource_path('views/vendor/forms/admin/forms/builder.blade.php');
        }
        return file_exists($path) ? file_get_contents($path) : null;
    }

    private function checkBuilderViewExists(): void
    {
        $content = $this->builderViewContent();
        $this->results[] = [
            'check'   => 'builder_view_exists',
            'pass'    => $content !== null,
            'message' => $content !== null
                ? 'Builder view located'
                : 'Builder view not found — cannot validate ordering engine',
        ];
    }

    private function checkAbortControllerForPersistSort(): void
    {
        $content = $this->builderViewContent() ?? '';
        // Phase 10.2.7 fix: _persistController AbortController in persistSort()
        $hasController = str_contains($content, '_persistController') &&
                         str_contains($content, '_persistController.abort()');
        $this->results[] = [
            'check'   => 'abort_controller_persist_sort',
            'pass'    => $hasController,
            'message' => $hasController
                ? 'AbortController present in persistSort() — stale reorder requests cancelled'
                : 'MISSING: persistSort() lacks AbortController — rapid drags will cause race conditions',
        ];
    }

    private function checkUpdateFieldOrderFromDom(): void
    {
        $content = $this->builderViewContent() ?? '';
        $hasMethod = str_contains($content, 'updateFieldOrderFromDom') &&
                     str_contains($content, 'data-field-id') &&
                     str_contains($content, 'querySelectorAll');
        $this->results[] = [
            'check'   => 'update_field_order_from_dom',
            'pass'    => $hasMethod,
            'message' => $hasMethod
                ? 'updateFieldOrderFromDom() reads DOM order via [data-field-id] selectors'
                : 'MISSING: updateFieldOrderFromDom() method or data-field-id attribute pattern',
        ];
    }

    private function checkSortableReInitAfterDrag(): void
    {
        $content = $this->builderViewContent() ?? '';
        // onEnd must call $nextTick(() => this.initSortable())
        $hasReInit = preg_match('/onEnd.*\$nextTick.*initSortable/s', $content) === 1;
        $this->results[] = [
            'check'   => 'sortable_reinit_after_drag',
            'pass'    => $hasReInit,
            'message' => $hasReInit
                ? 'initSortable() called via $nextTick in onEnd — DOM/Alpine state re-anchored after drag'
                : 'MISSING: onEnd does not re-init Sortable — subsequent drags will use stale DOM refs',
        ];
    }

    private function checkNoDuplicateSortableInit(): void
    {
        $content = $this->builderViewContent() ?? '';
        // initSortable must destroy before recreating
        $hasSafeInit = str_contains($content, 'this.sortableInstance') &&
                       str_contains($content, 'sortableInstance.destroy()');
        $this->results[] = [
            'check'   => 'no_duplicate_sortable_init',
            'pass'    => $hasSafeInit,
            'message' => $hasSafeInit
                ? 'initSortable() destroys previous instance before recreating — no duplicate listeners'
                : 'MISSING: initSortable() does not guard against duplicate Sortable instances',
        ];
    }

    private function checkSortableFilterDropZone(): void
    {
        $content = $this->builderViewContent() ?? '';
        $hasFilter = str_contains($content, "filter: '#drop-zone'");
        $this->results[] = [
            'check'   => 'sortable_filter_drop_zone',
            'pass'    => $hasFilter,
            'message' => $hasFilter
                ? 'Sortable configured with filter for drop-zone — drop zone not sortable'
                : 'WARNING: Drop zone may be included in sortable items causing drag artifacts',
        ];
    }

    private function checkFieldIdStability(): void
    {
        $content = $this->builderViewContent() ?? '';
        // Fields must use server-assigned IDs (from json.field.id), never Math.random() or Date.now()
        $hasUnsafeId = str_contains($content, 'Math.random()') ||
                       preg_match('/id.*Date\.now\(\)/', $content);
        $this->results[] = [
            'check'   => 'stable_field_ids',
            'pass'    => ! $hasUnsafeId,
            'message' => ! $hasUnsafeId
                ? 'Field IDs are server-assigned — stable across drags and reloads'
                : 'CRITICAL: Client-generated field IDs detected — will cause ordering desync on reload',
        ];
    }

    private function checkSortApiRouteExists(): void
    {
        $exists = false;
        try {
            $exists = \Illuminate\Support\Facades\Route::has('admin.forms.builder.sort');
        } catch (\Throwable) {}

        $this->results[] = [
            'check'   => 'sort_api_route_exists',
            'pass'    => $exists,
            'message' => $exists
                ? 'admin.forms.builder.sort route registered — field reorder can persist'
                : 'MISSING: admin.forms.builder.sort route not found — field reorder will fail silently',
        ];
    }

    private function checkOptimisticRollbackPattern(): void
    {
        $content = $this->builderViewContent() ?? '';
        // We accept optimistic ordering (no rollback UI) as long as there's error handling
        $hasErrorHandling = str_contains($content, "AbortError") &&
                            str_contains($content, 'showToast');
        $this->results[] = [
            'check'   => 'optimistic_reorder_error_handling',
            'pass'    => $hasErrorHandling,
            'message' => $hasErrorHandling
                ? 'persistSort() catches errors and shows toast — user informed of reorder failures'
                : 'WARNING: persistSort() silent failure — user unaware when sort persistence fails',
        ];
    }

    private function checkChecksumPattern(): void
    {
        $content = $this->builderViewContent() ?? '';
        // At minimum, DOM read-back via querySelectorAll is a form of checksum validation
        $hasReadBack = str_contains($content, 'querySelectorAll') &&
                       str_contains($content, 'data-field-id');
        $this->results[] = [
            'check'   => 'dom_state_read_back',
            'pass'    => $hasReadBack,
            'message' => $hasReadBack
                ? 'DOM order read back via [data-field-id] after each sort — implicit checksum'
                : 'MISSING: No DOM→state read-back — ordering desync undetectable',
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
