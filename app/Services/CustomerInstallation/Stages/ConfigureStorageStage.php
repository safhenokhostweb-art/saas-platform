<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class ConfigureStorageStage extends AbstractStage
{
    public function name(): string { return 'CONFIGURE_STORAGE'; }
    public function label(): string { return 'پیکربندی ذخیره‌سازی'; }
    public function estimatedSeconds(): int { return 5; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $webRoot = $ctx->webRoot();
            $safeWebRoot = escapeshellarg($webRoot);

            $commands = [
                "cd {$safeWebRoot} && php artisan storage:link 2>&1",
                "mkdir -p {$safeWebRoot}/storage/app/public {$safeWebRoot}/storage/logs {$safeWebRoot}/storage/framework/cache {$safeWebRoot}/storage/framework/sessions {$safeWebRoot}/storage/framework/views",
                "chmod -R 775 {$safeWebRoot}/storage {$safeWebRoot}/bootstrap/cache 2>/dev/null || true",
                "chown -R www-data:www-data {$safeWebRoot}/storage {$safeWebRoot}/bootstrap/cache 2>/dev/null || true",
            ];

            foreach ($commands as $cmd) {
                $ctx->connection->execute($cmd);
            }

            return StageResult::success($this->name(), [
                'storage_configured' => true,
                'web_root'           => $webRoot,
            ], [], $this->elapsed());
        });
    }
}
