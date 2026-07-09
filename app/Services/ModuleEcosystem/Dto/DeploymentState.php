<?php

namespace App\Services\ModuleEcosystem\Dto;

/**
 * Output shape for DeploymentResolverInterface::resolve().
 *
 * Describes whether a module is installed on a site and whether it is
 * up to date — nothing more. Carries no entitlement/license/visibility
 * information; that belongs to EntitlementState and VisibilityState.
 */
readonly class DeploymentState
{
    public const STATUS_INSTALLED       = 'installed';
    public const STATUS_NOT_INSTALLED   = 'not_installed';
    public const STATUS_UPDATE_REQUIRED = 'update_required';

    public function __construct(
        public string  $moduleSlug,
        public string  $status,
        public ?string $installedVersion = null,
        public ?string $latestVersion = null,
    ) {}
}
