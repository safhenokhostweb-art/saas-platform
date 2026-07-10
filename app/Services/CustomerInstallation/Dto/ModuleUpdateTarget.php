<?php

namespace App\Services\CustomerInstallation\Dto;

use InvalidArgumentException;

/**
 * Phase 13-I — identifies which module release a MODE_MODULE_UPDATE
 * pipeline run is deploying. Read from CustomerInstallation.metadata
 * (no new column — per the Phase 13-C design).
 */
readonly class ModuleUpdateTarget
{
    public function __construct(
        public string $moduleSlug,
        public int $moduleReleaseId,
        public string $targetVersion,
    ) {}

    public static function fromArray(array $data): self
    {
        $slug = $data['module_slug'] ?? null;
        $releaseId = $data['module_release_id'] ?? null;
        $version = $data['target_version'] ?? null;

        if (empty($slug) || empty($releaseId) || empty($version)) {
            throw new InvalidArgumentException(
                'module_slug, module_release_id, and target_version are all required for MODE_MODULE_UPDATE.'
            );
        }

        return new self(
            moduleSlug: (string) $slug,
            moduleReleaseId: (int) $releaseId,
            targetVersion: (string) $version,
        );
    }

    public function toArray(): array
    {
        return [
            'module_slug' => $this->moduleSlug,
            'module_release_id' => $this->moduleReleaseId,
            'target_version' => $this->targetVersion,
        ];
    }
}
