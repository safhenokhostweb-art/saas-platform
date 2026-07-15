<?php

namespace App\Services\CustomerInstallation\Provisioning;

/**
 * Phase 14-F3 — shared result shape for "install a background task"
 * provisioners (cron scheduler, queue worker). Both mechanisms have the
 * same two outcomes the pre-14-F3 Stages already encoded ad hoc: genuinely
 * installed (with a rollback snapshot), or gracefully skipped (no snapshot,
 * not a failure) — never a hard failure, since scheduler/queue are optional
 * infra by design (see ConfigureSchedulerStage / ConfigureQueueStage
 * isCapabilityRequiredToProceed()).
 */
readonly class ProvisioningResult
{
    public function __construct(
        public readonly bool $installed,
        public readonly array $output = [],
        public readonly array $snapshot = [],
    ) {}

    public static function installed(array $output, array $snapshot): self
    {
        return new self(true, $output, $snapshot);
    }

    public static function skipped(string $message): self
    {
        return new self(false, ['status' => 'skipped', 'message' => $message], []);
    }
}
