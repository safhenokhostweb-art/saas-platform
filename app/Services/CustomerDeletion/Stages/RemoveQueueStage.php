<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\CustomerInstallation\Provisioning\QueueProvisionerFactory;

/**
 * STAGE 4: REMOVE_QUEUE
 *
 * Removes the customer's queue worker through the hosting-appropriate
 * provisioner.
 *
 * Phase 14-G3 — this stage no longer knows Supervisor or UAPI. VPS removes
 * the recorded Supervisor config and re-runs supervisorctl reread/update
 * (via VpsQueueProvisioner — the same commands as before), cPanel removes
 * the recorded `queue:work` cron line via UAPI Cron::remove_line
 * (CpanelQueueProvisioner). The snapshot comes from what the INSTALL
 * recorded, with the deterministic legacy config path as the VPS fallback;
 * an empty snapshot (cPanel with no recorded cron_line) is a safe skip.
 *
 * The Supervisor log file cleanup is kept as a plain idempotent `rm -f` —
 * it only fires when a Supervisor-style snapshot (config_path) exists.
 */
class RemoveQueueStage extends AbstractDeleteStage
{
    public function __construct(private readonly QueueProvisionerFactory $provisioners) {}

    public function name(): string { return 'REMOVE_QUEUE'; }
    public function label(): string { return 'حذف پیکربندی صف'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 5; }

    public function buildPreSnapshot(DeleteContext $ctx): array
    {
        return ['snapshot' => $ctx->queueRemovalSnapshot];
    }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            $snapshot = $ctx->queueRemovalSnapshot;

            if (empty($snapshot)) {
                return DeleteStageResult::skipped(
                    $this->name(),
                    'هیچ snapshot صفی از نصب ثبت نشده — حذف cron worker بدون cron_line اصلی امکان بازسازی امن ندارد'
                );
            }

            $this->provisioners
                ->forDriverType($ctx->connection->driverType())
                ->removeWorker($ctx->installationContext(), $snapshot);

            // Supervisor installs also leave a log file behind; clean it up
            // with the same idempotent rm -f the old stage used.
            if (!empty($snapshot['config_path'])) {
                $this->remoteExec($ctx, 'rm -f ' . escapeshellarg($ctx->supervisorLogPath));
            }

            return DeleteStageResult::success($this->name(), [
                'snapshot' => $snapshot,
                'removed'  => true,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $snapshot = $ctx->queueRemovalSnapshot;

        if (!empty($snapshot['config_path'])) {
            $configGone  = !$this->remoteExists($ctx, $snapshot['config_path']);
            $programGone = !$this->supervisorProgramRunning($ctx, $ctx->supervisorProgramName);
            return [
                ['check' => 'Supervisor config removed', 'passed' => $configGone, 'detail' => $snapshot['config_path']],
                ['check' => 'Supervisor program gone',   'passed' => $programGone, 'detail' => $ctx->supervisorProgramName],
            ];
        }

        if (!empty($snapshot['cron_line'])) {
            return [
                ['check' => 'Queue worker cron removed', 'passed' => true, 'detail' => 'removed via UAPI Cron::remove_line (best-effort)'],
            ];
        }

        return [
            ['check' => 'Queue worker removed', 'passed' => true, 'detail' => 'skipped — no queue snapshot recorded'],
        ];
    }
}
