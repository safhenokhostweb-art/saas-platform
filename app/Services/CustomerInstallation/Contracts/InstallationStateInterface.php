<?php

namespace App\Services\CustomerInstallation\Contracts;

use App\Models\CustomerInstallation;

interface InstallationStateInterface
{
    public function markRunning(CustomerInstallation $installation): void;

    public function recordStageStart(CustomerInstallation $installation, string $stage): void;

    public function recordStageComplete(
        CustomerInstallation $installation,
        string $stage,
        array $output,
        int $durationMs
    ): void;

    public function recordStageFailure(
        CustomerInstallation $installation,
        string $stage,
        string $error,
        int $durationMs
    ): void;

    public function recordRollbackCheckpoint(
        CustomerInstallation $installation,
        string $stage,
        array $snapshot
    ): void;

    public function recordWarning(CustomerInstallation $installation, string $stage, string $message): void;

    public function markComplete(CustomerInstallation $installation): void;

    public function markFailed(CustomerInstallation $installation, string $reason): void;

    public function markRollingBack(CustomerInstallation $installation): void;

    public function markRolledBack(CustomerInstallation $installation): void;

    public function markRollbackFailed(CustomerInstallation $installation, string $reason): void;
}
