<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Marketplace;

final class PluginCompatibilityProfile
{
    public function __construct(
        private readonly string $moduleId,
        private readonly bool   $compatible,
        private readonly array  $missingCapabilities,
        private readonly array  $incompatibleReasons,
        private readonly string $platformVersion
    ) {}

    public function moduleId(): string              { return $this->moduleId; }
    public function isCompatible(): bool            { return $this->compatible; }
    public function missingCapabilities(): array    { return $this->missingCapabilities; }
    public function incompatibleReasons(): array    { return $this->incompatibleReasons; }
    public function platformVersion(): string       { return $this->platformVersion; }

    public function hasMissingCapabilities(): bool
    {
        return !empty($this->missingCapabilities);
    }

    public function toArray(): array
    {
        return [
            'module_id'              => $this->moduleId,
            'compatible'             => $this->compatible,
            'missing_capabilities'   => $this->missingCapabilities,
            'incompatible_reasons'   => $this->incompatibleReasons,
            'platform_version'       => $this->platformVersion,
        ];
    }
}
