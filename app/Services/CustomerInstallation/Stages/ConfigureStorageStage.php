<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class ConfigureStorageStage extends AbstractStage
{
    public function name(): string { return 'CONFIGURE_STORAGE'; }
    public function label(): string { return 'پیکربندی ذخیره‌سازی'; }
    public function estimatedSeconds(): int { return 5; }

    /**
     * Phase 14-F5 — this stage previously had no capability gate at all (it
     * ran unconditionally on every driver). Adding requiredCapabilities()
     * now that supportsStorageLink correctly reports true for both VPS and
     * cPanel means the gate never blocks either today — this is metadata
     * alignment, not a behavior change. Optional (not required to proceed):
     * a missing storage symlink is not fatal to installation completion, an
     * admin can wire it manually later — same philosophy as
     * ConfigureQueueStage/ConfigureSchedulerStage. The stage's own internal
     * failure path (both symlink attempts genuinely failing on a connected,
     * capable driver) is untouched — that remains a real StageResult::failure,
     * unrelated to this gate.
     */
    public function requiredCapabilities(): array
    {
        return ['supportsStorageLink'];
    }

    public function isCapabilityRequiredToProceed(): bool
    {
        return false;
    }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $webRoot     = $ctx->webRoot();
            $safeWebRoot = escapeshellarg($webRoot);

            // Ensure all required storage directories exist before linking
            $ctx->connection->execute(
                "mkdir -p {$safeWebRoot}/storage/app/public {$safeWebRoot}/storage/logs "
                . "{$safeWebRoot}/storage/framework/cache {$safeWebRoot}/storage/framework/sessions "
                . "{$safeWebRoot}/storage/framework/views {$safeWebRoot}/bootstrap/cache"
            );

            // Create the public/storage symlink via artisan
            $linkResult = $ctx->connection->execute("cd {$safeWebRoot} && php artisan storage:link 2>&1");

            // Verify the symlink actually exists — artisan may succeed but symlink could be missing
            $verifyResult = $ctx->connection->execute(
                "test -L {$safeWebRoot}/public/storage && echo linked || echo missing"
            );
            if (str_contains($verifyResult->stdout, 'missing')) {
                // Artisan failed or storage dir doesn't exist — create symlink manually
                $ctx->connection->execute(
                    "ln -sf {$safeWebRoot}/storage/app/public {$safeWebRoot}/public/storage 2>&1"
                );
                $reCheck = $ctx->connection->execute(
                    "test -L {$safeWebRoot}/public/storage && echo linked || echo missing"
                );
                if (str_contains($reCheck->stdout, 'missing')) {
                    return StageResult::failure(
                        $this->name(),
                        'Storage symlink creation failed. artisan output: ' . trim($linkResult->stdout),
                        $this->elapsed()
                    );
                }
            }

            $ctx->connection->execute("chmod -R 775 {$safeWebRoot}/storage {$safeWebRoot}/bootstrap/cache 2>/dev/null || true");
            $ctx->connection->execute("chown -R www-data:www-data {$safeWebRoot}/storage {$safeWebRoot}/bootstrap/cache 2>/dev/null || true");

            return StageResult::success($this->name(), [
                'storage_configured' => true,
                'web_root'           => $webRoot,
                'symlink_output'     => trim($linkResult->stdout),
            ], [], $this->elapsed());
        });
    }
}
