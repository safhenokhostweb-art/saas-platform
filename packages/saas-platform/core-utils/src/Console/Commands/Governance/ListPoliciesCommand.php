<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Governance;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernancePolicyRegistry;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Policy\RuntimePolicyEngine;

class ListPoliciesCommand extends Command
{
    protected $signature = 'governance:list-policies
                            {--enabled : Show only enabled policies}
                            {--json    : Output as JSON}';

    protected $description = 'List all registered runtime governance policies';

    public function handle(
        GovernancePolicyRegistry $registry,
        RuntimePolicyEngine      $engine
    ): int {
        $policies = $this->option('enabled')
            ? $registry->enabled()
            : $registry->all();

        if ($this->option('json')) {
            $this->line(json_encode([
                'total'    => count($policies),
                'policies' => $policies,
                'engine'   => $engine->listPolicies(),
            ], JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info('=== Registered Governance Policies ===');
        $this->line('Total: ' . count($policies) . ($this->option('enabled') ? ' (enabled only)' : ''));
        $this->newLine();

        if (empty($policies)) {
            $this->warn('No policies registered. Bootstrap may not have run.');
            return 0;
        }

        $engineList = collect($engine->listPolicies())->keyBy('name')->toArray();

        foreach ($policies as $policy) {
            $name    = $policy['name'];
            $enabled = $policy['enabled'] ? '<fg=green>enabled</>' : '<fg=gray>disabled</>';
            $trigger = $policy['trigger'] ?? 'manual';
            $version = $policy['version'] ?? '1.0.0';

            $cooldownStatus = '';
            if (isset($engineList[$name])) {
                $cooldownStatus = $engineList[$name]['on_cooldown']
                    ? ' <fg=yellow>[on cooldown]</>'
                    : '';
            }

            $this->line(sprintf(
                '  %-35s %s  trigger=%-20s v%s%s',
                $name, $enabled, $trigger, $version, $cooldownStatus
            ));

            $condCount = count($policy['conditions'] ?? []);
            $actCount  = count($policy['actions'] ?? []);
            $this->line(sprintf('    conditions: %d, actions: %d, cooldown: %ds',
                $condCount, $actCount, $policy['cooldown_seconds'] ?? 60
            ));
        }

        return 0;
    }
}
