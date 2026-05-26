<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker;

class ModuleCircuitRegistry
{
    private const KNOWN_MODULES = ['tickets', 'forms', 'notifications', 'reports'];

    public function __construct(private CircuitBreakerManager $manager) {}

    public function circuitForModule(string $module): RuntimeCircuit
    {
        return $this->manager->circuit("module.{$module}");
    }

    public function openForModule(string $module): void
    {
        $this->manager->forceOpen("module.{$module}");
    }

    public function resetForModule(string $module): void
    {
        $this->manager->reset("module.{$module}");
    }

    public function moduleIsDisabled(string $module): bool
    {
        $circuit = $this->manager->circuit("module.{$module}");
        return $circuit->state() === RuntimeCircuit::STATE_OPEN;
    }

    public function disabledModules(): array
    {
        $open     = $this->manager->openCircuits();
        $disabled = [];

        foreach ($open as $key) {
            if (preg_match('/^module\.(.+)$/', $key, $m)) {
                $disabled[] = $m[1];
            }
        }

        return $disabled;
    }

    public function allModuleStates(): array
    {
        return array_map(fn($m) => [
            'module' => $m,
            'state'  => $this->manager->circuit("module.{$m}")->state(),
        ], self::KNOWN_MODULES);
    }
}
