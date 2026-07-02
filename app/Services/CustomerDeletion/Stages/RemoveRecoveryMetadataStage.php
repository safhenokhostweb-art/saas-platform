<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Models\Infrastructure\LocalRecoveryLog;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeletePolicy;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

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
    public function isCritical(): bool { return true; }
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
                    if ($log->local_path && is_dir($log->local_path)) {
                        File::deleteDirectory($log->local_path);
                    } elseif ($log->local_path && file_exists($log->local_path)) {
                        File::delete($log->local_path);
                    }

                    if ($log->local_path && file_exists($log->local_path)) {
                        return DeleteStageResult::failure(
                            $this->name(),
                            "حذف فایل پشتیبان محلی ناموفق بود: {$log->local_path}"
                        );
                    }

                    $log->delete();
                    $removed[] = "LocalRecoveryLog #{$log->id} deleted";
                }
            }

            // Remote (Arvan) backup artifacts
            if ($policy->shouldDeleteRemoteBackups() && $ctx->siteId) {
                try {
                    $disk = Storage::disk('arvan_backup');
                    $prefixes = [
                        "customers/site-{$ctx->siteId}",
                        "customer-sites/tenant-{$ctx->siteId}",
                    ];

                    foreach ($prefixes as $prefix) {
                        $disk->deleteDirectory($prefix);

                        if (!empty($disk->allFiles($prefix))) {
                            return DeleteStageResult::failure(
                                $this->name(),
                                "حذف کامل prefix پشتیبان remote ناموفق بود: {$prefix}"
                            );
                        }

                        $removed[] = "Arvan prefix deleted: {$prefix}";
                    }
                } catch (\Throwable $e) {
                    Log::warning("CDE: Arvan backup prefix deletion failed for site {$ctx->siteId}: {$e->getMessage()}");
                    return DeleteStageResult::failure($this->name(), 'حذف پشتیبان‌های remote ناموفق بود: ' . $e->getMessage());
                }
            }

            if (empty($removed)) {
                $reason = match(true) {
                    !$policy->shouldDeleteLocalBackups() && !$policy->shouldDeleteRemoteBackups()
                        => "سیاست {$policy->value} — پشتیبان‌ها حفظ می‌شوند",
                    default
                        => "هیچ متادیتای قابل حذفی یافت نشد (site_id یا arvan_key وجود ندارد)",
                };
                return DeleteStageResult::skipped($this->name(), $reason);
            }

            return DeleteStageResult::success($this->name(), ['removed' => $removed]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $checks = [];

        if ($ctx->siteId) {
            if ($ctx->policy->shouldDeleteLocalBackups()) {
                $remaining = LocalRecoveryLog::where('site_id', $ctx->siteId)->count();
                $checks[] = [
                    'check' => 'Local recovery logs cleared',
                    'passed' => $remaining === 0,
                    'detail' => "{$remaining} records remaining",
                ];
            } else {
                $checks[] = ['check' => 'Local backups preserved (policy)', 'passed' => true, 'detail' => $ctx->policy->value];
            }

            if ($ctx->policy->shouldDeleteRemoteBackups()) {
                $disk = Storage::disk('arvan_backup');
                $remoteRemaining = array_merge(
                    $disk->allFiles("customers/site-{$ctx->siteId}"),
                    $disk->allFiles("customer-sites/tenant-{$ctx->siteId}")
                );
                $checks[] = [
                    'check' => 'Remote recovery prefixes cleared',
                    'passed' => empty($remoteRemaining),
                    'detail' => count($remoteRemaining) . ' objects remaining',
                ];
            } else {
                $checks[] = ['check' => 'Remote backups preserved (policy)', 'passed' => true, 'detail' => $ctx->policy->value];
            }

            return $checks;
        }

        return [['check' => 'Recovery metadata removed', 'passed' => true, 'detail' => 'no site_id to verify']];
    }
}
