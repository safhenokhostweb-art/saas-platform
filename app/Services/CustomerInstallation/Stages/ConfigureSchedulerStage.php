<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class ConfigureSchedulerStage extends AbstractStage
{
    public function name(): string { return 'CONFIGURE_SCHEDULER'; }
    public function label(): string { return 'پیکربندی زمانبند'; }
    public function estimatedSeconds(): int { return 3; }
    public function canRollback(): bool { return true; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $slug = $ctx->customerSlug();
            $webRoot = $ctx->webRoot();
            $cronPath = "/etc/cron.d/{$slug}";

            $cronEntry = "* * * * * www-data cd {$webRoot} && php artisan schedule:run >> /dev/null 2>&1\n";
            $encoded = base64_encode($cronEntry);

            $writeResult = $ctx->connection->execute(
                "echo " . escapeshellarg($encoded) . " | base64 -d > " . escapeshellarg($cronPath)
                . " 2>&1 && chmod 644 " . escapeshellarg($cronPath) . " && echo 'ok'"
            );

            if (!str_contains($writeResult->stdout, 'ok')) {
                return StageResult::success($this->name(), [
                    'status'  => 'skipped',
                    'message' => "Cannot write {$cronPath} — manual cron setup required",
                ], [], $this->elapsed());
            }

            return StageResult::success($this->name(), [
                'cron_path'  => $cronPath,
                'cron_entry' => $cronEntry,
            ], ['cron_path' => $cronPath], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['cron_path'])) {
            $ctx->connection->execute('rm -f ' . escapeshellarg($snapshot['cron_path']));
        }
    }
}
