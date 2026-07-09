<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services\Dto;

/**
 * Output of ModuleReleaseProducerInterface::record().
 */
readonly class ModuleReleaseResult
{
    public const STATUS_CREATED               = 'created';
    public const STATUS_SKIPPED_NO_MODULE_ROW = 'skipped_no_module_row';
    public const STATUS_SKIPPED_DUPLICATE     = 'skipped_duplicate';
    public const STATUS_FAILED                = 'failed';

    public function __construct(
        public string $status,
        public ?string $reason = null,
        public ?int $releaseId = null,
    ) {}
}
