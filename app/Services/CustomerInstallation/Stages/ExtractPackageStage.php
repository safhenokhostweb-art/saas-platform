<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class ExtractPackageStage extends AbstractStage
{
    public function name(): string { return 'EXTRACT_PACKAGE'; }
    public function label(): string { return 'استخراج بسته'; }
    public function estimatedSeconds(): int { return 15; }
    public function canRollback(): bool { return true; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $uploadOutput = $ctx->getOutput('UPLOAD_PACKAGE');
            $remotePath = $uploadOutput['remote_path'] ?? null;

            if (!$remotePath) {
                return StageResult::failure($this->name(), 'No remote package path found', $this->elapsed());
            }

            $webRoot = $ctx->webRoot();
            $safeWebRoot = escapeshellarg($webRoot);

            $steps = [
                "mkdir -p {$safeWebRoot}",
                "unzip -q {$remotePath} -d {$safeWebRoot} 2>&1",
                "rm -f {$remotePath}",
                "chown -R www-data:www-data {$safeWebRoot} 2>/dev/null || true",
                "find {$safeWebRoot} -type f -exec chmod 644 {} \\; 2>/dev/null",
                "find {$safeWebRoot} -type d -exec chmod 755 {} \\; 2>/dev/null",
            ];

            foreach ($steps as $step) {
                $result = $ctx->connection->execute($step);
                if ($result->failed() && !str_contains($step, 'chown') && !str_contains($step, 'find')) {
                    return StageResult::failure($this->name(), "Extraction step failed: {$step}\n" . $result->output(), $this->elapsed());
                }
            }

            $countResult = $ctx->connection->execute("find {$safeWebRoot} -type f 2>/dev/null | wc -l");
            $fileCount = (int) trim($countResult->stdout);

            return StageResult::success($this->name(), [
                'web_root'    => $webRoot,
                'file_count'  => $fileCount,
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
