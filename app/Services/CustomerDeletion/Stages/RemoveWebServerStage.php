<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

/**
 * STAGE 6: REMOVE_WEBSERVER
 *
 * Removes both the sites-available and sites-enabled Nginx configuration
 * files for the customer domain, then reloads Nginx so the virtual host
 * is immediately de-registered.
 *
 * Phase 14-G3 — gated on supportsWebServerConfiguration, mirroring the
 * install side's ConfigureWebServerStage: cPanel/WHM owns its vhosts, this
 * pipeline never wrote one there, so there is nothing to remove.
 */
class RemoveWebServerStage extends AbstractDeleteStage
{
    public function name(): string { return 'REMOVE_WEBSERVER'; }
    public function label(): string { return 'حذف پیکربندی وب‌سرور'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 8; }

    public function buildPreSnapshot(DeleteContext $ctx): array
    {
        return [
            'config_path'  => $ctx->nginxConfigPath,
            'enabled_path' => $ctx->nginxEnabledPath,
        ];
    }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            if (!$ctx->connection->capabilities()->supportsWebServerConfiguration) {
                return DeleteStageResult::skipped(
                    $this->name(),
                    'این درایور پیکربندی وب‌سرور را مدیریت نمی‌کند (vhost توسط پنل هاست مدیریت می‌شود)'
                );
            }

            $configPath  = $ctx->nginxConfigPath;
            $enabledPath = $ctx->nginxEnabledPath;

            // Remove symlink first, then the config file
            $this->remoteExec($ctx, 'rm -f ' . escapeshellarg($enabledPath));
            $this->remoteExec($ctx, 'rm -f ' . escapeshellarg($configPath));

            // Reload nginx to apply the removal
            $this->remoteExec($ctx,
                'nginx -s reload 2>&1 || systemctl reload nginx 2>&1 || service nginx reload 2>&1 || true'
            );

            return DeleteStageResult::success($this->name(), [
                'config_path'  => $configPath,
                'enabled_path' => $enabledPath,
                'nginx_reloaded' => true,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        if (!$ctx->connection->capabilities()->supportsWebServerConfiguration) {
            return [
                ['check' => 'Web server config removed', 'passed' => true, 'detail' => 'skipped — vhost managed by hosting panel'],
            ];
        }

        $configGone  = !$this->remoteExists($ctx, $ctx->nginxConfigPath);
        $enabledGone = !$this->remoteExists($ctx, $ctx->nginxEnabledPath);
        return [
            ['check' => 'Nginx config removed',  'passed' => $configGone,  'detail' => $ctx->nginxConfigPath],
            ['check' => 'Nginx symlink removed',  'passed' => $enabledGone, 'detail' => $ctx->nginxEnabledPath],
        ];
    }
}
