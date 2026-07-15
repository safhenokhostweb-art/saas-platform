<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Models\Site;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeletePolicy;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Installer\LocalRecoveryRepository;
use Illuminate\Support\Facades\Log;

/**
 * STAGE 2: FINAL_BACKUP
 *
 * Takes a final mysqldump of the tenant database before deletion.
 * Policy-controlled: skipped when DELETE_EVERYTHING since there is no point
 * in creating a backup that will immediately be deleted.
 * The backup is created temporarily on the remote server, then persisted in
 * the configured recovery repository outside the customer web root.
 *
 * Phase 14-G3 — the dump no longer runs as `mysqldump -u root` (which no
 * shared-hosting account can satisfy and which this stage, being critical,
 * turned into a hard blocker for every cPanel deletion). It authenticates
 * as the CUSTOMER's own DB user, resolved in order:
 *   1. Site::getDecryptedDbPassword() (vault-encrypted platform credential,
 *      populated for admin-managed/backfilled sites), with the DB user from
 *      the installation metadata;
 *   2. the remote .env's DB_USERNAME/DB_PASSWORD — the exact mechanism
 *      BackupModuleUpgradeStage has used since Phase 13 (this stage runs
 *      before REMOVE_FILES, so the .env is still present).
 * If neither source yields a credential, the stage fails with a clear,
 * secret-free message. The password itself is passed to the remote shell
 * through the connection's env-var mechanism — it never appears in the
 * command string, any log, snapshot, or failure_reason.
 */
class FinalBackupStage extends AbstractDeleteStage
{
    public function __construct(
        private readonly ArvanBackupService $arvan,
        private readonly LocalRecoveryRepository $localRepo,
    ) {}

    public function name(): string { return 'FINAL_BACKUP'; }
    public function label(): string { return 'پشتیبان‌گیری نهایی'; }
    public function isCritical(): bool { return true; }
    public function estimatedSeconds(): int { return 30; }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            // Skip when deleting everything — no point backing up before full wipe
            if ($ctx->policy === DeletePolicy::DELETE_EVERYTHING) {
                return DeleteStageResult::skipped($this->name(), 'سیاست DELETE_EVERYTHING — از پشتیبان‌گیری صرف نظر شد');
            }

            if (!$ctx->dbName || !$ctx->siteId) {
                return DeleteStageResult::failure($this->name(), 'متادیتای دیتابیس یا site_id برای پشتیبان نهایی کامل نیست');
            }

            [$dbUser, $dbPassword, $credentialSource] = $this->resolveDbCredential($ctx);

            if (empty($dbUser) || $dbPassword === null || $dbPassword === '') {
                // Clear, secret-free failure: critical stage, aborts the pipeline.
                return DeleteStageResult::failure(
                    $this->name(),
                    'اعتبارنامهٔ دیتابیس مشتری برای پشتیبان نهایی در دسترس نیست (نه Site credential و نه .env راه دور) — حذف بدون پشتیبان متوقف شد'
                );
            }

            $timestamp  = now()->format('Ymd_His');
            $backupFile = "/tmp/{$ctx->slug}_final_backup_{$timestamp}.sql.gz";

            // Run mysqldump as the customer's own DB user. The password goes
            // through the connection's env mechanism (exported remotely,
            // escaped by the driver) — never into this command string.
            $dumpCmd = 'mysqldump -u ' . escapeshellarg($dbUser)
                . ' ' . escapeshellarg($ctx->dbName)
                . ' 2>/dev/null | gzip > ' . escapeshellarg($backupFile)
                . ' && echo ok';

            $out = trim($ctx->connection->execute($dumpCmd, ['MYSQL_PWD' => $dbPassword])->stdout ?? '');
            if (!str_contains($out, 'ok')) {
                Log::warning("CDE: final backup failed for {$ctx->domain}");
                return DeleteStageResult::failure($this->name(), 'mysqldump پشتیبان نهایی ناموفق بود');
            }

            // Get file size for the audit record
            $sizeOut = $this->remoteExec($ctx, 'stat -c%s ' . escapeshellarg($backupFile) . ' 2>/dev/null');
            $sizeBytes = (int) trim($sizeOut);

            if ($sizeBytes < 100) {
                $ctx->connection->execute('rm -f ' . escapeshellarg($backupFile));
                return DeleteStageResult::failure($this->name(), 'فایل پشتیبان نهایی خالی یا ناقص است');
            }

            $localPath = storage_path("app/install-packages/delete_final_{$ctx->siteId}_{$timestamp}.sql.gz");
            @mkdir(dirname($localPath), 0750, true);

            try {
                $ctx->connection->download($backupFile, $localPath);
                $sha256 = hash_file('sha256', $localPath);

                $keepLocal = match ($ctx->policy) {
                    DeletePolicy::NEVER_DELETE_BACKUP => $this->localRepo->shouldUseLocal(),
                    DeletePolicy::DELETE_REMOTE_BACKUPS => true,
                    default => false,
                };
                $keepRemote = match ($ctx->policy) {
                    DeletePolicy::NEVER_DELETE_BACKUP => $this->localRepo->shouldUseArvan(),
                    DeletePolicy::DELETE_LOCAL_BACKUPS => true,
                    default => false,
                };

                if (!$keepLocal && !$keepRemote) {
                    return DeleteStageResult::failure($this->name(), 'هیچ مقصد recovery فعالی برای پشتیبان نهایی وجود ندارد');
                }
                $localResult = null;
                $remoteResult = null;

                if ($keepLocal) {
                    $localResult = $this->localRepo->storeCustomerBaseline(
                        siteId: $ctx->siteId,
                        installationUuid: (string) $ctx->installation->id,
                        sqlLocalPath: $localPath,
                        sha256: $sha256,
                    );
                }

                if ($keepRemote) {
                    $remotePath = "customers/site-{$ctx->siteId}/baselines/FINAL_DELETE_BACKUP_{$timestamp}.sql.gz";
                    $remoteResult = $this->arvan->upload($localPath, $remotePath);
                }

                $localOk = !$keepLocal || (($localResult['success'] ?? false) && !($localResult['skipped'] ?? false));
                $remoteOk = !$keepRemote || ($remoteResult['success'] ?? false);

                if (!$localOk || !$remoteOk) {
                    return DeleteStageResult::failure(
                        $this->name(),
                        'پشتیبان نهایی در مقصدهای الزامی ذخیره یا تأیید نشد'
                    );
                }

                return DeleteStageResult::success($this->name(), [
                    'size_bytes' => $sizeBytes,
                    'sha256' => $sha256,
                    'local_path' => $localResult['local_path'] ?? null,
                    'remote_path' => $remotePath ?? null,
                    'db_name' => $ctx->dbName,
                    'credential_source' => $credentialSource,
                    'created_at' => $timestamp,
                ]);
            } finally {
                @unlink($localPath);
                $ctx->connection->execute('rm -f ' . escapeshellarg($backupFile));
            }
        });
    }

    /**
     * Phase 14-G3 — resolve the customer's own DB credential for mysqldump.
     * Returns [dbUser, dbPassword, source]; password may be null when no
     * source has it. The plaintext only ever lives in this method's return
     * value and the env array handed to execute() — it is never logged,
     * never stored on the result/snapshot, never interpolated into messages.
     *
     * 1. Site credential: sites.db_password_encrypted via CredentialVault
     *    (getDecryptedDbPassword() returns null — never throws — when unset
     *    or undecryptable), paired with the db_user recorded at install.
     * 2. Remote .env: DB_USERNAME/DB_PASSWORD grep — the same extraction
     *    BackupModuleUpgradeStage uses. Valid because FINAL_BACKUP runs
     *    before REMOVE_FILES in the pipeline order.
     */
    private function resolveDbCredential(DeleteContext $ctx): array
    {
        $sitePassword = $ctx->siteId
            ? Site::find($ctx->siteId)?->getDecryptedDbPassword()
            : null;

        if (!empty($ctx->dbUser) && $sitePassword !== null && $sitePassword !== '') {
            return [$ctx->dbUser, $sitePassword, 'site_credential'];
        }

        $envPath = escapeshellarg($ctx->webRoot . '/.env');

        $envUser = trim($ctx->connection->execute(
            "grep '^DB_USERNAME=' {$envPath} 2>/dev/null | cut -d= -f2 | tr -d '\"'"
        )->stdout ?? '');
        $envPassword = trim($ctx->connection->execute(
            "grep '^DB_PASSWORD=' {$envPath} 2>/dev/null | cut -d= -f2 | tr -d '\"'"
        )->stdout ?? '');

        $dbUser = $ctx->dbUser ?: $envUser;

        if ($dbUser !== '' && $envPassword !== '') {
            return [$dbUser, $envPassword, 'remote_env'];
        }

        return [$dbUser, null, 'unavailable'];
    }

    public function verify(DeleteContext $ctx): array
    {
        $output = $ctx->getOutput($this->name());
        if (isset($output['reason'])) {
            // Skipped — not a failure
            return [['check' => 'Final backup', 'passed' => true, 'detail' => 'skipped: ' . $output['reason']]];
        }

        return [
            ['check' => 'Backup persisted outside customer web root', 'passed' => !empty($output['local_path']) || !empty($output['remote_path']), 'detail' => $output['local_path'] ?? $output['remote_path'] ?? 'n/a'],
            ['check' => 'Backup size > 0', 'passed' => ($output['size_bytes'] ?? 0) > 0, 'detail' => ($output['size_bytes'] ?? 0) . ' bytes'],
            ['check' => 'Backup SHA-256 recorded', 'passed' => !empty($output['sha256']), 'detail' => $output['sha256'] ?? 'n/a'],
        ];
    }
}
