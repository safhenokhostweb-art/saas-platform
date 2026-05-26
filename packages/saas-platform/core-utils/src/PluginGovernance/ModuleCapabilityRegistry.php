<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance;

class ModuleCapabilityRegistry
{
    private array $definitions = [];

    public function __construct()
    {
        $this->loadDefaults();
    }

    public function register(CapabilityDefinition $definition): void
    {
        $this->definitions[$definition->name()] = $definition;
    }

    public function get(string $name): ?CapabilityDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function all(): array
    {
        return $this->definitions;
    }

    public function byRiskLevel(string $riskLevel): array
    {
        return array_filter($this->definitions, fn(CapabilityDefinition $d) => $d->riskLevel() === $riskLevel);
    }

    public function requireingExplicitGrant(): array
    {
        return array_filter($this->definitions, fn(CapabilityDefinition $d) => $d->requiresExplicitGrant());
    }

    private function loadDefaults(): void
    {
        $defaults = [
            new CapabilityDefinition('filesystem.read',     'Read files from allowed paths',             CapabilityDefinition::RISK_MEDIUM,   true,  ['plugin', 'tenant']),
            new CapabilityDefinition('filesystem.write',    'Write files to allowed paths',              CapabilityDefinition::RISK_HIGH,     true,  ['plugin', 'tenant']),
            new CapabilityDefinition('queue.dispatch',      'Dispatch jobs to queue',                    CapabilityDefinition::RISK_MEDIUM,   true,  ['plugin', 'tenant', 'system']),
            new CapabilityDefinition('notification.send',   'Send notifications to users',               CapabilityDefinition::RISK_MEDIUM,   true,  ['plugin', 'tenant']),
            new CapabilityDefinition('api.external',        'Make outbound HTTP requests',               CapabilityDefinition::RISK_HIGH,     true,  ['plugin']),
            new CapabilityDefinition('tenant.read',         'Read tenant data within own scope',         CapabilityDefinition::RISK_MEDIUM,   true,  ['plugin', 'tenant']),
            new CapabilityDefinition('tenant.write',        'Write tenant data within own scope',        CapabilityDefinition::RISK_HIGH,     true,  ['plugin', 'tenant']),
            new CapabilityDefinition('telemetry.emit',      'Emit telemetry events',                     CapabilityDefinition::RISK_LOW,      false, ['plugin', 'tenant', 'system']),
            new CapabilityDefinition('storage.upload',      'Upload files to tenant storage',            CapabilityDefinition::RISK_MEDIUM,   true,  ['plugin', 'tenant']),
            new CapabilityDefinition('runtime.schedule',    'Schedule deferred jobs',                    CapabilityDefinition::RISK_MEDIUM,   true,  ['plugin', 'system']),
            new CapabilityDefinition('deployment.execute',  'Trigger deployment or migration actions',   CapabilityDefinition::RISK_CRITICAL, true,  ['system']),
        ];

        foreach ($defaults as $definition) {
            $this->definitions[$definition->name()] = $definition;
        }
    }
}
