<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

/**
 * STAGE 3: STOP_SERVICES
 *
 * Gracefully stops the customer's Supervisor worker before removing configs.
 * This prevents Supervisor from throwing FATAL errors when its config
 * disappears while a worker is still registered.
 *
 * Phase 14-G3 — gated on supportsSupervisor: cPanel (and any other driver
 * without Supervisor) skips safely instead of running a meaningless
 * `supervisorctl stop`. Its cron-based worker (`queue:work
 * --stop-when-empty`) exits on its own within a minute; the cron line
 * itself is removed by REMOVE_QUEUE.
 */
class StopServicesStage extends AbstractDeleteStage
{
    public function name(): string { return 'STOP_SERVICES'; }
    public function label(): string { return 'توقف سرویس‌ها'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 8; }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            if (!$ctx->connection->capabilities()->supportsSupervisor) {
                return DeleteStageResult::skipped(
                    $this->name(),
                    'این درایور Supervisor ندارد — worker مبتنی بر cron خودش خاتمه می‌یابد'
                );
            }

            $program = $ctx->supervisorProgramName;
            $stopped = [];
            $errors  = [];

            // Stop supervisor worker (idempotent — already-stopped is success)
            $stopOut = $this->remoteExec($ctx,
                "supervisorctl stop {$program} 2>&1 || echo 'not_running'"
            );
            $stopped[] = [
                'program' => $program,
                'result'  => trim($stopOut),
                'was_running' => !str_contains($stopOut, 'not_running') && !str_contains($stopOut, 'no such process'),
            ];

            // Short pause to let the worker drain in-progress jobs
            $this->remoteExec($ctx, 'sleep 2');

            return DeleteStageResult::success($this->name(), [
                'stopped' => $stopped,
                'errors'  => $errors,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        if (!$ctx->connection->capabilities()->supportsSupervisor) {
            return [
                ['check' => 'Services stopped', 'passed' => true, 'detail' => 'skipped — driver has no Supervisor'],
            ];
        }

        $running = $this->supervisorProgramRunning($ctx, $ctx->supervisorProgramName);
        return [
            ['check' => "Supervisor {$ctx->supervisorProgramName} stopped", 'passed' => !$running, 'detail' => $running ? 'still RUNNING' : 'not running'],
        ];
    }
}
