<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Contracts\InstallationStageInterface;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

abstract class AbstractStage implements InstallationStageInterface
{
    protected int $startTime = 0;

    public function canRollback(): bool
    {
        return false;
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        // No rollback by default — override in stages that have state
    }

    public function estimatedSeconds(): int
    {
        return 10;
    }

    /**
     * Return the rollback snapshot data that can be computed BEFORE execution.
     * Used by the orchestrator to store a meaningful checkpoint immediately,
     * so rollback has actionable data even if the stage fails mid-way.
     * Override in stages where the remote path / resource is known upfront.
     */
    public function buildPreSnapshot(InstallationContext $ctx): array
    {
        return [];
    }

    protected function startTimer(): void
    {
        $this->startTime = (int) round(microtime(true) * 1000);
    }

    protected function elapsed(): int
    {
        return (int) round(microtime(true) * 1000) - $this->startTime;
    }

    protected function run(InstallationContext $ctx, callable $callback): StageResult
    {
        $this->startTimer();
        try {
            return $callback($ctx);
        } catch (\Throwable $e) {
            return StageResult::failure($this->name(), $e->getMessage(), $this->elapsed());
        }
    }
}
