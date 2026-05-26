<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Marketplace;

final class MarketplaceModuleDescriptor
{
    public function __construct(
        private readonly string  $moduleId,
        private readonly string  $name,
        private readonly string  $version,
        private readonly string  $publisherId,
        private readonly string  $description,
        private readonly array   $requiredCapabilities,
        private readonly array   $optionalCapabilities,
        private readonly string  $minPlatformVersion,
        private readonly string  $integrityHash
    ) {}

    public function moduleId(): string              { return $this->moduleId; }
    public function name(): string                  { return $this->name; }
    public function version(): string               { return $this->version; }
    public function publisherId(): string           { return $this->publisherId; }
    public function description(): string           { return $this->description; }
    public function requiredCapabilities(): array   { return $this->requiredCapabilities; }
    public function optionalCapabilities(): array   { return $this->optionalCapabilities; }
    public function minPlatformVersion(): string    { return $this->minPlatformVersion; }
    public function integrityHash(): string         { return $this->integrityHash; }

    public function allCapabilities(): array
    {
        return array_unique(array_merge($this->requiredCapabilities, $this->optionalCapabilities));
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['module_id'],
            $data['name'],
            $data['version'],
            $data['publisher_id'],
            $data['description'] ?? '',
            $data['required_capabilities'] ?? [],
            $data['optional_capabilities'] ?? [],
            $data['min_platform_version'] ?? '8.0.0',
            $data['integrity_hash'] ?? ''
        );
    }

    public function toArray(): array
    {
        return [
            'module_id'             => $this->moduleId,
            'name'                  => $this->name,
            'version'               => $this->version,
            'publisher_id'          => $this->publisherId,
            'description'           => $this->description,
            'required_capabilities' => $this->requiredCapabilities,
            'optional_capabilities' => $this->optionalCapabilities,
            'min_platform_version'  => $this->minPlatformVersion,
            'integrity_hash'        => $this->integrityHash,
        ];
    }
}
