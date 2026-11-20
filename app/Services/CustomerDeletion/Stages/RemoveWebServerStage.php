<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\CustomerDeletion\Guards\PurgeGuardViolation;
use App\Services\CustomerDeletion\Guards\RemotePathGuard;

/**
 * STAGE: REMOVE_WEBSERVER
 *
 * Removes the sites-available / sites-enabled Nginx files of this
 * installation and reloads Nginx.
 *
 * Phase 14-G3 — gated on supportsWebServerConfiguration: cPanel/WHM owns its
 * vhosts, this pipeline never wrote one there.
 *
 * ADR-090 — safe reload. Nginx serves every site on the server (including
 * the platform itself), so:
 *  1. `nginx -t` must pass BEFORE anything changes — an already-broken
 *     config is reported, not made worse;
 *  2. the files are MOVED to /var/backups/saas-purge/{deletion_id}/, not
 *     deleted, then `nginx -t` runs again;
 *  3. only if it passes is Nginx reloaded and the backup discarded;
 *     otherwise the files are moved back and the stage fails (retryable).
 * Both paths must be exactly /etc/nginx/sites-{available,enabled}/{slug}.
 */
class RemoveWebServerStage extends AbstractDeleteStage
{
    private const BACKUP_BASE = '/var/backups/saas-purge';

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

            try {
                $configPath  = RemotePathGuard::assertVhostPath($ctx->nginxConfigPath, $ctx->slug, '/etc/nginx/sites-available');
                $enabledPath = RemotePathGuard::assertVhostPath($ctx->nginxEnabledPath, $ctx->slug, '/etc/nginx/sites-enabled');
            } catch (PurgeGuardViolation $e) {
                return DeleteStageResult::manual($this->name(), $e->getMessage());
            }

            if (!$this->nginxConfigValid($ctx)) {
                return DeleteStageResult::failure(
                    $this->name(),
                    'پیکربندی فعلی Nginx سرور (پیش از هر تغییری) معتبر نیست (nginx -t ناموفق)؛ ابتدا آن را اصلاح و سپس Retry کنید.'
                );
            }

            $backupDir = self::BACKUP_BASE . '/' . (int) $ctx->deletion->id;
            $qBackup   = escapeshellarg($backupDir);
            $this->remoteExec($ctx, "mkdir -p {$qBackup} && chmod 700 {$qBackup}");
            $this->remoteExec($ctx, 'mv -f -- ' . escapeshellarg($configPath) . " {$qBackup}/available 2>/dev/null || true");
            $this->remoteExec($ctx, 'mv -f -- ' . escapeshellarg($enabledPath) . " {$qBackup}/enabled 2>/dev/null || true");

            if (!$this->nginxConfigValid($ctx)) {
                $this->remoteExec($ctx, "mv -f -- {$qBackup}/available " . escapeshellarg($configPath) . ' 2>/dev/null || true');
                $this->remoteExec($ctx, "mv -f -- {$qBackup}/enabled " . escapeshellarg($enabledPath) . ' 2>/dev/null || true');

                return DeleteStageResult::failure(
                    $this->name(),
                    'پس از برداشتن vhost این سایت، nginx -t ناموفق شد؛ فایل‌ها بازگردانده شدند و Nginx ریلود نشد.'
                );
            }

            $reload = $ctx->connection->execute('systemctl reload nginx 2>&1 || nginx -s reload 2>&1');
            if ($reload->exitCode !== 0) {
                return DeleteStageResult::failure($this->name(), 'ریلود Nginx ناموفق بود: ' . trim((string) $reload->stdout));
            }

            $this->remoteExec($ctx, "rm -rf -- {$qBackup}");

            return DeleteStageResult::success($this->name(), [
                'config_path'    => $configPath,
                'enabled_path'   => $enabledPath,
                'nginx_tested'   => true,
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

    private function nginxConfigValid(DeleteContext $ctx): bool
    {
        return $ctx->connection->execute('nginx -t 2>&1')->exitCode === 0;
    }
}
