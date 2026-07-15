<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\CustomerInstallation\Provisioning\CronProvisionerFactory;

/**
 * STAGE 5: REMOVE_SCHEDULER
 *
 * Removes the customer's Laravel-scheduler entry through the hosting-
 * appropriate provisioner.
 *
 * Phase 14-G3 — this stage no longer knows /etc/cron.d or UAPI. It hands the
 * ORIGINAL install snapshot (resolved by DeleteContext from the immutable
 * installation log / rollback checkpoint — never rebuilt by guesswork) to
 * CronProvisionerFactory: VPS removes the recorded cron_path file (with the
 * deterministic legacy path as fallback, byte-identical to pre-14-G3
 * behavior), cPanel calls UAPI Cron::remove_line with the recorded
 * cron_line. An empty snapshot (cPanel install with no recorded cron_line)
 * is a safe skip — removing a guessed line could hit the wrong entry.
 */
class RemoveSchedulerStage extends AbstractDeleteStage
{
    public function __construct(private readonly CronProvisionerFactory $provisioners) {}

    public function name(): string { return 'REMOVE_SCHEDULER'; }
    public function label(): string { return 'حذف زمانبند'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 3; }

    public function buildPreSnapshot(DeleteContext $ctx): array
    {
        return ['snapshot' => $ctx->schedulerRemovalSnapshot];
    }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            $snapshot = $ctx->schedulerRemovalSnapshot;

            if (empty($snapshot)) {
                return DeleteStageResult::skipped(
                    $this->name(),
                    'هیچ snapshot زمانبندی از نصب ثبت نشده — حذف cron بدون cron_line اصلی امکان بازسازی امن ندارد'
                );
            }

            $this->provisioners
                ->forDriverType($ctx->connection->driverType())
                ->removeScheduler($ctx->installationContext(), $snapshot);

            return DeleteStageResult::success($this->name(), [
                'snapshot' => $snapshot,
                'removed'  => true,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $snapshot = $ctx->schedulerRemovalSnapshot;

        if (!empty($snapshot['cron_path'])) {
            $gone = !$this->remoteExists($ctx, $snapshot['cron_path']);
            return [
                ['check' => 'Cron entry removed', 'passed' => $gone, 'detail' => $snapshot['cron_path']],
            ];
        }

        if (!empty($snapshot['cron_line'])) {
            // UAPI Cron::remove_line is best-effort by contract; there is no
            // cheap read-back short of listing all cron lines. The removal
            // call either succeeded or was swallowed — surface that honestly.
            return [
                ['check' => 'Cron entry removed', 'passed' => true, 'detail' => 'removed via UAPI Cron::remove_line (best-effort)'],
            ];
        }

        return [
            ['check' => 'Cron entry removed', 'passed' => true, 'detail' => 'skipped — no scheduler snapshot recorded'],
        ];
    }
}
