<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Immutable deployment manifest (task scope item 2). Content fields
 * (everything but `status`) never change after construction — a status
 * transition produces a new instance via withStatus(), never a mutation.
 * `checksum` is computed over the content fields only, so it stays
 * stable across the lifecycle described in
 * docs/motion-deployment-preparation/DEPLOYMENT_LIFECYCLE.md.
 *
 * Allowed: site reference, template reference, package version, asset
 * references, runtime projection references, checksum, compatibility
 * result.
 *
 * Forbidden: source files, AI metadata, internal storage paths — never
 * present, by construction: `assetReferences`/`runtimeProjectionReferences`
 * only ever hold already-allowlisted MotionAssetReferenceDTO/
 * RuntimeProjectionDTO values (Phase 1/2), which themselves run
 * ForbiddenFieldGuard at construction.
 */
final class DeploymentManifest
{
    public const STATUS_PREPARED = 'prepared';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_READY = 'ready';
    public const STATUS_DEPLOYED = 'deployed'; // reserved for a future phase — never reached here

    /**
     * @param list<MotionAssetReferenceDTO> $assetReferences
     * @param list<RuntimeProjectionDTO> $runtimeProjectionReferences
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $siteReference,
        public readonly string $templateReference,
        public readonly int $packageVersion,
        public readonly array $assetReferences,
        public readonly array $runtimeProjectionReferences,
        public readonly string $checksum,
        public readonly ValidationReport $compatibilityResult,
        public readonly string $status = self::STATUS_PREPARED,
    ) {}

    public function withStatus(string $status): self
    {
        return new self(
            $this->uuid,
            $this->siteReference,
            $this->templateReference,
            $this->packageVersion,
            $this->assetReferences,
            $this->runtimeProjectionReferences,
            $this->checksum,
            $this->compatibilityResult,
            $status,
        );
    }

    public function toCustomerRuntimePackage(): CustomerRuntimePackage
    {
        return new CustomerRuntimePackage($this->runtimeProjectionReferences);
    }
}
