<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Models\Infrastructure\LocalRecoveryLog;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\CustomerDeletion\Guards\ObjectPrefixGuard;
use App\Services\CustomerDeletion\Purge\TenantObjectPurger;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * STAGE: REMOVE_RECOVERY_METADATA
 *
 * Policy-controlled cleanup of backup artifacts:
 *
 * NEVER_DELETE_BACKUP   → keep everything (local + Arvan).
 * DELETE_LOCAL_BACKUPS  → local recovery files + LocalRecoveryLog rows + local
 *                         site archives; Arvan kept.
 * DELETE_REMOTE_BACKUPS → every Arvan backup prefix of the site; local kept.
 * DELETE_EVERYTHING     → both.
 *
 * ADR-090 — the Arvan side covers ALL backup prefixes of the site
 * (ObjectPrefixGuard::backupPrefixes): tenants/site-{id}/ (db:backup-sites,
 * previously never deleted), customers/site-{id}/, customer-sites/tenant-{id}/
 * and saas-platform/archives/site_{id}_. Deletion goes through
 * TenantObjectPurger (paginated, every key re-checked, versioning-aware,
 * verified empty afterwards).
 *
 * Critical: a backup that should be gone but is not stops the pipeline
 * BEFORE the platform records are removed, so Retry can finish the job.
 */
class RemoveRecoveryMetadataStage extends AbstractDeleteStage
{
    public function __construct(private readonly TenantObjectPurger $purger) {}

    public function name(): string { return 'REMOVE_RECOVERY_METADATA'; }
    public function label(): string { return 'حذف پشتیبان‌ها و متادیتای بازیابی'; }
    public function isCritical(): bool { return true; }
    public function estimatedSeconds(): int { return 30; }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            $policy  = $ctx->policy;
            $removed = [];

            if (!$policy->shouldDeleteLocalBackups() && !$policy->shouldDeleteRemoteBackups()) {
                return DeleteStageResult::skipped($this->name(), "سیاست {$policy->value} — پشتیبان‌ها حفظ می‌شوند");
            }
            if (!$ctx->siteId) {
                return DeleteStageResult::skipped($this->name(), 'شناسهٔ سایت معلوم نیست — پشتیبانی برای حذف یافت نشد');
            }

            if ($policy->shouldDeleteLocalBackups()) {
                foreach (LocalRecoveryLog::where('site_id', $ctx->siteId)->get() as $log) {
                    if ($log->local_path && is_dir($log->local_path)) {
                        File::deleteDirectory($log->local_path);
                    } elseif ($log->local_path && file_exists($log->local_path)) {
                        File::delete($log->local_path);
                    }
                    if ($log->local_path && file_exists($log->local_path)) {
                        return DeleteStageResult::failure($this->name(), "حذف فایل پشتیبان محلی ناموفق بود: {$log->local_path}");
                    }
                    $log->delete();
                    $removed['local'][] = "LocalRecoveryLog #{$log->id}";
                }

                // SiteArchiveService zips: storage/app/archives/site_{id}_*.zip
                // (the trailing "_" keeps site 1 from matching site 10).
                foreach (glob(storage_path('app/archives/site_' . (int) $ctx->siteId . '_*.zip')) ?: [] as $archive) {
                    File::delete($archive);
                    $removed['local'][] = basename($archive);
                }
            }

            if ($policy->shouldDeleteRemoteBackups()) {
                foreach (ObjectPrefixGuard::backupPrefixes((int) $ctx->siteId) as $prefix) {
                    try {
                        $result = $this->purger->purge(
                            TenantObjectPurger::TARGET_BACKUPS,
                            ObjectPrefixGuard::assertBackupPrefix($prefix, (int) $ctx->siteId),
                        );
                    } catch (\Throwable $e) {
                        Log::warning("CDE: backup prefix purge failed for site {$ctx->siteId}", ['prefix' => $prefix, 'error' => $e->getMessage()]);

                        return DeleteStageResult::failure($this->name(), "حذف پشتیبان‌های آروان زیر «{$prefix}» ناموفق بود: " . $e->getMessage());
                    }
                    $removed['remote'][] = $result;
                }
            }

            return DeleteStageResult::success($this->name(), ['removed' => $removed]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        if (!$ctx->siteId) {
            return [['check' => 'Recovery metadata removed', 'passed' => true, 'detail' => 'no site_id to verify']];
        }

        $checks = [];
        if ($ctx->policy->shouldDeleteLocalBackups()) {
            $remaining = LocalRecoveryLog::where('site_id', $ctx->siteId)->count();
            $checks[] = ['check' => 'Local recovery logs cleared', 'passed' => $remaining === 0, 'detail' => "{$remaining} records remaining"];
        }
        if ($ctx->policy->shouldDeleteRemoteBackups()) {
            foreach (ObjectPrefixGuard::backupPrefixes((int) $ctx->siteId) as $prefix) {
                try {
                    $left = $this->purger->inventory(TenantObjectPurger::TARGET_BACKUPS, $prefix)['objects'];
                    $checks[] = ['check' => "Arvan backups cleared: {$prefix}", 'passed' => $left === 0, 'detail' => "{$left} objects remaining"];
                } catch (\Throwable $e) {
                    $checks[] = ['check' => "Arvan backups cleared: {$prefix}", 'passed' => false, 'detail' => 'unverifiable: ' . $e->getMessage()];
                }
            }
        }

        return $checks ?: [['check' => 'Backups preserved (policy)', 'passed' => true, 'detail' => $ctx->policy->value]];
    }
}
