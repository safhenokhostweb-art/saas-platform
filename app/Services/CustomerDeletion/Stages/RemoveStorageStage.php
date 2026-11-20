<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

/**
 * STAGE 7: REMOVE_STORAGE
 *
 * Removes storage symlinks and clears the public/storage symlink if it
 * exists outside the web root (e.g. symlink pointing to /var/www/{slug}/storage/app/public).
 * The actual storage directory is part of the web root and removed in
 * REMOVE_FILES, but we explicitly unlink any symlinks first to avoid
 * dangling references.
 */
class RemoveStorageStage extends AbstractDeleteStage
{
    public function name(): string { return 'REMOVE_STORAGE'; }
    public function label(): string { return 'حذف فضای ذخیره‌سازی'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 5; }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            // ADR-090 — nothing inside a web root the platform cannot prove
            // is this installation's own is touched (REMOVE_FILES reports it
            // as a manual action).
            try {
                RemoveFilesStage::guardedWebRoot($ctx);
            } catch (\App\Services\CustomerDeletion\Guards\PurgeGuardViolation $e) {
                return DeleteStageResult::skipped($this->name(), 'پوشهٔ سایت برای حذف خودکار تأیید نشده — ' . $e->getMessage());
            }

            $webRoot      = $ctx->webRoot;
            $storageLink  = $webRoot . '/public/storage';
            $storagePath  = $webRoot . '/storage';

            $removed = [];

            // Remove public/storage symlink if it exists
            if ($this->remoteExists($ctx, $storageLink)) {
                $this->remoteExec($ctx, 'rm -f ' . escapeshellarg($storageLink));
                $removed[] = $storageLink;
            }

            // Remove installed.lock sentinel (if exists)
            $lockPath = $storagePath . '/installed.lock';
            $this->remoteExec($ctx, 'rm -f ' . escapeshellarg($lockPath));

            // Remove bootstrap/cache
            $cachePath = $webRoot . '/bootstrap/cache';
            $this->remoteExec($ctx, 'rm -rf ' . escapeshellarg($cachePath) . ' 2>/dev/null || true');

            return DeleteStageResult::success($this->name(), [
                'web_root'       => $webRoot,
                'removed_links'  => $removed,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $symlinkGone = !$this->remoteExists($ctx, $ctx->webRoot . '/public/storage');
        $lockGone    = !$this->remoteExists($ctx, $ctx->webRoot . '/storage/installed.lock');
        return [
            ['check' => 'public/storage symlink removed', 'passed' => $symlinkGone, 'detail' => $ctx->webRoot . '/public/storage'],
            ['check' => 'installed.lock removed',         'passed' => $lockGone,    'detail' => $ctx->webRoot . '/storage/installed.lock'],
        ];
    }
}
