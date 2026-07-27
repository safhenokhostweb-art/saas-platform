<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services\Dto;

/**
 * Input to ModuleReleaseProducerInterface::record().
 * A plain value object — carries data only, no validation logic,
 * no database access.
 */
readonly class ModuleReleaseRequest
{
    /**
     * @param  ?string  $status  Phase 17-H — Part 2/6: one of ModuleRelease::STATUS_* (App\Models, not referenced here — packages never see that model). Null lets the host adapter apply its own default (published) — see ModuleReleaseCommand, the only caller that passes 'draft' explicitly, and ModuleReleaseProducerAdapter's docblock for why.
     */
    public function __construct(
        public string $slug,
        public string $version,
        public string $releaseType,
        public ?string $changelog = null,
        public ?\DateTimeInterface $distributedAt = null,
        public bool $isActive = true,
        public ?string $status = null,
    ) {}
}
