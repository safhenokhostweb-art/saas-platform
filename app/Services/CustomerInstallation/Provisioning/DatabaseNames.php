<?php

namespace App\Services\CustomerInstallation\Provisioning;

/** Phase 14-F2 — resolved database/user identifiers for a single installation. */
readonly class DatabaseNames
{
    public function __construct(
        public readonly string $database,
        public readonly string $username,
    ) {}
}
