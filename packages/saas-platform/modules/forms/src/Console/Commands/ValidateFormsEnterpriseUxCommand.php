<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ValidateFormsEnterpriseUxCommand extends Command
{
    protected $signature = 'forms:validate-enterprise-ux
                            {--json : Output results as JSON}
                            {--strict : Treat warnings as failures}
                            {--fail-on-warning : Exit with code 1 if any warnings exist}';

    protected $description = 'Validate that Forms module meets enterprise UX standards';

    private string $viewsPath;

    public function handle(): int
    {
        $this->viewsPath = realpath(__DIR__ . '/../../resources/views') ?: '';

        $results = [
            'drag_drop_ux'          => $this->checkDragDrop(),
            'autosave_indicator'    => $this->checkAutosaveIndicator(),
            'undo_redo'             => $this->checkUndoRedo(),
            'empty_states'          => $this->checkEmptyStates(),
            'loading_states'        => $this->checkLoadingStates(),
            'skeleton_loaders'      => $this->checkSkeletonLoaders(),
            'toast_notifications'   => $this->checkToastNotifications(),
            'keyboard_shortcuts'    => $this->checkKeyboardShortcuts(),
            'field_duplication'     => $this->checkFieldDuplication(),
            'inline_validation'     => $this->checkInlineValidation(),
            'modal_accessibility'   => $this->checkModalAccessibility(),
            'version_history'       => $this->checkVersionHistory(),
            'draft_recovery'        => $this->checkDraftRecovery(),
            'field_search'          => $this->checkFieldSearch(),
            'conditional_logic_ux'  => $this->checkConditionalLogicUx(),
            'mobile_responsive'     => $this->checkMobileResponsive(),
            'rtl_support'           => $this->checkRtlSupport(),
            'optimistic_ui'         => $this->checkOptimisticUi(),
        ];

        $passed  = count(array_filter($results, fn($r) => $r['status'] === 'pass'));
        $warned  = count(array_filter($results, fn($r) => $r['status'] === 'warning'));
        $failed  = count(array_filter($results, fn($r) => $r['status'] === 'fail'));
        $total   = count($results);
        $score   = (int) round((($passed + ($warned * 0.5)) / $total) * 100);

        $grade = match (true) {
            $score >= 95 => 'A+',
            $score >= 90 => 'A',
            $score >= 85 => 'B+',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            default      => 'F',
        };

        if ($this->option('json')) {
            $this->line(json_encode([
                'command'   => 'forms:validate-enterprise-ux',
                'score'     => $score,
                'grade'     => $grade,
                'passed'    => $passed,
                'warned'    => $warned,
                'failed'    => $failed,
                'total'     => $total,
                'checks'    => $results,
                'timestamp' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return $this->resolveExit($failed, $warned);
        }

        $this->newLine();
        $this->line('<fg=magenta;options=bold>═══════════════════════════════════════════════</fg=magenta;options=bold>');
        $this->line('<fg=magenta;options=bold>  Forms Enterprise UX Validation — Phase 9.2  </fg=magenta;options=bold>');
        $this->line('<fg=magenta;options=bold>═══════════════════════════════════════════════</fg=magenta;options=bold>');
        $this->newLine();

        $table = [];
        foreach ($results as $key => $result) {
            $status = $result['status'] ?? 'unknown';
            $icon   = match ($status) {
                'pass'    => '<fg=green>✓ PASS</>',
                'warning' => '<fg=yellow>⚠ WARN</>',
                default   => '<fg=red>✗ FAIL</>',
            };
            $table[] = [
                str_replace('_', ' ', ucwords($key, '_')),
                $icon,
                substr($result['message'] ?? '', 0, 80),
            ];
        }

        $this->table(['UX Feature', 'Status', 'Detail'], $table);
        $this->newLine();

        $scoreColor = $score >= 90 ? 'green' : ($score >= 75 ? 'yellow' : 'red');
        $this->line("<fg={$scoreColor};options=bold>  UX Maturity Score: {$score}/100 (Grade {$grade})</fg={$scoreColor};options=bold>");
        $this->line("  Passed:  {$passed}/{$total}");
        $this->line("  Warned:  {$warned}/{$total}");
        $this->line("  Failed:  {$failed}/{$total}");
        $this->newLine();

        return $this->resolveExit($failed, $warned);
    }

    private function checkDragDrop(): array
    {
        $builder = $this->builderContents();
        $has = $builder &&
            str_contains($builder, 'SortableJS') ||
            str_contains($builder ?? '', 'Sortable.create') ||
            str_contains($builder ?? '', '@dragstart');

        return ['status' => $has ? 'pass' : 'fail', 'message' => $has ? 'SortableJS drag-drop present' : 'No drag-drop implementation found'];
    }

    private function checkAutosaveIndicator(): array
    {
        $builder = $this->builderContents();
        $has = $builder && str_contains($builder, 'autosave') && str_contains($builder, 'saveStatus');
        return ['status' => $has ? 'pass' : 'warning', 'message' => $has ? 'Autosave indicator with status tracking present' : 'Autosave indicator incomplete'];
    }

    private function checkUndoRedo(): array
    {
        $builder = $this->builderContents();
        $has = $builder && str_contains($builder, 'pushHistory') && str_contains($builder, 'undo()') && str_contains($builder, 'redo()');
        return ['status' => $has ? 'pass' : 'fail', 'message' => $has ? 'Undo/redo with history stack implemented' : 'No undo/redo found'];
    }

    private function checkEmptyStates(): array
    {
        $files = $this->allBladeFiles();
        $withEmpty = 0;
        foreach ($files as $file) {
            $contents = File::get($file);
            if (str_contains($contents, 'x-core-ui::empty-state') || str_contains($contents, 'empty-state') || str_contains($contents, 'empty_state')) {
                $withEmpty++;
            }
        }
        return ['status' => $withEmpty >= 2 ? 'pass' : 'warning', 'message' => "Empty state components used in {$withEmpty} views"];
    }

    private function checkLoadingStates(): array
    {
        $files = $this->allBladeFiles();
        $total = 0;
        foreach ($files as $file) {
            $contents = File::get($file);
            if (str_contains($contents, ':disabled="loading"') || str_contains($contents, 'loading') || str_contains($contents, 'submitting')) {
                $total++;
            }
        }
        return ['status' => $total >= 2 ? 'pass' : 'warning', 'message' => "Loading states found in {$total} views"];
    }

    private function checkSkeletonLoaders(): array
    {
        $files = $this->allBladeFiles();
        $found = 0;
        foreach ($files as $file) {
            $contents = File::get($file);
            if (str_contains($contents, 'skeleton') || str_contains($contents, 'x-core-ui::skeleton')) {
                $found++;
            }
        }
        return ['status' => $found >= 1 ? 'pass' : 'warning', 'message' => "Skeleton loaders found in {$found} views"];
    }

    private function checkToastNotifications(): array
    {
        $files = $this->allBladeFiles();
        $found = 0;
        foreach ($files as $file) {
            $contents = File::get($file);
            if (str_contains($contents, 'x-core-ui::toast') || str_contains($contents, 'showToast')) {
                $found++;
            }
        }
        return ['status' => $found >= 2 ? 'pass' : 'warning', 'message' => "Toast notifications in {$found} views"];
    }

    private function checkKeyboardShortcuts(): array
    {
        $builder = $this->builderContents();
        $hasCtrlZ = $builder && str_contains($builder, 'Ctrl+Z');
        $hasCtrlY = $builder && str_contains($builder, 'Ctrl+Y');
        $hasListener = $builder && str_contains($builder, '@keydown') || str_contains($builder ?? '', 'keydown');
        $pass = $hasCtrlZ && $hasCtrlY && $hasListener;
        return ['status' => $pass ? 'pass' : 'warning', 'message' => $pass ? 'Keyboard shortcuts (Ctrl+Z/Y) implemented' : 'Keyboard shortcuts partially implemented'];
    }

    private function checkFieldDuplication(): array
    {
        $builder = $this->builderContents();
        $has = $builder && str_contains($builder, 'duplicateField') && str_contains($builder, '/duplicate');
        return ['status' => $has ? 'pass' : 'warning', 'message' => $has ? 'Field duplication available' : 'Field duplication not found'];
    }

    private function checkInlineValidation(): array
    {
        $files = $this->allBladeFiles();
        $found = 0;
        foreach ($files as $file) {
            $contents = File::get($file);
            if (str_contains($contents, '@error(') || str_contains($contents, 'x-core-ui::validation-badge')) {
                $found++;
            }
        }
        return ['status' => $found >= 3 ? 'pass' : 'warning', 'message' => "Inline validation in {$found} views"];
    }

    private function checkModalAccessibility(): array
    {
        $files = $this->allBladeFiles();
        $issues = 0;
        $total = 0;
        foreach ($files as $file) {
            $contents = File::get($file);
            if (str_contains($contents, 'x-show') && (str_contains($contents, 'modal') || str_contains($contents, 'dialog'))) {
                $total++;
                if (!str_contains($contents, 'role="dialog"') && !str_contains($contents, 'aria-modal')) {
                    $issues++;
                }
            }
        }
        return [
            'status'  => $issues === 0 ? 'pass' : 'warning',
            'message' => $total === 0 ? 'No modals found' : "Modals: {$total} total, {$issues} missing role/aria-modal",
        ];
    }

    private function checkVersionHistory(): array
    {
        $servicePath = __DIR__ . '/../../Services/FormsVersionHistoryService.php';
        $has = file_exists(realpath($servicePath) ?: '');
        return ['status' => $has ? 'pass' : 'warning', 'message' => $has ? 'FormsVersionHistoryService available' : 'Version history service missing'];
    }

    private function checkDraftRecovery(): array
    {
        $servicePath = __DIR__ . '/../../Services/FormsVersionHistoryService.php';
        if (!file_exists(realpath($servicePath) ?: '')) {
            return ['status' => 'warning', 'message' => 'Draft recovery depends on FormsVersionHistoryService'];
        }
        $contents = File::get($servicePath);
        $has = str_contains($contents, 'saveDraft') && str_contains($contents, 'getDraft');
        return ['status' => $has ? 'pass' : 'warning', 'message' => $has ? 'Draft autosave recovery available' : 'Draft recovery incomplete'];
    }

    private function checkFieldSearch(): array
    {
        $builder = $this->builderContents();
        $has = $builder && str_contains($builder, 'paletteSearch') && str_contains($builder, 'fieldTypeVisible');
        return ['status' => $has ? 'pass' : 'warning', 'message' => $has ? 'Field search in palette implemented' : 'Field search missing'];
    }

    private function checkConditionalLogicUx(): array
    {
        $builder = $this->builderContents();
        $has = $builder && str_contains($builder, 'conditions') && str_contains($builder, 'addConditionRule');
        return ['status' => $has ? 'pass' : 'warning', 'message' => $has ? 'Conditional logic UX implemented in builder' : 'Conditional logic UX missing'];
    }

    private function checkMobileResponsive(): array
    {
        $files = $this->allBladeFiles();
        $responsive = 0;
        foreach ($files as $file) {
            $contents = File::get($file);
            if (str_contains($contents, 'md:') || str_contains($contents, 'lg:') || str_contains($contents, 'sm:')) {
                $responsive++;
            }
        }
        return [
            'status'  => $responsive >= 3 ? 'pass' : 'warning',
            'message' => "Responsive breakpoints used in {$responsive} views",
        ];
    }

    private function checkRtlSupport(): array
    {
        $files = $this->allBladeFiles();
        $rtl = 0;
        foreach ($files as $file) {
            $contents = File::get($file);
            if (str_contains($contents, 'dir="rtl"') || str_contains($contents, "dir='rtl'")) {
                $rtl++;
            }
        }
        return ['status' => $rtl >= 1 ? 'pass' : 'warning', 'message' => "RTL direction set in {$rtl} views"];
    }

    private function checkOptimisticUi(): array
    {
        $builder = $this->builderContents();
        $has = $builder && (str_contains($builder, 'saveStatus') || str_contains($builder, 'optimistic'));
        return ['status' => $has ? 'pass' : 'warning', 'message' => $has ? 'Optimistic UI state (saveStatus) present' : 'No optimistic UI pattern detected'];
    }

    private function builderContents(): ?string
    {
        $path = $this->viewsPath . '/admin/forms/builder.blade.php';
        if ($this->viewsPath && File::exists($path)) {
            return File::get($path);
        }
        return null;
    }

    private function allBladeFiles(): array
    {
        if (!$this->viewsPath || !File::isDirectory($this->viewsPath)) {
            return [];
        }
        return array_map(fn($f) => $f->getPathname(), File::allFiles($this->viewsPath));
    }

    private function resolveExit(int $failed, int $warned): int
    {
        if ($failed > 0) {
            return self::FAILURE;
        }
        if ($warned > 0 && ($this->option('strict') || $this->option('fail-on-warning'))) {
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
