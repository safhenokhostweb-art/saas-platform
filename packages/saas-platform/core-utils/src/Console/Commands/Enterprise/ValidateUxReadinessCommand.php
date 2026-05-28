<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Enterprise;

use Illuminate\Console\Command;

class ValidateUxReadinessCommand extends Command
{
    protected $signature   = 'ux:validate-enterprise-readiness {--json} {--strict} {--fail-on-warning}';
    protected $description = 'Validate enterprise UX readiness: RTL, accessibility, keyboard navigation, loading states.';

    private array $viewChecks = [
        ['view' => 'packages/saas-platform/modules/forms/resources/views/admin/forms/builder.blade.php',
         'required' => ['x-data', 'aria-', 'focus-visible', 'Keyboard|keyboard|showKeyboard|میانبر']],
        ['view' => 'packages/saas-platform/modules/forms/resources/views/admin/forms/index.blade.php',
         'required' => ['aria-', 'empty-state|emptyState|empty_state|هیچ|فرمی']],
        ['view' => 'packages/saas-platform/modules/tickets/Resources/views/admin/tickets/show.blade.php',
         'required' => ['x-data', 'aria-|role=']],
        ['view' => 'packages/saas-platform/modules/tickets/Resources/views/admin/tickets/index.blade.php',
         'required' => ['x-data', 'aria-|role=']],
    ];

    private array $componentChecks = [
        'alert', 'badge', 'modal', 'toast', 'skeleton-card',
        'empty-state', 'page-header', 'stat-card', 'tabs',
        'ai-assistant-panel', 'command-palette', 'ai-suggestion-card',
    ];

    public function handle(): int
    {
        $checks   = [];
        $score    = 100;
        $warnings = 0;
        $failures = 0;

        // 1. View UX quality checks
        foreach ($this->viewChecks as $check) {
            $result = $this->checkView($check['view'], $check['required']);
            $checks[] = $result;
            if (! $result['pass']) { $score -= 5; $warnings++; }
        }

        // 2. core-ui components
        $componentBase = base_path('packages/saas-platform/modules/forms/resources/views/core-ui/components');
        foreach ($this->componentChecks as $comp) {
            $exists   = file_exists("{$componentBase}/{$comp}.blade.php");
            $checks[] = ['check' => "core_ui::{$comp}", 'pass' => $exists, 'detail' => $exists ? 'Present' : 'Missing'];
            if (! $exists) { $score -= 3; $warnings++; }
        }

        // 3. RTL support
        $rtlFile  = base_path('packages/saas-platform/modules/forms/resources/views/core-ui/components/design-tokens.blade.php');
        $rtlOk    = file_exists($rtlFile) && str_contains(file_get_contents($rtlFile), 'rtl');
        $checks[] = ['check' => 'rtl_support', 'pass' => $rtlOk, 'detail' => $rtlOk ? 'Design tokens include RTL' : 'RTL tokens missing'];
        if (! $rtlOk) { $score -= 5; $warnings++; }

        // 4. Alpine.js AI store
        $storeFile = base_path('packages/saas-platform/core-utils/resources/views/components/ai-runtime-store.blade.php');
        $storeOk   = file_exists($storeFile);
        $checks[]  = ['check' => 'ai_runtime_store', 'pass' => $storeOk, 'detail' => $storeOk ? 'Alpine AI store present' : 'Missing'];
        if (! $storeOk) { $score -= 10; $failures++; }

        // 5. Toast store
        $toastView = "{$componentBase}/toast.blade.php";
        $toastOk   = file_exists($toastView);
        $checks[]  = ['check' => 'toast_system', 'pass' => $toastOk, 'detail' => $toastOk ? 'Toast component active' : 'Missing'];
        if (! $toastOk) { $score -= 5; $warnings++; }

        // 6. Loading states present (any async UI indicator)
        $builderFile    = base_path('packages/saas-platform/modules/forms/resources/views/admin/forms/builder.blade.php');
        $builderContent = file_exists($builderFile) ? file_get_contents($builderFile) : '';
        $loadingTokens  = ['loading', 'saving', 'autosave', 'animate-spin', 'skeleton', 'spinner', 'busy'];
        $hasLoading     = false;
        foreach ($loadingTokens as $lt) {
            if (str_contains($builderContent, $lt)) { $hasLoading = true; break; }
        }
        $checks[] = ['check' => 'loading_states', 'pass' => $hasLoading, 'detail' => $hasLoading ? 'Async UI indicators present in builder' : 'Loading states missing'];
        if (! $hasLoading) { $score -= 3; $warnings++; }

        $score = max(0, $score);
        $grade = match(true) { $score >= 97 => 'A+', $score >= 90 => 'A', $score >= 80 => 'B', $score >= 70 => 'C', default => 'F' };

        $result = [
            'command'  => 'ux:validate-enterprise-readiness',
            'score'    => $score,
            'grade'    => $grade,
            'failures' => $failures,
            'warnings' => $warnings,
            'checks'   => $checks,
            'validated_at' => now()->toISOString(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
        } else {
            $this->newLine();
            $this->line('  <fg=cyan>Enterprise UX Readiness Validation</>');
            $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');
            $this->line("  Score: <fg={$color}>{$score}/100 ({$grade})</>");
            $this->newLine();
            foreach ($checks as $c) {
                $icon = $c['pass'] ? '<fg=green>✓</>' : '<fg=yellow>⚠</>';
                $this->line("  {$icon} {$c['check']}: {$c['detail']}");
            }
            $this->newLine();
            if ($failures > 0) {
                $this->error("  {$failures} critical UX failures.");
            } elseif ($warnings > 0) {
                $this->warn("  {$warnings} UX warnings detected.");
            } else {
                $this->info('  Enterprise UX is fully ready.');
            }
        }

        if ($this->option('strict') && ($failures > 0 || ($this->option('fail-on-warning') && $warnings > 0))) {
            return self::FAILURE;
        }
        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkView(string $relPath, array $required): array
    {
        $full = base_path($relPath);
        if (! file_exists($full)) {
            return ['check' => "view:{$relPath}", 'pass' => false, 'detail' => 'File missing'];
        }
        $content = file_get_contents($full);
        $missing = [];
        foreach ($required as $token) {
            // Support | as OR operator: "aria-|role=" means either must be present
            $alternatives = explode('|', $token);
            $found = false;
            foreach ($alternatives as $alt) {
                if (str_contains($content, $alt)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $missing[] = $token;
            }
        }
        return [
            'check'  => 'view:'.basename($relPath),
            'pass'   => empty($missing),
            'detail' => empty($missing) ? 'All UX tokens present' : 'Missing: '.implode(', ', $missing),
        ];
    }
}
