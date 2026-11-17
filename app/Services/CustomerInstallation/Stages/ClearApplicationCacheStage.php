<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

/**
 * ADR-088 — clears the new site's Laravel config, application and compiled
 * view caches once its .env is final (license key written, entitlements
 * applied) and before CUSTOMER_READY opens it to traffic, so the first
 * request never renders against configuration or views cached mid-install.
 *
 * artisan runs as the deployment user, so ownership of storage/ and
 * bootstrap/cache is re-asserted for www-data afterwards; a root-owned
 * cache file there is what returned 500s after the 2026-11-01 deploys.
 *
 * Non-fatal: a fresh install has nothing stale to serve, so a failed clear
 * is recorded as a warning in the stage output rather than rolling back a
 * finished installation.
 */
class ClearApplicationCacheStage extends AbstractStage
{
    public const COMMANDS = ['config:clear', 'cache:clear', 'view:clear'];

    public function name(): string { return 'CLEAR_APPLICATION_CACHE'; }
    public function label(): string { return 'پاک‌سازی کش برنامه و نماها'; }
    public function estimatedSeconds(): int { return 5; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $webRoot = escapeshellarg($ctx->webRoot());
            $results = [];

            foreach (self::COMMANDS as $command) {
                $result = $ctx->connection->execute("cd {$webRoot} && php artisan {$command} 2>&1");
                $results[$command] = $result->failed() ? 'failed: ' . mb_substr(trim($result->output()), 0, 300) : 'ok';
            }

            $ctx->connection->execute(
                "chown -R www-data:www-data {$webRoot}/storage {$webRoot}/bootstrap/cache 2>/dev/null || true; "
                . "chmod -R 775 {$webRoot}/storage {$webRoot}/bootstrap/cache 2>/dev/null || true"
            );

            $failed = array_filter($results, fn (string $r) => $r !== 'ok');

            return StageResult::success($this->name(), [
                'status'   => $failed === [] ? 'cleared' : 'warning',
                'commands' => $results,
            ], [], $this->elapsed());
        });
    }
}
