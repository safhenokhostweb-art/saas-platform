<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Models\Infrastructure\LocalRecoveryLog;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeletePolicy;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * STAGE 13: REMOVE_RECOVERY_METADATA
 *
 * Policy-controlled cleanup of backup artifacts:
 *
 * NEVER_DELETE_BACKUP:
 *   → Keep all LocalRecoveryLog records and all Arvan objects.
 *
 * DELETE_LOCAL_BACKUPS:
 *   → Delete LocalRecoveryLog records for this site.
 *   → Delete the local backup files from RECOVERY_LOCAL_PATH.
 *   → Keep Arvan objects.
 *
 * DELETE_REMOTE_BACKUPS:
 *   → Delete Arvan backup objects for this installation.
 *   → Keep local recovery logs.
 *
 * DELETE_EVERYTHING:
 *   → Delete both local and remote backup artifacts.
 */
class RemoveRecoveryMetadataStage extends AbstractDeleteStage
{
    public function name(): string { return 'REMOVE_RECOVERY_METADATA'; }
    public function label(): string { return 'حذف متادیتای بازیابی'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 10; }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            $policy  = $ctx->policy;
            $removed = [];

            // Local backup artifacts
            if ($policy->shouldDeleteLocalBackups() && $ctx->siteId) {
                $logs = LocalRecoveryLog::where('site_id', $ctx->siteId)->get();

                foreach ($logs as $log) {
                    // Delete the actual backup file from disk
                    if ($log->local_path && file_exists($log->local_path)) {
                        @unlink($log->local_path);
                        // Remove parent dir if empty
                        $dir = dirname($log->local_path);
                        if (is_dir($dir) && count(scandir($dir)) === 2) {
                            @rmdir($dir);
                        }
                    }
                    $log->delete();
                    $removed[] = "LocalRecoveryLog #{$log->id} deleted";
                }
            }

            // Remote (Arvan) backup artifacts
            if ($policy->shouldDeleteRemoteBackups() && $ctx->arvanBaselineKey) {
                try {
                    Storage::disk('arvan')->delete($ctx->arvanBaselineKey);
                    $removed[] = "Arvan object deleted: {$ctx->arvanBaselineKey}";
                } catch (\Throwable $e) {
                    // Non-fatal — Arvan object may have been pruned already
                    Log::warning("CDE: Arvan delete failed for {$ctx->arvanBaselineKey}: {$e->getMessage()}");
                    $removed[] = "Arvan delete skipped: {$e->getMessage()}";
                }
            }

            if (empty($removed)) {
                return DeleteStageResult::skipped(
                    $this->name(),
                    "سیاست {$policy->value} — پشتیبان‌ها حفظ شدند"
                );
            }

            return DeleteStageResult::success($this->name(), ['removed' => $removed]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        if (!$ctx->policy->shouldDeleteLocalBackups()) {
            return [['check' => 'Local backups preserved (policy)', 'passed' => true, 'detail' => $ctx->policy->value]];
        }

        if ($ctx->siteId) {
            $remaining = LocalRecoveryLog::where('site_id', $ctx->siteId)->count();
            return [
                ['check' => 'Local recovery logs cleared', 'passed' => $remaining === 0, 'detail' => "{$remaining} records remaining"],
            ];
        }

        return [['check' => 'Recovery metadata removed', 'passed' => true, 'detail' => 'no site_id to verify']];
    }
}
