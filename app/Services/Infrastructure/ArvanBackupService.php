<?php
namespace App\Services\Infrastructure;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use League\Flysystem\FileAttributes;

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
     * Build remote path for a tenant database backup.
     */
    public function tenantDbPath(int $siteId, string $filename): string
    {
        return "customer-sites/tenant-{$siteId}/database/{$filename}";
    }

    /**
     * Build remote path for a tenant backup.
     */
    public function tenantPath(int $siteId, string $type, string $filename): string
    {
        return "customer-sites/tenant-{$siteId}/{$type}/{$filename}";
    }

    /**
     * List all files under a remote prefix, sorted newest-first.
     * Uses listContents() to fetch metadata in a single API call (no N+1).
     *
     * @return list<array{path: string, filename: string, size: int|null, last_modified: int|null}>
     */
    public function listFiles(string $prefix): array
    {
        try {
            $listing = Storage::disk($this->disk)->listContents($prefix, false);
            $result  = [];

            foreach ($listing as $item) {
                if (!($item instanceof FileAttributes)) {
                    continue;
                }

                $size     = $item->fileSize();
                $modified = $item->lastModified();

                // Fallback to individual API calls only if metadata is missing from listing
                if ($size === null) {
                    $size = $this->size($item->path());
                }
                if ($modified === null) {
                    $modified = $this->lastModified($item->path());
                }

                $result[] = [
                    'path'          => $item->path(),
                    'filename'      => basename($item->path()),
                    'size'          => $size,
                    'last_modified' => $modified,
                ];
            }

            usort($result, fn($a, $b) => ($b['last_modified'] ?? 0) <=> ($a['last_modified'] ?? 0));
            return $result;

        } catch (\Throwable $e) {
            Log::warning("ArvanBackup: listFiles failed for prefix '{$prefix}': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Prune old files under a prefix, keeping the newest $keepCount files.
     * Files are sorted newest-first; oldest files beyond $keepCount are deleted.
     *
     * @return array{pruned: int, kept: int}
     */
    public function prune(string $prefix, int $keepCount = 7): array
    {
        $files = $this->listFiles($prefix);

        if (count($files) <= $keepCount) {
            return ['pruned' => 0, 'kept' => count($files)];
        }

        $toDelete = array_slice($files, $keepCount);
        $pruned   = 0;

        foreach ($toDelete as $file) {
            try {
                Storage::disk($this->disk)->delete($file['path']);
                $pruned++;
                Log::info("ArvanBackup: pruned {$file['path']}");
            } catch (\Throwable $e) {
                Log::warning("ArvanBackup: prune delete failed for {$file['path']}: " . $e->getMessage());
            }
        }

        return ['pruned' => $pruned, 'kept' => count($files) - $pruned];
    }

    /**
     * Download a remote file to a local path. Returns true on success.
     */
    public function download(string $remotePath, string $localPath): bool
    {
        try {
            $stream = Storage::disk($this->disk)->readStream($remotePath);
            if (!$stream) {
                Log::warning("ArvanBackup: readStream returned null for {$remotePath}");
                return false;
            }

            $dest = fopen($localPath, 'w');
            if ($dest === false) {
                Log::warning("ArvanBackup: cannot open local path for writing: {$localPath}");
                if (is_resource($stream)) fclose($stream);
                return false;
            }

            stream_copy_to_stream($stream, $dest);
            fclose($dest);
            if (is_resource($stream)) fclose($stream);

            $ok = file_exists($localPath) && filesize($localPath) > 0;
            if ($ok) {
                Log::info("ArvanBackup: Downloaded {$remotePath} → {$localPath} (" . filesize($localPath) . " bytes)");
            }
            return $ok;
        } catch (\Throwable $e) {
            Log::warning("ArvanBackup: download failed for {$remotePath}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get last-modified timestamp for a file, or null on failure.
     */
    public function lastModified(string $remotePath): ?int
    {
        try {
            return Storage::disk($this->disk)->lastModified($remotePath);
        } catch (\Throwable $e) {
            Log::warning("ArvanBackup: lastModified check failed for {$remotePath}: " . $e->getMessage());
            return null;
        }
    }
}
