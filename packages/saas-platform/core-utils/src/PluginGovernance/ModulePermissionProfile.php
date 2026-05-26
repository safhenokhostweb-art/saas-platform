<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance;

final class ModulePermissionProfile
{
    public function __construct(
        private readonly string $moduleId,
        private readonly array  $granted,
        private readonly array  $denied
    ) {}

    public function moduleId(): string  { return $this->moduleId; }
    public function granted(): array    { return $this->granted; }
    public function denied(): array     { return $this->denied; }

    public function can(string $capability): bool
    {
        return in_array($capability, $this->granted, true);
    }

    public function cannot(string $capability): bool
    {
        return !$this->can($capability);
    }

    public function grantCount(): int { return count($this->granted); }
    public function denyCount(): int  { return count($this->denied); }

    public function isMinimal(): bool
    {
        return $this->grantCount() === 0;
    }

    public function toArray(): array
    {
        return [
            'module_id'      => $this->moduleId,
            'granted'        => $this->granted,
            'denied'         => $this->denied,
            'grant_count'    => $this->grantCount(),
            'deny_count'     => $this->denyCount(),
            'captured_at'    => now()->toIso8601String(),
        ];
    }
}
