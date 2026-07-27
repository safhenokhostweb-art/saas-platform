<?php

namespace App\Modules\MotionGraphics\DTO;

use InvalidArgumentException;

/**
 * Declares which Motion assets a template manifest needs before a Site
 * Asset Package candidate can be built. See
 * docs/motion-saas-integration/LARAVEL_ADAPTER_CONTRACT.md
 * (`TemplateAssetPackageContract::resolveRequiredAssets`).
 */
class TemplateAssetRequestDTO
{
    /**
     * @param list<MotionAssetReferenceDTO> $requiredAssets
     * @param list<MotionAssetReferenceDTO> $optionalAssets
     */
    public function __construct(
        public readonly string $templateId,
        public readonly array $requiredAssets = [],
        public readonly array $optionalAssets = [],
    ) {
        foreach ([...$requiredAssets, ...$optionalAssets] as $asset) {
            if (! $asset instanceof MotionAssetReferenceDTO) {
                throw new InvalidArgumentException(
                    'TemplateAssetRequestDTO assets must be MotionAssetReferenceDTO instances.'
                );
            }
        }
    }

    /**
     * @param array{template_id: string, required_assets?: array<int, array<string, mixed>>, optional_assets?: array<int, array<string, mixed>>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            templateId: $data['template_id'],
            requiredAssets: array_map(
                static fn (array $asset): MotionAssetReferenceDTO => MotionAssetReferenceDTO::fromArray($asset),
                $data['required_assets'] ?? []
            ),
            optionalAssets: array_map(
                static fn (array $asset): MotionAssetReferenceDTO => MotionAssetReferenceDTO::fromArray($asset),
                $data['optional_assets'] ?? []
            ),
        );
    }
}
