<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

/**
 * STAGE 5: REMOVE_SCHEDULER
 *
 * Removes the customer's cron.d entry, stopping the Laravel scheduler
 * from running for the now-deleted tenant.
 */
class RemoveSchedulerStage extends AbstractDeleteStage
{
    public function name(): string { return 'REMOVE_SCHEDULER'; }
    public function label(): string { return 'حذف زمانبند'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 3; }

    public function buildPreSnapshot(DeleteContext $ctx): array
    {
        return ['cron_path' => $ctx->cronPath];
    }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            $cronPath = $ctx->cronPath;

            $this->remoteExec($ctx, 'rm -f ' . escapeshellarg($cronPath));

            return DeleteStageResult::success($this->name(), [
                'cron_path' => $cronPath,
                'removed'   => true,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $gone = !$this->remoteExists($ctx, $ctx->cronPath);
        return [
            ['check' => 'Cron entry removed', 'passed' => $gone, 'detail' => $ctx->cronPath],
        ];
    }
}
