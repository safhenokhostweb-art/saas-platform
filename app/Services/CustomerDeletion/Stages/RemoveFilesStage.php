<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

/**
 * STAGE 8: REMOVE_FILES
 *
 * Removes the entire customer web root directory tree.
 * This is executed AFTER nginx/supervisor/cron configs are removed so
 * the application is fully offline before its files disappear.
 *
 * Also cleans up any temporary installation artifacts:
 * - /tmp/{slug}_*.zip
 * - /tmp/{slug}_*.tar.gz
 */
class RemoveFilesStage extends AbstractDeleteStage
{
    public function name(): string { return 'REMOVE_FILES'; }
    public function label(): string { return 'حذف فایل‌های برنامه'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 20; }

    public function buildPreSnapshot(DeleteContext $ctx): array
    {
        return ['web_root' => $ctx->webRoot];
    }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            $webRoot = $ctx->webRoot;
            $slug    = $ctx->slug;

            // Get disk usage before deletion for the audit record
            $sizeOut = $this->remoteExec($ctx,
                'du -sb ' . escapeshellarg($webRoot) . ' 2>/dev/null | cut -f1'
            );
            $sizeBytes = (int) trim($sizeOut);

            // Remove web root
            $this->remoteExec($ctx, 'rm -rf ' . escapeshellarg($webRoot) . ' 2>/dev/null || true');

            // Remove tmp installation artifacts
            $this->remoteExec($ctx, "rm -f /tmp/{$slug}_*.zip /tmp/{$slug}_*.tar.gz /tmp/{$slug}.cie_installing 2>/dev/null || true");

            return DeleteStageResult::success($this->name(), [
                'web_root'     => $webRoot,
                'size_bytes'   => $sizeBytes,
                'removed'      => true,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $gone = !$this->remoteExists($ctx, $ctx->webRoot);
        return [
            ['check' => 'Web root directory removed', 'passed' => $gone, 'detail' => $ctx->webRoot],
        ];
    }
}
