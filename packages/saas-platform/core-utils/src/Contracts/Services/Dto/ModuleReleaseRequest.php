<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services\Dto;

/**
 * Input to ModuleReleaseProducerInterface::record().
 * A plain value object — carries data only, no validation logic,
 * no database access.
 */
readonly class ModuleReleaseRequest
{
    public function __construct(
        public string $slug,
        public string $version,
        public string $releaseType,
        public ?string $changelog = null,
        public ?\DateTimeInterface $distributedAt = null,
        public bool $isActive = true,
    ) {}
}
