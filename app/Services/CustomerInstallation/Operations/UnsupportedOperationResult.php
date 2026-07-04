<?php

namespace App\Services\CustomerInstallation\Operations;

final readonly class UnsupportedOperationResult
{
    /** @param list<string> $missingCapabilities */
    public function __construct(
        public string $operation,
        public string $reason,
        public array $missingCapabilities = [],
    ) {}
}
