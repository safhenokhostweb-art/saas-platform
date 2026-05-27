<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FrontendVisualParityEngine;

class ValidateFrontendParityCommand extends Command
{
    protected $signature   = 'frontend:validate-parity
                              {--json    : Output as JSON}
                              {--strict  : Treat warnings as failures}
                              {--fix-hints : Show fix instructions for each failure}';

    protected $description = 'Validate cross-context visual parity between saas-platform and customer-site';

    public function handle(FrontendVisualParityEngine $engine): int
    {
        $results = $engine->validate();
        $score   = $engine->score();
        $parity  = $engine->isParity();

        if ($this->option('json')) {
            $this->line(json_encode([
                'score'   => $score,
                'parity'  => $parity,
                'checks'  => $results,
                'passed'  => $parity,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $parity ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold> ⚡ Frontend Visual Parity Audit</>');
        $this->line('  Phase 9.3 — Cross-Context Runtime Parity');
        $this->newLine();

        foreach ($results as $key => $check) {
            $icon    = ($check['passed'] ?? false) ? '<fg=bright-green>✓</>' : '<fg=bright-red>✗</>';
            $label   = $check['check'] ?? $key;
            $message = $check['message'] ?? '';
            $this->line("  {$icon}  <options=bold>{$label}</>");

            if (! ($check['passed'] ?? false)) {
                $this->line("     <fg=red>{$message}</>");

                foreach ($check['violations'] ?? $check['issues'] ?? [] as $v) {
                    $this->line("       <fg=yellow>— {$v}</>");
                }

                if ($this->option('fix-hints')) {
                    $this->line('     <fg=cyan>FIX:</> ' . $this->fixHint($key));
                }
            } else {
                $this->line("     <fg=gray>{$message}</>");
            }
            $this->newLine();
        }

        $bar   = str_repeat('█', (int) ($score / 5)) . str_repeat('░', 20 - (int) ($score / 5));
        $color = $score >= 90 ? 'bright-green' : ($score >= 70 ? 'bright-yellow' : 'bright-red');
        $this->line("  Parity Score  <fg={$color};options=bold>{$score}/100</>  [{$bar}]");
        $this->newLine();

        if ($parity) {
            $this->info('  ✅  Cross-context visual parity CONFIRMED');
        } else {
            $this->error('  ❌  Parity violations detected — frontend divergence risk');
        }

        if ($this->option('strict') && ! $parity) {
            return self::FAILURE;
        }

        return $parity ? self::SUCCESS : self::FAILURE;
    }

    private function fixHint(string $key): string
    {
        return match ($key) {
            'forbidden_overrides'      => 'Delete all blade files from resources/views/admin/forms, site/forms, admin/tickets, site/tickets in both apps',
            'css_variable_drift'       => 'Replace hardcoded CSS values with --forms-* design tokens from <x-core-ui::design-tokens />',
            'component_namespace'      => 'Add Blade::anonymousComponentNamespace(\'forms::core-ui.components\', \'core-ui\') in TicketsServiceProvider::boot()',
            'layout_resolution'        => 'Ensure both FormsServiceProvider and TicketsServiceProvider register view()->composer() for dynamic layout',
            'view_authority_integrity' => 'Remove any .blade.php files from host app resources/views/admin or resources/views/site for modules',
            default                    => 'Review the check output and fix the listed items',
        };
    }
}
