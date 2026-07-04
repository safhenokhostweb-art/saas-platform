<?php

namespace App\Services\CustomerInstallation\Contracts;

use App\Models\DeploymentTarget;

interface DeploymentJobInterface
{
    public function deploymentId(): string;

    /** Current queue attempt, starting at one. */
    public function attempt(): int;

    /**
     * Targets keyed by ssh, cpanel, and file_upload (or sftp).
     *
     * @return array<string, DeploymentTarget>
     */
    public function targets(): array;

    /** Execute the package deployment through the selected driver. */
    public function execute(DeploymentDriverInterface $driver): mixed;

    /** Release this job back to its queue after the bounded delay. */
    public function release(int $delaySeconds): void;
}
