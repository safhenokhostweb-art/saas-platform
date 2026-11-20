<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Contracts\DeleteStageInterface;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

abstract class AbstractDeleteStage implements DeleteStageInterface
{
    protected int $startTime = 0;

    public function isCritical(): bool
    {
        return false;
    }

    public function estimatedSeconds(): int
    {
        return 5;
    }

    public function requiresCleanRun(): bool
    {
        return false;
    }

    public function buildPreSnapshot(DeleteContext $ctx): array
    {
        return [];
    }

    public function verify(DeleteContext $ctx): array
    {
        return [];
    }

    public function rollback(DeleteContext $ctx, array $snapshot): void
    {
        // No rollback by default — deletion stages are generally irreversible.
        // Subclasses may override for specific scenarios.
    }

    protected function startTimer(): void
    {
        $this->startTime = (int) round(microtime(true) * 1000);
    }

    protected function elapsed(): int
    {
        return (int) round(microtime(true) * 1000) - $this->startTime;
    }

    protected function run(DeleteContext $ctx, callable $callback): DeleteStageResult
    {
        $this->startTimer();
        try {
            return $callback($ctx);
        } catch (\Throwable $e) {
            return DeleteStageResult::failure($this->name(), $e->getMessage(), $this->elapsed());
        }
    }

    /**
     * Run a remote command and return true if it succeeded or the resource
     * is already gone (idempotent: both outcomes are success).
     */
    protected function remoteExec(DeleteContext $ctx, string $command): string
    {
        $result = $ctx->connection->execute($command);
        return trim($result->stdout ?? '');
    }

    /**
     * Check if a remote path (file or directory) exists.
     */
    protected function remoteExists(DeleteContext $ctx, string $path): bool
    {
        $out = $this->remoteExec($ctx, 'test -e ' . escapeshellarg($path) . ' && echo yes || echo no');
        return str_contains($out, 'yes');
    }

    /**
     * Check if a remote socket/service unit is active via supervisorctl.
     */
    protected function supervisorProgramRunning(DeleteContext $ctx, string $program): bool
    {
        $out = $this->remoteExec($ctx, "supervisorctl status {$program} 2>/dev/null || echo absent");
        return str_contains($out, 'RUNNING');
    }
}
