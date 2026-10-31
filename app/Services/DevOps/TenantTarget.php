<?php

namespace App\Services\DevOps;

use App\Models\Site;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;

/**
 * Where a site's files physically live and how to reach them. The driver is
 * not connected yet; callers connect, run, and disconnect it.
 */
final class TenantTarget
{
    public function __construct(
        public readonly Site                      $site,
        public readonly DeploymentDriverInterface $driver,
        public readonly string                    $webRoot,
        public readonly ?int                      $installationId = null,
        public readonly ?int                      $serverId = null,
    ) {}
}
