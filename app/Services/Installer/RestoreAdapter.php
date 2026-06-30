<?php
namespace App\Services\Installer;

use App\Services\Infrastructure\RestoreService;
use App\Services\Infrastructure\ArvanBackupService;

/**
 * Adapter for MODE 2 (Restore Platform).
 * Wraps RestoreService + ArvanBackupService for the installer context.
 * Sources: local disk, Arvan object storage, release package ZIP, recovery_repository.
 */
class RestoreAdapter
{
    public function __construct(
        private RestoreService          $restore,
        private ArvanBackupService      $arvan,
        private LocalRecoveryRepository $localRepo,
    ) {}

    // ─── Source Discovery ────────────────────────────────────────────────────

    public function listAllSources(): array
    {
        return [
            'local'               => $this->listLocalBackups(),
            'arvan'               => $this->listArvanBackups(),
            'release'             => $this->listReleasePackages(),
            'recovery_repository' => $this->listRecoveryRepositoryBackups(),
        ];
    }

    private function listLocalBackups(): array
    {
        $backupDir = storage_path('backups');
        if (!is_dir($backupDir)) {
            return [];
        }

        $files  = array_merge(
            glob($backupDir . '/*.sql.gz') ?: [],
            glob($backupDir . '/*.sql')    ?: []
        );

        return array_map(fn($f) => [
            'path'     => $f,
            'filename' => basename($f),
            'size'     => filesize($f),
            'modified' => filemtime($f),
            'source'   => 'local',
        ], $this->sortByModified($files));
    }

    private function listArvanBackups(): array
    {
        try {
            $files = $this->restore->listBackups('platform_database');
            return array_map(fn($f) => array_merge($f, ['source' => 'arvan']), $files);
        } catch (\Throwable) {
            return [];
        }
    }

    private function listReleasePackages(): array
    {
        $releaseDir = storage_path('releases');
        if (!is_dir($releaseDir)) {
            return [];
        }

        $files = glob($releaseDir . '/*.zip') ?: [];
        return array_map(fn($f) => [
            'path'     => $f,
            'filename' => basename($f),
            'size'     => filesize($f),
            'modified' => filemtime($f),
            'source'   => 'release',
        ], $this->sortByModified($files));
    }

    // ─── Recovery Repository Source ───────────────────────────────────────────

    private function listRecoveryRepositoryBackups(): array
    {
        try {
            return $this->localRepo->listRestorablePlatformBackups();
        } catch (\Throwable) {
            return [];
        }
    }

    // ─── Verification ────────────────────────────────────────────────────────

    public function verifyBackup(string $source, string $path): array
    {
        return match($source) {
            'local', 'release', 'recovery_repository', 'local_pc' => $this->verifyLocalFile($path),
            'arvan'                                                => $this->verifyArvanFile($path),
            default                                                => ['valid' => false, 'message' => "منبع نامعتبر: {$source}"],
        };
    }

    private function verifyLocalFile(string $path): array
    {
        if (!file_exists($path)) {
            return ['valid' => false, 'message' => 'فایل پشتیبان یافت نشد'];
        }
        $size = filesize($path);
        if ($size < 100) {
            return ['valid' => false, 'message' => 'فایل پشتیبان خراب یا خالی است'];
        }
        return [
            'valid'    => true,
            'size'     => $size,
            'checksum' => md5_file($path),
            'message'  => 'فایل معتبر — اندازه: ' . $this->formatBytes($size),
        ];
    }

    private function verifyArvanFile(string $path): array
    {
        try {
            $exists = $this->arvan->exists($path);
            if (!$exists) {
                return ['valid' => false, 'message' => 'فایل در Arvan یافت نشد'];
            }
            $size = $this->arvan->size($path);
            return [
                'valid'   => true,
                'size'    => $size,
                'message' => 'فایل در Arvan موجود است' . ($size ? ' — اندازه: ' . $this->formatBytes($size) : ''),
            ];
        } catch (\Throwable $e) {
            return ['valid' => false, 'message' => 'خطای Arvan: ' . $e->getMessage()];
        }
    }

    // ─── Rollback Point ──────────────────────────────────────────────────────

    public function createRollbackPoint(string $label = 'pre_restore'): array
    {
        $rollbackDir = storage_path("installer_rollbacks/{$label}_" . now()->format('Ymd_His'));
        @mkdir($rollbackDir, 0750, true);

        // Snapshot .env
        $envSrc = base_path('.env');
        if (file_exists($envSrc)) {
            copy($envSrc, "{$rollbackDir}/.env.backup");
        }

        return [
            'success'      => true,
            'rollback_path'=> $rollbackDir,
            'message'      => "نقطه بازگشت در {$rollbackDir} ذخیره شد",
        ];
    }

    // ─── Delegate to RestoreService ──────────────────────────────────────────

    public function getRestoreService(): RestoreService
    {
        return $this->restore;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function sortByModified(array $files): array
    {
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        return $files;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
