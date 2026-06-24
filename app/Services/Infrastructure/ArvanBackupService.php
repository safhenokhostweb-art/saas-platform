<?php
namespace App\Services\Infrastructure;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ArvanBackupService
{
    protected string $disk = 'arvan_backup';
    protected int $maxRetries = 3;
    protected int $retryDelayMs = 1000;

    /**
     * Upload a file to ArvanCloud with retry and verification.
     *
     * @param string $localPath  Absolute path to local file
     * @param string $remotePath Path within bucket (e.g. "saas-platform/database/file.sql.gz")
     * @return array{success: bool, message: string, remote_size: ?int}
     */
    public function upload(string $localPath, string $remotePath): array
    {
        if (!file_exists($localPath)) {
            return ['success' => false, 'message' => 'Local file not found: ' . $localPath, 'remote_size' => null];
        }

        $localSize = filesize($localPath);
        $lastError = '';

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $stream = fopen($localPath, 'r');
                if ($stream === false) {
                    throw new \RuntimeException('Cannot open local file for reading');
                }

                Storage::disk($this->disk)->put($remotePath, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                // Verify existence
                if (!$this->exists($remotePath)) {
                    throw new \RuntimeException('File not found on remote after upload');
                }

                // Verify size
                $remoteSize = $this->size($remotePath);
                if ($remoteSize === null) {
                    throw new \RuntimeException('Cannot verify remote file size');
                }

                if ($remoteSize !== $localSize) {
                    throw new \RuntimeException("Size mismatch: local={$localSize} remote={$remoteSize}");
                }

                Log::info("ArvanBackup: Uploaded {$remotePath} ({$localSize} bytes, attempt {$attempt})");
                return [
                    'success'     => true,
                    'message'     => "Uploaded to {$remotePath} ({$localSize} bytes)",
                    'remote_size' => $remoteSize,
                ];

            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("ArvanBackup: Attempt {$attempt}/{$this->maxRetries} failed for {$remotePath}: {$lastError}");

                if ($attempt < $this->maxRetries) {
                    usleep($this->retryDelayMs * 1000);
                }
            }
        }

        Log::error("ArvanBackup: All {$this->maxRetries} attempts failed for {$remotePath}: {$lastError}");
        return ['success' => false, 'message' => $lastError, 'remote_size' => null];
    }

    /**
     * Check if a remote path exists.
     */
    public function exists(string $remotePath): bool
    {
        try {
            return Storage::disk($this->disk)->exists($remotePath);
        } catch (\Throwable $e) {
            Log::warning("ArvanBackup: exists check failed for {$remotePath}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get remote file size in bytes, or null on failure.
     */
    public function size(string $remotePath): ?int
    {
        try {
            return Storage::disk($this->disk)->size($remotePath);
        } catch (\Throwable $e) {
            Log::warning("ArvanBackup: size check failed for {$remotePath}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Build remote path for platform DB backup.
     */
    public function platformDbPath(string $filename): string
    {
        return 'saas-platform/database/' . $filename;
    }

    /**
     * Build remote path for platform archive backup.
     */
    public function platformArchivePath(string $filename): string
    {
        return 'saas-platform/archives/' . $filename;
    }

    /**
     * Build remote path for platform storage backup.
     */
    public function platformStoragePath(string $filename): string
    {
        return 'saas-platform/storage/' . $filename;
    }

    /**
     * Build remote path for a tenant backup.
     */
    public function tenantPath(int $siteId, string $type, string $filename): string
    {
        return "customer-sites/tenant-{$siteId}/{$type}/{$filename}";
    }
}
