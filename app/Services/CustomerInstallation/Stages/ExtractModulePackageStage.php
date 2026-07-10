<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use Illuminate\Support\Facades\Log;

/**
 * Phase 13-I — extracts an uploaded module package into its own
 * directory within an already-live site, never the whole web root.
 *
 * Reuses ExtractPackageStage's pattern (idempotent structure, checked
 * steps, pre-snapshot, rollback) but differs where it must: instead of
 * wiping/recreating the web root, the existing module directory (if any)
 * is moved aside before extraction, and restored on rollback — the site
 * stays live and undisturbed outside the module's own path throughout.
 */
class ExtractModulePackageStage extends AbstractStage
{
    public function name(): string { return 'EXTRACT_MODULE_PACKAGE'; }
    public function label(): string { return 'استخراج بستهٔ ماژول'; }
    public function estimatedSeconds(): int { return 20; }
    public function canRollback(): bool { return true; }

    public function buildPreSnapshot(InstallationContext $ctx): array
    {
        if (!$ctx->moduleTarget) {
            return [];
        }
        return ['module_path' => $ctx->moduleInstallPath()];
    }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            if (!$ctx->moduleTarget) {
                return StageResult::failure($this->name(), 'No moduleTarget on context', $this->elapsed());
            }

            $uploadOutput = $ctx->getOutput('UPLOAD_PACKAGE');
            $remotePath = $uploadOutput['remote_path'] ?? null;
            if (!$remotePath) {
                return StageResult::failure($this->name(), 'No remote package path found from UPLOAD_PACKAGE stage', $this->elapsed());
            }

            $modulePath = $ctx->moduleInstallPath();
            $safeModulePath = escapeshellarg($modulePath);
            $backupPath = $modulePath . '.bak_' . now()->format('YmdHis');
            $safeBackupPath = escapeshellarg($backupPath);

            $existsResult = $ctx->connection->execute("test -d {$safeModulePath} && echo exists || echo absent");
            $hadExisting = str_contains($existsResult->stdout, 'exists');

            if ($hadExisting) {
                $moveResult = $ctx->connection->execute("mv {$safeModulePath} {$safeBackupPath}");
                if ($moveResult->failed()) {
                    return StageResult::failure($this->name(), 'Failed to move aside existing module directory: ' . $moveResult->output(), $this->elapsed());
                }
            }

            $steps = [
                "mkdir -p {$safeModulePath}",
                'unzip -o -q ' . escapeshellarg($remotePath) . " -d {$safeModulePath} 2>&1",
                'rm -f ' . escapeshellarg($remotePath),
            ];

            foreach ($steps as $step) {
                $result = $ctx->connection->execute($step);
                if ($result->failed()) {
                    if ($hadExisting) {
                        $ctx->connection->execute("rm -rf {$safeModulePath}; mv {$safeBackupPath} {$safeModulePath}");
                    }
                    return StageResult::failure($this->name(), "Extraction step failed: {$step}\n" . $result->output(), $this->elapsed());
                }
            }

            $countResult = $ctx->connection->execute("find {$safeModulePath} -type f 2>/dev/null | wc -l");
            $fileCount = (int) trim($countResult->stdout);

            if ($fileCount < 1) {
                if ($hadExisting) {
                    $ctx->connection->execute("rm -rf {$safeModulePath}; mv {$safeBackupPath} {$safeModulePath}");
                }
                return StageResult::failure($this->name(), "Extraction produced no files — package may be empty", $this->elapsed());
            }

            Log::info('CIE EXTRACT_MODULE_PACKAGE: Extracted files', [
                'installation_id' => $ctx->installation->id,
                'module_slug' => $ctx->moduleTarget->moduleSlug,
                'file_count' => $fileCount,
                'previous_backed_up_to' => $hadExisting ? $backupPath : null,
            ]);

            return StageResult::success($this->name(), [
                'module_path' => $modulePath,
                'file_count' => $fileCount,
                'previous_backed_up_to' => $hadExisting ? $backupPath : null,
            ], [
                'module_path' => $modulePath,
                'backup_path' => $hadExisting ? $backupPath : null,
            ], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        $modulePath = $snapshot['module_path'] ?? null;
        if (!$modulePath) {
            return;
        }

        $backupPath = $snapshot['backup_path'] ?? null;
        $safeModulePath = escapeshellarg($modulePath);

        if ($backupPath) {
            // Restore the previous version of the module.
            $ctx->connection->execute("rm -rf {$safeModulePath}");
            $ctx->connection->execute('mv ' . escapeshellarg($backupPath) . " {$safeModulePath}");
        } else {
            // There was no previous version — safe to remove entirely.
            $ctx->connection->execute("rm -rf {$safeModulePath}");
        }
    }
}
