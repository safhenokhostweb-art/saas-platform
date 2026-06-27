<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class ValidateDiskStage extends AbstractStage
{
    public function name(): string { return 'VALIDATE_DISK'; }
    public function label(): string { return 'اعتبارسنجی فضای دیسک'; }
    public function estimatedSeconds(): int { return 2; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $minimums = config('customer_installation.disk_minimums', []);
            $webMin = $minimums['web_root_bytes'] ?? 2 * 1024 * 1024 * 1024;
            $tmpMin = $minimums['tmp_bytes'] ?? 512 * 1024 * 1024;

            $webFree = $ctx->connection->diskFree('/var/www');
            if ($webFree < $webMin) {
                return StageResult::failure(
                    $this->name(),
                    sprintf('Insufficient disk on /var/www: %s free, %s required',
                        $this->humanBytes($webFree), $this->humanBytes($webMin)
                    ),
                    $this->elapsed()
                );
            }

            $tmpFree = $ctx->connection->diskFree('/tmp');
            if ($tmpFree < $tmpMin) {
                return StageResult::failure(
                    $this->name(),
                    sprintf('Insufficient disk on /tmp: %s free, %s required',
                        $this->humanBytes($tmpFree), $this->humanBytes($tmpMin)
                    ),
                    $this->elapsed()
                );
            }

            return StageResult::success($this->name(), [
                'web_root_free_bytes' => $webFree,
                'tmp_free_bytes'      => $tmpFree,
            ], [], $this->elapsed());
        });
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1024 ** 3) return round($bytes / 1024 ** 3, 1) . 'GB';
        if ($bytes >= 1024 ** 2) return round($bytes / 1024 ** 2, 1) . 'MB';
        return round($bytes / 1024, 1) . 'KB';
    }
}
