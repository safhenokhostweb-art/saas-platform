<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeletePolicy;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Installer\LocalRecoveryRepository;
use Illuminate\Support\Facades\Log;

/**
 * STAGE 2: FINAL_BACKUP
 *
 * Takes a final mysqldump of the tenant database before deletion.
 * Policy-controlled: skipped when DELETE_EVERYTHING since there is no point
 * in creating a backup that will immediately be deleted.
 * The backup is created temporarily on the remote server, then persisted in
 * the configured recovery repository outside the customer web root.
 */
class FinalBackupStage extends AbstractDeleteStage
{
    public function __construct(
        private readonly ArvanBackupService $arvan,
        private readonly LocalRecoveryRepository $localRepo,
    ) {}

    public function name(): string { return 'FINAL_BACKUP'; }
    public function label(): string { return 'پشتیبان‌گیری نهایی'; }
    public function isCritical(): bool { return true; }
    public function estimatedSeconds(): int { return 30; }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            // Skip when deleting everything — no point backing up before full wipe
            if ($ctx->policy === DeletePolicy::DELETE_EVERYTHING) {
                return DeleteStageResult::skipped($this->name(), 'سیاست DELETE_EVERYTHING — از پشتیبان‌گیری صرف نظر شد');
            }

            if (!$ctx->dbName || !$ctx->siteId) {
                return DeleteStageResult::failure($this->name(), 'متادیتای دیتابیس یا site_id برای پشتیبان نهایی کامل نیست');
            }

            $timestamp  = now()->format('Ymd_His');
            $backupFile = "/tmp/{$ctx->slug}_final_backup_{$timestamp}.sql.gz";

            // Run mysqldump and gzip into a temporary remote file.
            $dumpCmd = 'mysqldump -u root'
                . ' ' . escapeshellarg($ctx->dbName)
                . ' 2>/dev/null | gzip > ' . escapeshellarg($backupFile)
                . ' && echo ok';

            $out = $this->remoteExec($ctx, $dumpCmd);
            if (!str_contains($out, 'ok')) {
                Log::warning("CDE: final backup failed for {$ctx->domain}");
                return DeleteStageResult::failure($this->name(), 'mysqldump پشتیبان نهایی ناموفق بود');
            }

            // Get file size for the audit record
            $sizeOut = $this->remoteExec($ctx, 'stat -c%s ' . escapeshellarg($backupFile) . ' 2>/dev/null');
            $sizeBytes = (int) trim($sizeOut);

            if ($sizeBytes < 100) {
                $ctx->connection->execute('rm -f ' . escapeshellarg($backupFile));
                return DeleteStageResult::failure($this->name(), 'فایل پشتیبان نهایی خالی یا ناقص است');
            }

            $localPath = storage_path("app/install-packages/delete_final_{$ctx->siteId}_{$timestamp}.sql.gz");
            @mkdir(dirname($localPath), 0750, true);

            try {
                $ctx->connection->download($backupFile, $localPath);
                $sha256 = hash_file('sha256', $localPath);

                $keepLocal = match ($ctx->policy) {
                    DeletePolicy::NEVER_DELETE_BACKUP => $this->localRepo->shouldUseLocal(),
                    DeletePolicy::DELETE_REMOTE_BACKUPS => true,
                    default => false,
                };
                $keepRemote = match ($ctx->policy) {
                    DeletePolicy::NEVER_DELETE_BACKUP => $this->localRepo->shouldUseArvan(),
                    DeletePolicy::DELETE_LOCAL_BACKUPS => true,
                    default => false,
                };

                if (!$keepLocal && !$keepRemote) {
                    return DeleteStageResult::failure($this->name(), 'هیچ مقصد recovery فعالی برای پشتیبان نهایی وجود ندارد');
                }
                $localResult = null;
                $remoteResult = null;

                if ($keepLocal) {
                    $localResult = $this->localRepo->storeCustomerBaseline(
                        siteId: $ctx->siteId,
                        installationUuid: (string) $ctx->installation->id,
                        sqlLocalPath: $localPath,
                        sha256: $sha256,
                    );
                }

                if ($keepRemote) {
                    $remotePath = "customers/site-{$ctx->siteId}/baselines/FINAL_DELETE_BACKUP_{$timestamp}.sql.gz";
                    $remoteResult = $this->arvan->upload($localPath, $remotePath);
                }

                $localOk = !$keepLocal || (($localResult['success'] ?? false) && !($localResult['skipped'] ?? false));
                $remoteOk = !$keepRemote || ($remoteResult['success'] ?? false);

                if (!$localOk || !$remoteOk) {
                    return DeleteStageResult::failure(
                        $this->name(),
                        'پشتیبان نهایی در مقصدهای الزامی ذخیره یا تأیید نشد'
                    );
                }

                return DeleteStageResult::success($this->name(), [
                    'size_bytes' => $sizeBytes,
                    'sha256' => $sha256,
                    'local_path' => $localResult['local_path'] ?? null,
                    'remote_path' => $remotePath ?? null,
                    'db_name' => $ctx->dbName,
                    'created_at' => $timestamp,
                ]);
            } finally {
                @unlink($localPath);
                $ctx->connection->execute('rm -f ' . escapeshellarg($backupFile));
            }
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $output = $ctx->getOutput($this->name());
        if (isset($output['reason'])) {
            // Skipped — not a failure
            return [['check' => 'Final backup', 'passed' => true, 'detail' => 'skipped: ' . $output['reason']]];
        }

        return [
            ['check' => 'Backup persisted outside customer web root', 'passed' => !empty($output['local_path']) || !empty($output['remote_path']), 'detail' => $output['local_path'] ?? $output['remote_path'] ?? 'n/a'],
            ['check' => 'Backup size > 0', 'passed' => ($output['size_bytes'] ?? 0) > 0, 'detail' => ($output['size_bytes'] ?? 0) . ' bytes'],
            ['check' => 'Backup SHA-256 recorded', 'passed' => !empty($output['sha256']), 'detail' => $output['sha256'] ?? 'n/a'],
        ];
    }
}
