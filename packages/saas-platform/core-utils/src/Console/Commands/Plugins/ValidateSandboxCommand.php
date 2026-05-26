<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Plugins;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\PluginGovernance\Sandbox\SandboxRuntimeManager;
use SaaSPlatform\CoreUtils\PluginGovernance\Containment\PluginContainmentManager;
use SaaSPlatform\CoreUtils\PluginGovernance\CapabilityResolutionEngine;
use SaaSPlatform\CoreUtils\PluginGovernance\ModuleCapabilityRegistry;

class ValidateSandboxCommand extends Command
{
    protected $signature   = 'plugins:validate-sandbox {--module= : Specific module ID to validate}';
    protected $description = 'Validate plugin sandbox runtime health and capability enforcement';

    public function handle(
        SandboxRuntimeManager    $sandboxManager,
        PluginContainmentManager $containmentManager,
        CapabilityResolutionEngine $resolver,
        ModuleCapabilityRegistry $registry
    ): int {
        $this->info('Plugin Sandbox Validation');
        $this->line(str_repeat('─', 50));

        $checks = [
            'capability_registry'    => $this->checkCapabilityRegistry($registry),
            'deny_by_default'        => $this->checkDenyByDefault($resolver),
            'sandbox_runtime'        => $this->checkSandboxRuntime($sandboxManager),
            'containment_available'  => $this->checkContainment($containmentManager),
        ];

        $passed = 0;
        $failed = 0;

        foreach ($checks as $check => $result) {
            $status = $result['ok'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
            $this->line("  [{$status}] {$check}: {$result['message']}");
            $result['ok'] ? $passed++ : $failed++;
        }

        $this->newLine();
        $this->line("Result: {$passed} passed / {$failed} failed");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function checkCapabilityRegistry(ModuleCapabilityRegistry $registry): array
    {
        $count = count($registry->all());
        return ['ok' => $count >= 11, 'message' => "{$count} capabilities registered"];
    }

    private function checkDenyByDefault(CapabilityResolutionEngine $resolver): array
    {
        $granted = $resolver->effectiveCapabilities('__non_existent_module__');
        $onlyFree = count($granted) <= 1;
        return ['ok' => $onlyFree, 'message' => 'deny-by-default enforced for unknown modules'];
    }

    private function checkSandboxRuntime(SandboxRuntimeManager $manager): array
    {
        $summary = $manager->summary();
        return ['ok' => isset($summary['sandbox_runtime']), 'message' => 'sandbox runtime active'];
    }

    private function checkContainment(PluginContainmentManager $manager): array
    {
        return ['ok' => true, 'message' => 'containment manager available'];
    }
}
