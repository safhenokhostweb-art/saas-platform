<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Plugins;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\PluginGovernance\Sandbox\SandboxRuntimeManager;
use SaaSPlatform\CoreUtils\PluginGovernance\ModuleCapabilityRegistry;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\TrustedPublisherRegistry;
use SaaSPlatform\CoreUtils\PluginGovernance\ResourceGovernance\SandboxLoadBalancer;

class PluginRuntimeStatusCommand extends Command
{
    protected $signature   = 'plugins:runtime-status {--module= : Show status for specific module}';
    protected $description = 'Show plugin governance runtime status overview';

    public function handle(
        SandboxRuntimeManager    $sandboxManager,
        ModuleCapabilityRegistry $capabilityRegistry,
        TrustedPublisherRegistry $publisherRegistry,
        SandboxLoadBalancer      $loadBalancer
    ): int {
        $this->info('Plugin Governance Runtime Status');
        $this->line(str_repeat('─', 50));

        $this->newLine();
        $this->line('<fg=cyan>Capability Registry</>');
        $this->line('  Total capabilities   : ' . count($capabilityRegistry->all()));
        $this->line('  Requires grant       : ' . count($capabilityRegistry->requireingExplicitGrant()));

        $this->newLine();
        $this->line('<fg=cyan>Trust Infrastructure</>');
        $this->line('  Trusted publishers   : ' . $publisherRegistry->count());

        $this->newLine();
        $this->line('<fg=cyan>Platform Load</>');
        $platformStatus = $loadBalancer->platformStatus();
        $this->line('  Total modules        : ' . $platformStatus['total_modules']);
        $this->line('  Noisy modules        : ' . $platformStatus['noisy_modules']);
        $this->line('  Fairness score       : ' . $platformStatus['fairness_score'] . '%');
        $this->line('  Load balance         : ' . $platformStatus['load_balance_status']);

        $moduleId = $this->option('module');

        if ($moduleId) {
            $this->newLine();
            $this->line("<fg=cyan>Module: {$moduleId}</>");
            $status = $sandboxManager->status($moduleId);
            $this->line('  Status : ' . ($status['status'] ?? 'unknown'));
            $resources = $sandboxManager->resourceSnapshot($moduleId);
            $this->line('  Jobs used   : ' . ($resources['queue_jobs'] ?? 0));
            $this->line('  HTTP used   : ' . ($resources['http_requests'] ?? 0));
        }

        $this->newLine();
        $this->line('<fg=green>Plugin governance runtime is operational.</>');

        return self::SUCCESS;
    }
}
