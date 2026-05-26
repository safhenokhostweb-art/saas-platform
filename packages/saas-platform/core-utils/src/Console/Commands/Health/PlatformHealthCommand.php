<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Health;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\Health\RuntimeHealthService;

class PlatformHealthCommand extends Command
{
    protected $signature = 'platform:health
                            {gate?         : Specific gate: live, ready, release (default: all)}
                            {--json        : Output as JSON}
                            {--fail-on-warn: Exit 1 if not fully healthy}';

    protected $description = 'Check platform runtime health (Phase 8.2)';

    public function handle(RuntimeHealthService $health): int
    {
        $gate = $this->argument('gate');

        $result = match ($gate) {
            'live'    => ['live' => $health->live()],
            'ready'   => ['ready' => $health->ready()],
            'release' => ['release' => $health->release()],
            default   => $health->full(),
        };

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $this->isHealthy($result) ? 0 : 1;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>PLATFORM HEALTH CHECK — PHASE 8.2</>');
        $this->line('  ─────────────────────────────────────');
        $this->newLine();

        if (isset($result['live'])) {
            $this->line("  <fg=green>✓</> Process alive — PHP " . ($result['live']['php'] ?? '?'));
        }

        if (isset($result['ready'])) {
            $readyData = $result['ready'];
            $icon      = $readyData['ready'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  {$icon} Ready: " . ($readyData['ready'] ? 'YES' : 'NO'));

            foreach ($readyData['checks'] ?? [] as $name => $check) {
                $ci = $check['passed'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
                $this->line("      {$ci} {$name}: {$check['detail']}");
            }
        }

        if (isset($result['release'])) {
            $rel  = $result['release'];
            $icon = $rel['coherent'] ? '<fg=green>✓</>' : '<fg=yellow>⚠</>';
            $this->line("  {$icon} Release coherent: " . ($rel['coherent'] ? 'YES' : 'NO'));

            if ($rel['latest_release'] ?? null) {
                $lr = $rel['latest_release'];
                $this->line("     UUID:    {$lr['uuid']}");
                $this->line("     At:      {$lr['released_at']}");
                $this->line("     Commit:  " . ($lr['git_commit'] ?? 'unknown'));
            }

            if ($rel['schema_drift'] ?? false) {
                $this->warn("     Schema drift detected — {$rel['pending_migrations']} pending migration(s)");
            }
        }

        $this->newLine();

        $healthy = $this->isHealthy($result);
        if (!$healthy && $this->option('fail-on-warn')) {
            return 1;
        }

        return 0;
    }

    private function isHealthy(array $result): bool
    {
        if (isset($result['ready']) && !$result['ready']['ready']) {
            return false;
        }
        if (isset($result['release']) && !$result['release']['coherent']) {
            return false;
        }
        return true;
    }
}
