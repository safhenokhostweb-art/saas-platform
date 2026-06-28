<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use Illuminate\Support\Facades\Log;

class ExtractPackageStage extends AbstractStage
{
    public function name(): string { return 'EXTRACT_PACKAGE'; }
    public function label(): string { return 'استخراج بسته'; }
    public function estimatedSeconds(): int { return 60; }
    public function canRollback(): bool { return true; }

    /**
     * Pre-snapshot: the web root path is deterministic from the context,
     * so rollback can remove a partially-extracted directory even if
     * this stage never reached its success return.
     */
    public function buildPreSnapshot(InstallationContext $ctx): array
    {
        return ['remote_path' => $ctx->webRoot()];
    }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $uploadOutput = $ctx->getOutput('UPLOAD_PACKAGE');
            $remotePath   = $uploadOutput['remote_path'] ?? null;

            if (!$remotePath) {
                return StageResult::failure($this->name(), 'No remote package path found from UPLOAD_PACKAGE stage', $this->elapsed());
            }

            $webRoot     = $ctx->webRoot();
            $safeWebRoot = escapeshellarg($webRoot);
            $installId   = $ctx->installation->id;

            // Idempotency: if the web root already contains extracted files from a
            // previous (resumed) pipeline run, skip re-extraction. The sentinel file
            // .cie_installing is removed by CustomerReadyStage only on full success.
            $existsResult = $ctx->connection->execute("test -d {$safeWebRoot} && echo exists || echo absent");
            if (str_contains($existsResult->stdout, 'exists')) {
                $countResult = $ctx->connection->execute("find {$safeWebRoot} -type f 2>/dev/null | wc -l");
                $fileCount   = (int) trim($countResult->stdout);
                if ($fileCount > 100) {
                    Log::info("CIE EXTRACT_PACKAGE: Web root already has {$fileCount} files — resuming past extraction", ['installation_id' => $installId]);
                    return StageResult::success($this->name(), [
                        'web_root'   => $webRoot,
                        'file_count' => $fileCount,
                        'resumed'    => true,
                    ], ['remote_path' => $webRoot], $this->elapsed());
                }
                // Fewer than 100 files → treat as partial/corrupt extraction — wipe and re-extract.
                $ctx->connection->execute("rm -rf {$safeWebRoot}");
            }

            // Create the web root and place a sentinel so that any web server
            // wildcard config cannot serve partially-installed files as a live tenant.
            $steps = [
                "mkdir -p {$safeWebRoot}",
                "touch {$safeWebRoot}/.cie_installing",
                "unzip -q " . escapeshellarg($remotePath) . " -d {$safeWebRoot} 2>&1",
                "rm -f " . escapeshellarg($remotePath),
                "chown -R www-data:www-data {$safeWebRoot} 2>/dev/null || true",
                "find {$safeWebRoot} -type f ! -name '.cie_installing' -exec chmod 644 {} \\; 2>/dev/null",
                "find {$safeWebRoot} -type d -exec chmod 755 {} \\; 2>/dev/null",
            ];

            foreach ($steps as $step) {
                $result = $ctx->connection->execute($step);

                // chown and find are non-fatal; unzip and mkdir ARE fatal.
                $isFatal = !str_contains($step, 'chown') && !str_contains($step, 'find');
                if ($result->failed() && $isFatal) {
                    return StageResult::failure(
                        $this->name(),
                        "Extraction step failed: {$step}\n" . $result->output(),
                        $this->elapsed()
                    );
                }
            }

            $countResult = $ctx->connection->execute("find {$safeWebRoot} -type f 2>/dev/null | wc -l");
            $fileCount   = (int) trim($countResult->stdout);

            if ($fileCount < 10) {
                return StageResult::failure(
                    $this->name(),
                    "Extraction produced only {$fileCount} files — ZIP may be empty or extraction failed silently",
                    $this->elapsed()
                );
            }

            Log::info("CIE EXTRACT_PACKAGE: Extracted {$fileCount} files to {$webRoot}", ['installation_id' => $installId]);

            return StageResult::success($this->name(), [
                'web_root'   => $webRoot,
                'file_count' => $fileCount,
                'resumed'    => false,
            ], ['remote_path' => $webRoot], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['remote_path'])) {
            $ctx->connection->execute('rm -rf ' . escapeshellarg($snapshot['remote_path']));
        }
    }
}
