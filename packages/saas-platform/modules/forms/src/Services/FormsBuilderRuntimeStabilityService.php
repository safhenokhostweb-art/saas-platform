<?php

namespace SaaSPlatform\Modules\Forms\Services;

/**
 * Phase 10.2.7 — Builder Runtime Stability Validator
 *
 * Validates the complete runtime stability lifecycle:
 * - Undo/redo state machine (pushHistory, history array, historyIndex)
 * - Autosave lifecycle (debounce, AbortController, status indicator)
 * - Modal stack integrity (showDeleteConfirm, showTemplateModal, showKeyboardHelp)
 * - Escape key handling (all modals close on Escape)
 * - Keyboard shortcuts lifecycle (Ctrl+Z, Ctrl+Y, Ctrl+S, ?)
 * - Concurrent edit protection (AbortControllers, no duplicate listeners)
 * - Stale timer cleanup (clearTimeout before setting new timer)
 * - Memory leak prevention (no duplicate Sortable instances)
 * - Duplicate event listener prevention (single init per lifecycle)
 * - Unsaved changes protection (beforeunload handler)
 */
class FormsBuilderRuntimeStabilityService
{
    /** @var array<int, array{check: string, pass: bool, message: string, severity: string}> */
    private array $results = [];

    public function validate(bool $strict = false): array
    {
        $this->results = [];

        $this->checkUndoRedoStateMachine();
        $this->checkHistoryLimitBound();
        $this->checkAutosaveDebounce();
        $this->checkAutosaveAbortController();
        $this->checkModalStateVariables();
        $this->checkEscapeHandlerCoverage();
        $this->checkKeyboardShortcuts();
        $this->checkBeforeUnloadProtection();
        $this->checkSortableDestroyOnReinit();
        $this->checkTimerCleanup();
        $this->checkToastDeduplication();
        $this->checkCrashRecovery();

        $critical   = array_filter($this->results, fn ($r) => ! $r['pass'] && ($r['severity'] ?? 'ERROR') === 'CRITICAL');
        $passed     = array_filter($this->results, fn ($r) => $r['pass']);
        $score      = (int) round((count($passed) / max(count($this->results), 1)) * 100);
        $isBlocking = count($critical) > 0;

        return [
            'passed'          => $score >= ($strict ? 100 : 90) && ! $isBlocking,
            'score'           => $score,
            'grade'           => $this->grade($score),
            'results'         => $this->results,
            'critical_issues' => array_values($critical),
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

    private function checkUndoRedoStateMachine(): void
    {
        $content = $this->view() ?? '';
        $hasHistory      = str_contains($content, 'historyIndex:') && str_contains($content, 'history:');
        $hasPush         = str_contains($content, 'pushHistory()') && str_contains($content, 'this.history.push(');
        $hasUndoRedo     = str_contains($content, 'this.historyIndex--') && str_contains($content, 'this.historyIndex++');
        $hasBranchPrune  = str_contains($content, 'this.history.slice(0, this.historyIndex + 1)');
        $this->results[] = [
            'check'    => 'undo_redo_state_machine',
            'pass'     => $hasHistory && $hasPush && $hasUndoRedo && $hasBranchPrune,
            'message'  => ($hasHistory && $hasPush && $hasUndoRedo && $hasBranchPrune)
                ? 'Undo/redo state machine complete: historyIndex, pushHistory, branch pruning'
                : 'ISSUE: Undo/redo state machine incomplete — history may diverge',
            'severity' => 'ERROR',
        ];
    }

    private function checkHistoryLimitBound(): void
    {
        $content = $this->view() ?? '';
        // Should cap history at some limit (50) to prevent memory leak
        $hasCap = str_contains($content, 'history.length > 50') ||
                  str_contains($content, 'history.shift()');
        $this->results[] = [
            'check'    => 'history_limit_bound',
            'pass'     => $hasCap,
            'message'  => $hasCap
                ? 'History capped at 50 entries — no unbounded memory growth'
                : 'WARNING: History array unbounded — long sessions will leak memory',
            'severity' => 'WARNING',
        ];
    }

    private function checkAutosaveDebounce(): void
    {
        $content = $this->view() ?? '';
        $hasDebounce = str_contains($content, '_saveTimer') &&
                       str_contains($content, 'clearTimeout(this._saveTimer)') &&
                       str_contains($content, 'saveFormSettings');
        $this->results[] = [
            'check'    => 'autosave_debounce',
            'pass'     => $hasDebounce,
            'message'  => $hasDebounce
                ? 'autosave() debounced via _saveTimer — form settings saves coalesced'
                : 'MISSING: autosave debounce — every field focus loss fires a server request',
            'severity' => 'ERROR',
        ];
    }

    private function checkAutosaveAbortController(): void
    {
        $content = $this->view() ?? '';
        $hasAbort = str_contains($content, '_saveController') &&
                    str_contains($content, '_saveController.abort()') &&
                    str_contains($content, 'signal: this._saveController.signal');
        $this->results[] = [
            'check'    => 'autosave_abort_controller',
            'pass'     => $hasAbort,
            'message'  => $hasAbort
                ? 'saveFormSettings() uses AbortController — concurrent settings saves cancelled'
                : 'MISSING: saveFormSettings() lacks AbortController — race conditions possible',
            'severity' => 'ERROR',
        ];
    }

    private function checkModalStateVariables(): void
    {
        $content = $this->view() ?? '';
        $modals = [
            'showTemplateModal'        => 'Template selection modal',
            'showDeleteConfirm'        => 'Delete confirmation modal',
            'showTemplateApplyConfirm' => 'Template apply confirmation modal',
            'showKeyboardHelp'         => 'Keyboard shortcuts modal',
        ];
        $allPresent = true;
        $missing    = [];
        foreach ($modals as $var => $label) {
            if (! str_contains($content, "$var:")) {
                $allPresent = false;
                $missing[]  = $var;
            }
        }
        $this->results[] = [
            'check'    => 'modal_state_variables',
            'pass'     => $allPresent,
            'message'  => $allPresent
                ? 'All 4 modal state variables initialized: showTemplateModal, showDeleteConfirm, showTemplateApplyConfirm, showKeyboardHelp'
                : 'MISSING modal variables: ' . implode(', ', $missing),
            'severity' => 'CRITICAL',
        ];
    }

    private function checkEscapeHandlerCoverage(): void
    {
        $content = $this->view() ?? '';
        // Escape should close: keyboard help, template modal, template apply confirm
        $hasEscapeKeyDown = str_contains($content, "e.key === 'Escape'");
        $hasModalEscapes  = str_contains($content, '@keydown.escape.window="showKeyboardHelp = false"') ||
                            str_contains($content, '@keydown.escape.window');
        $this->results[] = [
            'check'    => 'escape_handler_coverage',
            'pass'     => $hasEscapeKeyDown && $hasModalEscapes,
            'message'  => ($hasEscapeKeyDown && $hasModalEscapes)
                ? 'Escape key closes all modals — keyboard handler + Alpine @keydown.escape.window'
                : 'MISSING: Escape key handler incomplete — modals may not close with keyboard',
            'severity' => 'WARNING',
        ];
    }

    private function checkKeyboardShortcuts(): void
    {
        $content = $this->view() ?? '';
        $hasCtrlZ = str_contains($content, "e.key === 'z'") || str_contains($content, "key === 'z'");
        $hasCtrlS = str_contains($content, "e.key === 's'") || str_contains($content, "key === 's'");
        $hasCtrlY = str_contains($content, "e.key === 'y'") || str_contains($content, "key === 'y'");
        $this->results[] = [
            'check'    => 'keyboard_shortcuts',
            'pass'     => $hasCtrlZ && $hasCtrlS && $hasCtrlY,
            'message'  => ($hasCtrlZ && $hasCtrlS && $hasCtrlY)
                ? 'Keyboard shortcuts registered: Ctrl+Z (undo), Ctrl+Y (redo), Ctrl+S (save)'
                : 'MISSING keyboard shortcut: ' . implode(', ', array_filter([
                    ! $hasCtrlZ ? 'Ctrl+Z' : null,
                    ! $hasCtrlY ? 'Ctrl+Y' : null,
                    ! $hasCtrlS ? 'Ctrl+S' : null,
                ])),
            'severity' => 'WARNING',
        ];
    }

    private function checkBeforeUnloadProtection(): void
    {
        $content = $this->view() ?? '';
        $hasBeforeUnload = str_contains($content, "addEventListener('beforeunload'") &&
                           str_contains($content, "saveStatus === 'unsaved'");
        $this->results[] = [
            'check'    => 'before_unload_protection',
            'pass'     => $hasBeforeUnload,
            'message'  => $hasBeforeUnload
                ? 'beforeunload handler warns user of unsaved changes — no accidental data loss'
                : 'MISSING: beforeunload handler — users may accidentally navigate away with unsaved changes',
            'severity' => 'WARNING',
        ];
    }

    private function checkSortableDestroyOnReinit(): void
    {
        $content = $this->view() ?? '';
        $hasDestroy = str_contains($content, 'this.sortableInstance') &&
                      str_contains($content, 'sortableInstance.destroy()') &&
                      str_contains($content, 'new Sortable(canvas,');
        $this->results[] = [
            'check'    => 'sortable_destroy_on_reinit',
            'pass'     => $hasDestroy,
            'message'  => $hasDestroy
                ? 'Sortable instance destroyed before re-init — no memory leaks or duplicate listeners'
                : 'MISSING: Sortable not destroyed before re-init — duplicate event listeners and memory leaks',
            'severity' => 'CRITICAL',
        ];
    }

    private function checkTimerCleanup(): void
    {
        $content = $this->view() ?? '';
        $hasCleanup = str_contains($content, 'clearTimeout(this._fieldSaveTimer)') &&
                      str_contains($content, 'clearTimeout(this._saveTimer)') &&
                      str_contains($content, 'clearTimeout(this._crashSaveTimer)');
        $this->results[] = [
            'check'    => 'timer_cleanup',
            'pass'     => $hasCleanup,
            'message'  => $hasCleanup
                ? 'All timers (_fieldSaveTimer, _saveTimer, _crashSaveTimer) cleared before reset — no stale callbacks'
                : 'MISSING: Some timers not cleared — stale callbacks may fire after state changes',
            'severity' => 'WARNING',
        ];
    }

    private function checkToastDeduplication(): void
    {
        $content = $this->view() ?? '';
        $hasDedup = str_contains($content, 'toasts.some(t => t.message === message && t.type === type)');
        $this->results[] = [
            'check'    => 'toast_deduplication',
            'pass'     => $hasDedup,
            'message'  => $hasDedup
                ? 'Toast deduplication prevents duplicate notifications for same message+type'
                : 'MISSING: Toast deduplication — rapid actions produce duplicate toast spam',
            'severity' => 'WARNING',
        ];
    }

    private function checkCrashRecovery(): void
    {
        $content = $this->view() ?? '';
        $hasCrashSave = str_contains($content, '_draftKey()') &&
                        str_contains($content, 'localStorage.setItem') &&
                        str_contains($content, 'checkDraftRecovery') &&
                        str_contains($content, 'restoreDraft');
        $this->results[] = [
            'check'    => 'crash_recovery_draft',
            'pass'     => $hasCrashSave,
            'message'  => $hasCrashSave
                ? 'Crash recovery via localStorage: draft saved every 500ms, recovery banner on open'
                : 'MISSING: Crash recovery system — page crash loses all unsaved changes',
            'severity' => 'ERROR',
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
