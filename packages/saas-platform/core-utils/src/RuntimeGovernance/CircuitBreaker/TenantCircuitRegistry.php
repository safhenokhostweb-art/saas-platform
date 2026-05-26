<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker;

class TenantCircuitRegistry
{
    public function __construct(private CircuitBreakerManager $manager) {}

    public function circuitForTenant(int $tenantId, string $resource = 'default'): RuntimeCircuit
    {
        return $this->manager->circuit("tenant.{$tenantId}.{$resource}");
    }

    public function openForTenant(int $tenantId, string $resource = 'default'): void
    {
        $this->manager->forceOpen("tenant.{$tenantId}.{$resource}");
    }

    public function resetForTenant(int $tenantId, string $resource = 'default'): void
    {
        $this->manager->reset("tenant.{$tenantId}.{$resource}");
    }

    public function tenantIsIsolated(int $tenantId): bool
    {
        $circuit = $this->manager->circuit("tenant.{$tenantId}.default");
        return $circuit->state() === RuntimeCircuit::STATE_OPEN;
    }

    public function tenantsWithOpenCircuits(): array
    {
        $open    = $this->manager->openCircuits();
        $tenants = [];

        foreach ($open as $key) {
            if (preg_match('/^tenant\.(\d+)\./', $key, $m)) {
                $tenants[] = (int) $m[1];
            }
        }

        return array_unique($tenants);
    }
}
