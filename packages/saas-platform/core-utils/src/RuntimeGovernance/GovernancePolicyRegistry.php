<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance;

class GovernancePolicyRegistry
{
    /** @var array<string, array> */
    private array $policies = [];

    public function register(string $name, array $policy): void
    {
        $this->policies[$name] = array_merge([
            'name'               => $name,
            'version'            => '1.0',
            'enabled'            => true,
            'trigger'            => 'periodic',
            'condition_operator' => 'AND',
            'conditions'         => [],
            'actions'            => [],
            'cooldown_seconds'   => 300,
        ], $policy, ['name' => $name]);
    }

    public function get(string $name): ?array
    {
        return $this->policies[$name] ?? null;
    }

    public function all(): array
    {
        return $this->policies;
    }

    public function enabled(): array
    {
        return array_filter($this->policies, fn($p) => $p['enabled'] ?? true);
    }

    public function matching(string $trigger): array
    {
        return array_filter($this->enabled(), fn($p) => ($p['trigger'] ?? 'periodic') === $trigger);
    }

    public function disable(string $name): void
    {
        if (isset($this->policies[$name])) {
            $this->policies[$name]['enabled'] = false;
        }
    }

    public function enable(string $name): void
    {
        if (isset($this->policies[$name])) {
            $this->policies[$name]['enabled'] = true;
        }
    }

    public function versions(): array
    {
        return array_map(fn($p) => [
            'name'    => $p['name'],
            'version' => $p['version'] ?? '1.0',
            'enabled' => $p['enabled'] ?? true,
        ], $this->policies);
    }

    public function bootstrap(): void
    {
        // Default platform governance policies
        $this->register('tenant_overload_protection', [
            'version'    => '1.0',
            'trigger'    => 'periodic',
            'conditions' => [
                ['metric' => 'tenant.error_rate',  'operator' => '>',  'threshold' => 0.15],
            ],
            'actions' => [
                ['type' => 'degradation', 'level' => 2],
                ['type' => 'telemetry',   'event' => 'governance.tenant_overload_activated'],
            ],
            'cooldown_seconds' => 600,
        ]);

        $this->register('queue_pressure_relief', [
            'version'    => '1.0',
            'trigger'    => 'periodic',
            'conditions' => [
                ['metric' => 'queue.pending_count', 'operator' => '>', 'threshold' => 500],
            ],
            'actions' => [
                ['type' => 'queue_throttle', 'factor' => 0.5],
                ['type' => 'telemetry',      'event' => 'governance.queue_pressure_relief'],
            ],
            'cooldown_seconds' => 300,
        ]);

        $this->register('critical_failure_isolation', [
            'version'            => '1.0',
            'trigger'            => 'anomaly',
            'condition_operator' => 'OR',
            'conditions' => [
                ['metric' => 'anomaly.code', 'operator' => '=', 'threshold' => 'TENANT_ISOLATION_VIOLATIONS'],
                ['metric' => 'anomaly.code', 'operator' => '=', 'threshold' => 'SECURITY_AUTH_FAILURE_BURST'],
            ],
            'actions' => [
                ['type' => 'state_transition', 'state' => GovernanceContext::STATE_CRITICAL],
                ['type' => 'deployment_freeze'],
                ['type' => 'telemetry', 'event' => 'governance.critical_failure_isolation'],
            ],
            'cooldown_seconds' => 900,
        ]);

        $this->register('stale_lock_recovery', [
            'version'    => '1.0',
            'trigger'    => 'anomaly',
            'conditions' => [
                ['metric' => 'anomaly.code', 'operator' => '=', 'threshold' => 'DEPLOYMENT_STALE_LOCK'],
            ],
            'actions' => [
                ['type' => 'recovery', 'strategy' => 'release_stale_lock'],
                ['type' => 'telemetry', 'event' => 'governance.stale_lock_recovered'],
            ],
            'cooldown_seconds' => 1800,
        ]);

        $this->register('memory_pressure_relief', [
            'version'    => '1.0',
            'trigger'    => 'periodic',
            'conditions' => [
                ['metric' => 'runtime.memory_pressure', 'operator' => '=', 'threshold' => true],
            ],
            'actions' => [
                ['type' => 'cache_clear', 'scope' => 'runtime'],
                ['type' => 'telemetry', 'event' => 'governance.memory_pressure_relief'],
            ],
            'cooldown_seconds' => 600,
        ]);
    }
}
