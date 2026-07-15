<?php

namespace App\Services\CustomerInstallation\Contracts;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

interface InstallationStageInterface
{
    public function name(): string;

    public function label(): string;

    public function execute(InstallationContext $ctx): StageResult;

    public function rollback(InstallationContext $ctx, array $snapshot): void;

    public function canRollback(): bool;

    /**
     * Phase 13-M3b — true only for stages whose rollback() failing leaves
     * the system in a dangerous, inconsistent state (e.g. a database left
     * in its post-migration state with no schema-compatible code running
     * against it). When true and rollback() throws, the LIFO replay in
     * CustomerInstallationRollbackService halts immediately instead of
     * continuing to older checkpoints — see BackupModuleUpgradeStage.
     */
    public function isCriticalForSafety(): bool;

    public function estimatedSeconds(): int;

    /**
     * Phase 14-E — capability keys (DriverCapabilities public property names,
     * e.g. 'supportsDatabaseCreation') this stage's actual implementation
     * needs from the connected driver. Empty array = works on any driver,
     * no gate. Checked by CustomerInstallationOrchestrator via
     * StageCapabilityGate before execute() is called.
     */
    public function requiredCapabilities(): array;

    /**
     * When requiredCapabilities() are not met: true = the orchestrator fails
     * the installation with a clear message (this capability is essential —
     * the site cannot function without it); false = the orchestrator skips
     * this stage (logs a warning, records it complete with a 'skipped'
     * marker) and continues the pipeline (this capability is optional infra).
     */
    public function isCapabilityRequiredToProceed(): bool;
}
