<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Phase 8 (docs/motion-customer-runtime-phase8/MANIFEST_SCHEMA.md): the
 * customer-safe runtime manifest delivered to a customer-site over the
 * platform's existing `tenant.api` channel. Explicit schema version so a
 * future incompatible shape can be rejected by old consumers rather than
 * silently misread — see task requirement "use an explicit schema
 * version... reject unknown schema versions."
 *
 * Allowed: published runtime asset references, optimized output
 * references, approved theme tokens, placement rules, responsive rules,
 * checksums, package identity. Forbidden fields are structurally absent
 * (see CustomerRuntimeAssetEntry) — this class adds no additional
 * fields of its own beyond identity/versioning, so there is nothing
 * further to guard here.
 */
final class CustomerRuntimeManifest
{
    public const SCHEMA_VERSION = '1.0';

    /**
     * @param list<CustomerRuntimeAssetEntry> $assets
     */
    public function __construct(
        public readonly string $siteReference,
        public readonly string $templateReference,
        public readonly string $packageId,
        public readonly int $packageVersion,
        public readonly string $generatedAt,
        public readonly array $assets,
        public readonly string $manifestChecksum,
        public readonly string $schemaVersion = self::SCHEMA_VERSION,
    ) {}

    /**
     * @return array{schema_version: string, site_reference: string, template_reference: string, package_id: string, package_version: int, generated_at: string, assets: list<array<string,mixed>>, manifest_checksum: string}
     */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'site_reference' => $this->siteReference,
            'template_reference' => $this->templateReference,
            'package_id' => $this->packageId,
            'package_version' => $this->packageVersion,
            'generated_at' => $this->generatedAt,
            'assets' => array_map(fn (CustomerRuntimeAssetEntry $a): array => $a->toArray(), $this->assets),
            'manifest_checksum' => $this->manifestChecksum,
        ];
    }
}
