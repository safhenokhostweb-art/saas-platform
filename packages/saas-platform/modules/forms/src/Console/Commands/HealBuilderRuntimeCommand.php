<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsBuilderSelfHealingService;

/**
 * Phase 10.2.7 — Heal Forms Builder runtime.
 *
 * Validates all self-healing mechanisms and attempts server-side recovery.
 *
 * Usage:
 *   php artisan forms:heal-builder-runtime
 *   php artisan forms:heal-builder-runtime --json
 */
class HealBuilderRuntimeCommand extends Command
{
    protected $signature = 'forms:heal-builder-runtime
                            {--json : Output JSON}';

    protected $description = 'Validate and run Forms Builder self-healing mechanisms';

    public function handle(FormsBuilderSelfHealingService $service): int
    {
        $json = (bool) $this->option('json');

        $validation = $service->validate();
        $healing    = $service->heal();

        if ($json) {
            $this->line(json_encode([
                'validation' => $validation,
                'healing'    => $healing,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ($validation['passed'] && $healing['healed']) ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>═══ Forms Builder Self-Healing (Phase 10.2.7) ═══</>');
        $this->newLine();

        $this->line('<fg=white;options=bold>Self-Healing Mechanisms:</>');
        $this->newLine();
        foreach ($validation['healers'] as $h) {
            $icon = $h['present'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  $icon {$h['healer']}");
            $this->line("      Mechanism : {$h['mechanism']}");
            $this->line("      Status    : {$h['recovery']}");
            $this->newLine();
        }

        $this->line("  Score: <fg=white>{$validation['score']}/100 ({$validation['grade']})</>");

        $this->newLine();
        $this->line('<fg=white;options=bold>Server-Side Healing Actions:</>');
        $this->newLine();
        foreach ($healing['actions'] as $a) {
            $icon = $a['status'] === 'ok' ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  $icon {$a['message']}");
        }

        $this->newLine();
        $allOk = $validation['passed'] && $healing['healed'];
        if ($allOk) {
            $this->info('Builder runtime is healthy — all self-healing mechanisms active.');
        } else {
            $this->error('Builder runtime has issues — some self-healing mechanisms missing.');
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}
