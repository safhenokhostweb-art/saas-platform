<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Marketplace;

final class ExtensionDistributionManifest
{
    public function __construct(
        private readonly string $moduleId,
        private readonly string $version,
        private readonly string $publisherId,
        private readonly string $integrityHash,
        private readonly array  $files,
        private readonly array  $capabilities,
        private readonly string $licenseType,
        private readonly string $publishedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['module_id'],
            $data['version'],
            $data['publisher_id'],
            $data['integrity_hash'],
            $data['files'] ?? [],
            $data['capabilities'] ?? [],
            $data['license_type'] ?? 'proprietary',
            $data['published_at'] ?? now()->toIso8601String()
        );
    }

    public function moduleId(): string    { return $this->moduleId; }
    public function version(): string     { return $this->version; }
    public function publisherId(): string { return $this->publisherId; }
    public function integrityHash(): string { return $this->integrityHash; }
    public function files(): array        { return $this->files; }
    public function capabilities(): array { return $this->capabilities; }
    public function licenseType(): string { return $this->licenseType; }
    public function publishedAt(): string { return $this->publishedAt; }

    public function toArray(): array
    {
        return [
            'module_id'      => $this->moduleId,
            'version'        => $this->version,
            'publisher_id'   => $this->publisherId,
            'integrity_hash' => $this->integrityHash,
            'files'          => $this->files,
            'capabilities'   => $this->capabilities,
            'license_type'   => $this->licenseType,
            'published_at'   => $this->publishedAt,
        ];
    }
}
