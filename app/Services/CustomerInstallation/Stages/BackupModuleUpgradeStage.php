<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Installer\LocalRecoveryRepository;
use Illuminate\Support\Facades\Log;

/**
 * Phase 13-I — pre-upgrade database backup.
 *
 * Reuses InitializeBackupStage's dump/checksum/dual-storage mechanism
 * exactly (mysqldump → download → checksum → store to Local Recovery
 * Repository and/or Arvan, fail only if both fail), minus the one-time
 * .env bootstrap (BACKUP_ENABLED/RETENTION) and the artisan db:backup
 * trigger — both install-time-only concerns that don't apply to "take
 * one dump right now before this specific upgrade."
 *
 * Phase 13-M2 — canRollback()/rollback() connect this backup to a real
 * restore: on rollback, the dump referenced by the checkpoint's snapshot
 * (Local Recovery Repository file and/or Arvan key, whichever succeeded
 * in execute()) is fetched, its SHA-256 is re-verified against the value
 * recorded at backup time, uploaded back to the remote server, and
 * restored with `mysql`. Any failure in that chain — fetch, checksum
 * mismatch, remote restore, or post-restore table-count sanity check —
 * throws, so CustomerInstallationRollbackService marks the installation
 * rollback_failed instead of silently reporting success while the
 * database is still in its post-migration state.
 */
class BackupModuleUpgradeStage extends AbstractStage
{
    public function __construct(
        private readonly ArvanBackupService $arvan,
        private readonly LocalRecoveryRepository $localRepo,
    ) {}

    public function name(): string { return 'BACKUP_MODULE_UPGRADE'; }
    public function label(): string { return 'پشتیبان‌گیری قبل از بروزرسانی ماژول'; }
    public function estimatedSeconds(): int { return 60; }
    public function canRollback(): bool { return true; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $webRoot = $ctx->webRoot();
            $envPath = escapeshellarg($webRoot . '/.env');
            $siteId = $ctx->installation->site_id ?? $ctx->installation->id;
            $timestamp = now()->format('Ymd_His');
            $dumpFile = "/tmp/cie_module_upgrade_{$siteId}_{$timestamp}.sql.gz";

            $dbName = trim($ctx->connection->execute("grep '^DB_DATABASE=' {$envPath} 2>/dev/null | cut -d= -f2 | tr -d '\"'")->stdout);
            $dbUser = trim($ctx->connection->execute("grep '^DB_USERNAME=' {$envPath} 2>/dev/null | cut -d= -f2 | tr -d '\"'")->stdout);
            $dbPassRaw = trim($ctx->connection->execute("grep '^DB_PASSWORD=' {$envPath} 2>/dev/null | cut -d= -f2 | tr -d '\"'")->stdout);

            if (empty($dbName) || empty($dbUser)) {
                return StageResult::failure($this->name(), 'Could not read DB credentials from remote .env — cannot take pre-upgrade backup', $this->elapsed());
            }

            $dumpResult = $ctx->connection->execute(
                'MYSQL_PWD=' . escapeshellarg($dbPassRaw) . ' mysqldump -u ' . escapeshellarg($dbUser)
                . ' ' . escapeshellarg($dbName)
                . ' 2>/dev/null | gzip > ' . escapeshellarg($dumpFile)
                . " && echo 'dump-ok' || echo 'dump-failed'"
            );

            if (!str_contains($dumpResult->stdout, 'dump-ok')) {
                return StageResult::failure($this->name(), 'mysqldump failed on remote server', $this->elapsed());
            }

            $sizeResult = $ctx->connection->execute('stat -c%s ' . escapeshellarg($dumpFile) . ' 2>/dev/null || echo 0');
            $dumpSize = (int) trim($sizeResult->stdout);

            if ($dumpSize <= 100) {
                $ctx->connection->execute('rm -f ' . escapeshellarg($dumpFile));
                return StageResult::failure($this->name(), 'Database dump is empty or suspiciously small', $this->elapsed());
            }

            $localPath = storage_path("app/install-packages/module_upgrade_baseline_{$siteId}_{$timestamp}.sql.gz");

            try {
                $ctx->connection->download($dumpFile, $localPath);
            } catch (\Throwable $e) {
                $ctx->connection->execute('rm -f ' . escapeshellarg($dumpFile));
                return StageResult::failure($this->name(), 'Failed to download backup dump: ' . $e->getMessage(), $this->elapsed());
            }

            $checksum = hash_file('sha256', $localPath);

            $localRepoResult = null;
            if ($this->localRepo->shouldUseLocal()) {
                try {
                    $localRepoResult = $this->localRepo->storeCustomerBaseline(
                        siteId: (int) $siteId,
                        installationUuid: $ctx->installation->uuid ?? (string) $ctx->installation->id,
                        sqlLocalPath: $localPath,
                        sha256: $checksum,
                    );
                } catch (\Throwable $e) {
                    Log::warning("CIE BackupModuleUpgrade: Local repo copy failed — {$e->getMessage()}");
                }
            }

            $arvanKey = "customers/site-{$siteId}/module-upgrades/backup_{$timestamp}.sql.gz";
            $uploaded = $this->localRepo->shouldUseArvan()
                ? $this->arvan->upload($localPath, $arvanKey)
                : ['success' => false, 'message' => 'Arvan disabled by policy'];

            $arvanOk = $uploaded['success'] ?? false;
            $localRepoOk = $localRepoResult['success'] ?? false;
            $localRepoFile = $localRepoOk && !empty($localRepoResult['local_path'])
                ? $localRepoResult['local_path'] . DIRECTORY_SEPARATOR . 'customer_baseline.sql.gz'
                : null;

            @unlink($localPath);
            $ctx->connection->execute('rm -f ' . escapeshellarg($dumpFile));

            if (!$arvanOk && !$localRepoOk) {
                return StageResult::failure($this->name(), 'Backup could not be stored (both Arvan and local repo failed)', $this->elapsed());
            }

            return StageResult::success($this->name(), [
                'backup_sha256' => $checksum,
                'backup_arvan_key' => $arvanOk ? $arvanKey : null,
                'stored_locally' => $localRepoOk,
            ], [
                'backup_sha256' => $checksum,
                'backup_arvan_key' => $arvanOk ? $arvanKey : null,
                'local_repo_file' => $localRepoFile,
            ], $this->elapsed());
        });
    }

    /**
     * Restores the database dump taken by execute() onto the remote
     * server. Throws on any failure so the caller (CustomerInstallation-
     * RollbackService) marks the installation rollback_failed rather than
     * reporting a clean rollback while the database is untouched.
     */
    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        $sha256 = $snapshot['backup_sha256'] ?? null;
        $arvanKey = $snapshot['backup_arvan_key'] ?? null;
        $localFile = $snapshot['local_repo_file'] ?? null;

        if (!$sha256 || (!$arvanKey && !$localFile)) {
            throw new \RuntimeException('BACKUP_MODULE_UPGRADE rollback: no usable backup reference in snapshot — cannot restore database.');
        }

        $siteId = $ctx->installation->site_id ?? $ctx->installation->id;
        $localPath = storage_path('app/install-packages/module_upgrade_restore_' . $siteId . '_' . now()->format('YmdHis') . '.sql.gz');

        try {
            if (!$this->fetchBackupDump($localFile, $arvanKey, $localPath)) {
                throw new \RuntimeException('BACKUP_MODULE_UPGRADE rollback: could not fetch backup dump from local repo or Arvan.');
            }

            $actualSha256 = hash_file('sha256', $localPath);
            if ($actualSha256 !== $sha256) {
                throw new \RuntimeException("BACKUP_MODULE_UPGRADE rollback: checksum mismatch (expected {$sha256}, got {$actualSha256}) — refusing to restore a possibly corrupted backup.");
            }

            $webRoot = $ctx->webRoot();
            $envPath = escapeshellarg($webRoot . '/.env');
            $dbName = trim($ctx->connection->execute("grep '^DB_DATABASE=' {$envPath} 2>/dev/null | cut -d= -f2 | tr -d '\"'")->stdout);
            $dbUser = trim($ctx->connection->execute("grep '^DB_USERNAME=' {$envPath} 2>/dev/null | cut -d= -f2 | tr -d '\"'")->stdout);
            $dbPassRaw = trim($ctx->connection->execute("grep '^DB_PASSWORD=' {$envPath} 2>/dev/null | cut -d= -f2 | tr -d '\"'")->stdout);

            if (empty($dbName) || empty($dbUser)) {
                throw new \RuntimeException('BACKUP_MODULE_UPGRADE rollback: could not read DB credentials from remote .env — cannot restore database.');
            }

            $remoteDumpPath = '/tmp/cie_module_upgrade_restore_' . $siteId . '_' . now()->format('YmdHis') . '.sql.gz';
            $ctx->connection->upload($localPath, $remoteDumpPath);

            $restoreResult = $ctx->connection->execute(
                'gunzip -c ' . escapeshellarg($remoteDumpPath) . ' | MYSQL_PWD=' . escapeshellarg($dbPassRaw)
                . ' mysql -u ' . escapeshellarg($dbUser) . ' ' . escapeshellarg($dbName)
                . " && echo 'restore-ok' || echo 'restore-failed'"
            );

            $ctx->connection->execute('rm -f ' . escapeshellarg($remoteDumpPath));

            if (!str_contains($restoreResult->stdout, 'restore-ok')) {
                throw new \RuntimeException('BACKUP_MODULE_UPGRADE rollback: mysql restore failed on remote server: ' . $restoreResult->output());
            }

            // Post-restore sanity check — a restore that "succeeds" (exit 0)
            // but silently imports nothing (e.g. an empty/truncated dump slipping
            // past the checksum check) must still fail loudly, not report success.
            $verifyResult = $ctx->connection->execute(
                'MYSQL_PWD=' . escapeshellarg($dbPassRaw) . ' mysql -N -u ' . escapeshellarg($dbUser)
                . ' ' . escapeshellarg($dbName)
                . ' -e ' . escapeshellarg('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')
                . ' 2>/dev/null'
            );
            $tableCount = (int) trim($verifyResult->stdout);

            if ($tableCount < 1) {
                throw new \RuntimeException('BACKUP_MODULE_UPGRADE rollback: post-restore verification found no tables — restore may have silently failed.');
            }

            Log::info('CIE BACKUP_MODULE_UPGRADE: database restored from pre-upgrade backup', [
                'installation_id' => $ctx->installation->id,
                'table_count' => $tableCount,
            ]);
        } finally {
            @unlink($localPath);
        }
    }

    private function fetchBackupDump(?string $localFile, ?string $arvanKey, string $destPath): bool
    {
        if ($localFile && file_exists($localFile) && copy($localFile, $destPath)) {
            return true;
        }

        if ($arvanKey) {
            return $this->arvan->download($arvanKey, $destPath);
        }

        return false;
    }
}
