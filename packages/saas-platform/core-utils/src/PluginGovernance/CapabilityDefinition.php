<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance;

final class CapabilityDefinition
{
    public const RISK_LOW      = 'low';
    public const RISK_MEDIUM   = 'medium';
    public const RISK_HIGH     = 'high';
    public const RISK_CRITICAL = 'critical';

    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly string $riskLevel,
        private readonly bool   $requiresExplicitGrant,
        private readonly array  $allowedContexts = []
    ) {}

    public function name(): string            { return $this->name; }
    public function description(): string     { return $this->description; }
    public function riskLevel(): string       { return $this->riskLevel; }
    public function requiresExplicitGrant(): bool { return $this->requiresExplicitGrant; }
    public function allowedContexts(): array  { return $this->allowedContexts; }

    public function isCritical(): bool { return $this->riskLevel === self::RISK_CRITICAL; }
    public function isHighRisk(): bool { return in_array($this->riskLevel, [self::RISK_HIGH, self::RISK_CRITICAL], true); }

    public function toArray(): array
    {
        return [
            'name'                   => $this->name,
            'description'            => $this->description,
            'risk_level'             => $this->riskLevel,
            'requires_explicit_grant'=> $this->requiresExplicitGrant,
            'allowed_contexts'       => $this->allowedContexts,
        ];
    }
}
