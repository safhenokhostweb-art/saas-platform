<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\CustomerDeletion\Guards\PurgeGuardViolation;
use App\Services\CustomerDeletion\Guards\RemotePathGuard;
use App\Services\CustomerInstallation\Enums\DriverType;

/**
 * STAGE: REMOVE_FILES
 *
 * Removes the customer web root directory tree. Runs AFTER nginx/supervisor/
 * cron configs are removed so no process references the files.
 *
 * ADR-090 — `rm -rf` runs only on a path that:
 *  1. the frozen purge plan marked `delete` (shared hosting needs this:
 *     without a plan the path is only a guess from server config);
 *  2. passes RemotePathGuard — VPS: exactly /var/www/{this installation's
 *     slug}; shared hosting: a site directory inside /home/{user}, never the
 *     account root or public_html;
 *  3. resolves remotely (`readlink -f`) to itself, so a symlink cannot
 *     redirect the delete.
 * Anything else becomes a "manual action required" result — never a delete.
 */
class RemoveFilesStage extends AbstractDeleteStage
{
    public function name(): string { return 'REMOVE_FILES'; }
    public function label(): string { return 'حذف فایل‌های برنامه'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 20; }

    public function buildPreSnapshot(DeleteContext $ctx): array
    {
        return ['web_root' => $ctx->webRoot];
    }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            $slug = $ctx->slug;

            try {
                $webRoot = self::guardedWebRoot($ctx);
            } catch (PurgeGuardViolation $e) {
                return DeleteStageResult::manual(
                    $this->name(),
                    'پوشهٔ سایت به‌طور خودکار حذف نشد — ' . $e->getMessage() . ' پس از بررسی، پوشه را دستی حذف کنید.',
                    ['web_root' => $ctx->webRoot],
                );
            }

            // Temporary install/backup artefacts carry the slug, which the
            // guard above has already validated against SLUG_PATTERN.
            $this->remoteExec($ctx, 'rm -f ' . escapeshellarg("/tmp/{$slug}") . '_*.zip '
                . escapeshellarg("/tmp/{$slug}") . '_*.tar.gz '
                . escapeshellarg("/tmp/{$slug}") . '_final_backup_*.sql.gz '
                . escapeshellarg("/tmp/{$slug}.cie_installing") . ' 2>/dev/null || true');

            $resolved = $this->remoteExec($ctx, 'readlink -f -- ' . escapeshellarg($webRoot) . ' 2>/dev/null');
            if ($resolved === '') {
                return DeleteStageResult::success($this->name(), [
                    'web_root' => $webRoot,
                    'removed'  => false,
                    'reason'   => 'already absent',
                ]);
            }
            if ($resolved !== $webRoot) {
                return DeleteStageResult::manual(
                    $this->name(),
                    "پوشهٔ سایت به مسیر دیگری ({$resolved}) اشاره می‌کند (symlink)؛ حذف خودکار انجام نشد.",
                    ['web_root' => $webRoot],
                );
            }

            $sizeBytes = (int) $this->remoteExec($ctx, 'du -sb ' . escapeshellarg($webRoot) . ' 2>/dev/null | cut -f1');

            $this->remoteExec($ctx, 'rm -rf --one-file-system -- ' . escapeshellarg($webRoot) . ' 2>/dev/null || true');

            if ($this->remoteExists($ctx, $webRoot)) {
                return DeleteStageResult::failure($this->name(), "حذف پوشهٔ سایت ناموفق بود: {$webRoot}");
            }

            return DeleteStageResult::success($this->name(), [
                'web_root'   => $webRoot,
                'size_bytes' => $sizeBytes,
                'removed'    => true,
            ]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        $gone = !$this->remoteExists($ctx, $ctx->webRoot);
        return [
            ['check' => 'Web root directory removed', 'passed' => $gone, 'detail' => $ctx->webRoot],
        ];
    }

    /**
     * The web root, if it may be deleted automatically.
     *
     * @throws PurgeGuardViolation with the reason a human must do it instead
     */
    public static function guardedWebRoot(DeleteContext $ctx): string
    {
        RemotePathGuard::assertSafeShape($ctx->webRoot);

        if ($ctx->webRootAction !== 'delete') {
            throw new PurgeGuardViolation($ctx->webRootManualReason ?: 'پلن حذف این مسیر را برای حذف خودکار تأیید نکرده است.');
        }

        $type = $ctx->connection->driverType();
        if (in_array($type, [DriverType::CPANEL, DriverType::DIRECTADMIN], true)) {
            if ($ctx->plan === []) {
                throw new PurgeGuardViolation('روی هاست اشتراکی، حذف خودکار پوشه فقط با پلن حذف ثبت‌شده مجاز است.');
            }
            $username = (string) ($ctx->installation->remoteServer?->username ?? '');

            return RemotePathGuard::assertSharedHostingSiteRoot($ctx->webRoot, $username);
        }

        return RemotePathGuard::assertVpsSiteRoot($ctx->webRoot, $ctx->slug);
    }
}
