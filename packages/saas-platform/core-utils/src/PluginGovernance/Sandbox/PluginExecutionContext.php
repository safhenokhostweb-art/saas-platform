<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Sandbox;

final class PluginExecutionContext
{
    public const CONTEXT_PLUGIN = 'plugin';
    public const CONTEXT_TENANT = 'tenant';
    public const CONTEXT_SYSTEM = 'system';

    public function __construct(
        private readonly string  $moduleId,
        private readonly string  $context,
        private readonly ?int    $tenantId,
        private readonly array   $grantedCapabilities,
        private readonly string  $executionId,
        private readonly float   $createdAt
    ) {}

    public static function make(
        string $moduleId,
        string $context,
        ?int   $tenantId,
        array  $grantedCapabilities
    ): self {
        return new self(
            $moduleId,
            $context,
            $tenantId,
            $grantedCapabilities,
            uniqid('exec_', true),
            microtime(true)
        );
    }

    public function moduleId(): string          { return $this->moduleId; }
    public function context(): string           { return $this->context; }
    public function tenantId(): ?int            { return $this->tenantId; }
    public function grantedCapabilities(): array { return $this->grantedCapabilities; }
    public function executionId(): string       { return $this->executionId; }

    public function can(string $capability): bool
    {
        return in_array($capability, $this->grantedCapabilities, true);
    }

    public function elapsedMs(): float
    {
        return (microtime(true) - $this->createdAt) * 1000;
    }

    public function toArray(): array
    {
        return [
            'module_id'            => $this->moduleId,
            'context'              => $this->context,
            'tenant_id'            => $this->tenantId,
            'granted_capabilities' => $this->grantedCapabilities,
            'execution_id'         => $this->executionId,
        ];
    }
}
