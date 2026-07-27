<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Opaque, allowlisted reference to one published Motion Graphics asset
 * version. Mirrors the `asset_id` / `version` identity described in
 * docs/motion-saas-integration/LARAVEL_ADAPTER_CONTRACT.md — never carries
 * source definitions, editable schema, or AI metadata.
 */
class MotionAssetReferenceDTO
{
    /**
     * @param array<string, scalar|array<int|string, mixed>|null> $compatibility
     */
    public function __construct(
        public readonly string $assetId,
        public readonly int $version,
        public readonly string $type,
        public readonly array $compatibility = [],
    ) {
        ForbiddenFieldGuard::assertClean($compatibility, 'compatibility');
    }

    /**
     * @param array{asset_id: string, version: int, type: string, compatibility?: array<string, mixed>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            assetId: $data['asset_id'],
            version: (int) $data['version'],
            type: $data['type'],
            compatibility: $data['compatibility'] ?? [],
        );
    }

    /**
     * @return array{asset_id: string, version: int, type: string, compatibility: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'asset_id' => $this->assetId,
            'version' => $this->version,
            'type' => $this->type,
            'compatibility' => $this->compatibility,
        ];
    }
}
