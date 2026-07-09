<?php

namespace App\Services\ModuleEcosystem\Dto;

/**
 * Output shape for EntitlementResolverInterface::resolve().
 *
 * A single yes/no decision plus a machine-readable reason code for the
 * "no" case (e.g. license_expired, grant_revoked, no_purchase). Carries
 * no deployment/visibility information; that belongs to DeploymentState
 * and VisibilityState.
 */
readonly class EntitlementState
{
    public function __construct(
        public bool    $allowed,
        public ?string $reason = null,
    ) {}
}
