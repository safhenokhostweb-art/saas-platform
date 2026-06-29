<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

/**
 * STAGE 4: REMOVE_QUEUE
 *
 * Removes the Supervisor worker configuration file and triggers a
 * supervisorctl reread+update so Supervisor forgets the program entirely.
 */
class RemoveQueueStage extends AbstractDeleteStage
{
    public function name(): string { return 'REMOVE_QUEUE'; }
    public function label(): string { return 'حذف پیکربندی صف'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 5; }

    public function buildPreSnapshot(DeleteContext $ctx): array
    {
        return [
            'config_path' => $ctx->supervisorConfigPath,
            'log_path'    => $ctx->supervisorLogPath,
        ];
    }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            $configPath = $ctx->supervisorConfigPath;
            $logPath    = $ctx->supervisorLogPath;

            // Remove config (idempotent — if absent, rm -f succeeds)
            $this->remoteExec($ctx, 'rm -f ' . escapeshellarg($configPath));

            // Remove log file
            $this->remoteExec($ctx, 'rm -f ' . escapeshellarg($logPath));

            // Tell Supervisor to reload its config and remove the now-absent program
            $this->remoteExec($ctx,
                'supervisorctl reread 2>/dev/null; supervisorctl update 2>/dev/null || true'
            );

            return DeleteStageResult::success($this->name(), [
                'config_path' => $configPath,
                'log_path'    => $logPath,
                'removed'     => true,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $configGone = !$this->remoteExists($ctx, $ctx->supervisorConfigPath);
        $programGone = !$this->supervisorProgramRunning($ctx, $ctx->supervisorProgramName);
        return [
            ['check' => 'Supervisor config removed', 'passed' => $configGone, 'detail' => $ctx->supervisorConfigPath],
            ['check' => 'Supervisor program gone',   'passed' => $programGone, 'detail' => $ctx->supervisorProgramName],
        ];
    }
}
