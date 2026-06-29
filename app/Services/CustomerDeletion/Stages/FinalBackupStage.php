<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeletePolicy;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use Illuminate\Support\Facades\Log;

/**
 * STAGE 2: FINAL_BACKUP
 *
 * Takes a final mysqldump of the tenant database before deletion.
 * Policy-controlled: skipped when DELETE_EVERYTHING since there is no point
 * in creating a backup that will immediately be deleted.
 * The backup is stored at {webRoot}/final_backup_{timestamp}.sql.gz on the
 * remote server; local/Arvan upload handled by separate policy.
 */
class FinalBackupStage extends AbstractDeleteStage
{
    public function name(): string { return 'FINAL_BACKUP'; }
    public function label(): string { return 'پشتیبان‌گیری نهایی'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 30; }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            // Skip when deleting everything — no point backing up before full wipe
            if ($ctx->policy === DeletePolicy::DELETE_EVERYTHING) {
                return DeleteStageResult::skipped($this->name(), 'سیاست DELETE_EVERYTHING — از پشتیبان‌گیری صرف نظر شد');
            }

            if (!$ctx->dbName) {
                return DeleteStageResult::skipped($this->name(), 'نام پایگاه داده نامشخص — از پشتیبان‌گیری صرف نظر شد');
            }

            $timestamp  = now()->format('Ymd_His');
            $backupFile = $ctx->webRoot . "/final_backup_{$timestamp}.sql.gz";

            // Run mysqldump → gzip → store in web root temporarily
            $dumpCmd = 'mysqldump -u root'
                . ' ' . escapeshellarg($ctx->dbName)
                . ' 2>/dev/null | gzip > ' . escapeshellarg($backupFile)
                . ' && echo ok';

            $out = $this->remoteExec($ctx, $dumpCmd);
            if (!str_contains($out, 'ok')) {
                // Non-critical — log warning but continue
                Log::warning("CDE: final backup failed for {$ctx->domain}");
                return DeleteStageResult::skipped($this->name(), 'mysqldump ناموفق بود — پشتیبان‌گیری رد شد');
            }

            // Get file size for the audit record
            $sizeOut = $this->remoteExec($ctx, 'stat -c%s ' . escapeshellarg($backupFile) . ' 2>/dev/null');
            $sizeBytes = (int) trim($sizeOut);

            return DeleteStageResult::success($this->name(), [
                'backup_path'  => $backupFile,
                'size_bytes'   => $sizeBytes,
                'db_name'      => $ctx->dbName,
                'created_at'   => $timestamp,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $output = $ctx->getOutput($this->name());
        if (isset($output['reason'])) {
            // Skipped — not a failure
            return [['check' => 'Final backup', 'passed' => true, 'detail' => 'skipped: ' . $output['reason']]];
        }

        $exists = !empty($output['backup_path']) && $this->remoteExists($ctx, $output['backup_path']);
        return [
            ['check' => 'Backup file exists on remote', 'passed' => $exists, 'detail' => $output['backup_path'] ?? 'n/a'],
            ['check' => 'Backup size > 0', 'passed' => ($output['size_bytes'] ?? 0) > 0, 'detail' => ($output['size_bytes'] ?? 0) . ' bytes'],
        ];
    }
}
