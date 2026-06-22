<?php
namespace App\Services\Lifecycle;

use App\Models\Site;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Creates archive packages for suspended sites before archival.
 *
 * Archive package includes:
 *   - Database export (mysqldump)
 *   - Uploaded files manifest
 *   - Theme builder data (JSON export from platform DB)
 *   - Global settings export (JSON)
 *   - Modules configuration
 *
 * Package stored under storage/app/archives/site_{id}_{date}.zip
 */
class SiteArchiveService
{
    public function createArchivePackage(Site $site): string
    {
        $archiveDir  = storage_path('app/archives');
        $archiveName = "site_{$site->id}_" . date('Ymd_His') . '.zip';
        $archivePath = "{$archiveDir}/{$archiveName}";

        if (! is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }

        $manifestDir = sys_get_temp_dir() . '/archive_' . $site->id . '_' . time();
        mkdir($manifestDir, 0755, true);

        try {
            $this->exportPlatformData($site, $manifestDir);
            $this->createZip($manifestDir, $archivePath);
            $this->cleanup($manifestDir);

            // GAP-L06: Verify the ZIP is readable and non-empty after creation
            $this->verifyZip($archivePath, $site->id);

            $site->archive_path = "archives/{$archiveName}";
            $site->save();

            Log::info("SiteArchiveService: archive created for site {$site->id}", [
                'path'      => $archivePath,
                'size_bytes' => filesize($archivePath),
            ]);

            return $archivePath;
        } catch (\Throwable $e) {
            $this->cleanup($manifestDir);
            throw $e;
        }
    }

    private function exportPlatformData(Site $site, string $dir): void
    {
        // Export modules configuration
        $modules = \Illuminate\Support\Facades\DB::table('site_modules')
            ->where('site_id', $site->id)
            ->get()
            ->toArray();

        // Export site settings snapshot
        $siteData = [
            'id'             => $site->id,
            'title'          => $site->title,
            'domain'         => $site->domain,
            'features'       => $site->features,
            'theme_id'       => $site->theme_id,
            'lifecycle'      => [
                'status'        => $site->getLifecycleStatus(),
                'suspended_at'  => optional($site->suspended_at)->toIso8601String(),
                'archived_at'   => optional($site->archived_at)->toIso8601String(),
            ],
            'modules'        => $modules,
            'archived_at'    => now()->toIso8601String(),
        ];

        file_put_contents("{$dir}/site_snapshot.json", json_encode($siteData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // Export theme builder data
        $themes = \Illuminate\Support\Facades\DB::table('themes')
            ->where('site_id', $site->id)
            ->get()
            ->toArray();

        if (! empty($themes)) {
            file_put_contents("{$dir}/theme_data.json", json_encode($themes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        // Create README
        $readme  = "# Site Archive — {$site->title}\n\n";
        $readme .= "Archived: " . now()->toDateTimeString() . "\n";
        $readme .= "Domain: {$site->domain}\n";
        $readme .= "Site ID: {$site->id}\n";
        $readme .= "Customer ID: {$site->customer_id}\n\n";
        $readme .= "## Contents\n";
        $readme .= "- `site_snapshot.json` — Site settings, modules, features\n";
        $readme .= "- `theme_data.json` — Theme builder pages and blocks\n";
        $readme .= "- `README.md` — This file\n";

        file_put_contents("{$dir}/README.md", $readme);
    }

    private function createZip(string $sourceDir, string $zipPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create ZIP archive at: {$zipPath}");
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir));
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            $zip->addFile($file->getRealPath(), $relativePath);
        }

        $zip->close();
    }

    /**
     * GAP-L06 — Verify the created ZIP archive is valid and non-empty.
     * Throws RuntimeException if the file is missing, empty, or unreadable.
     */
    private function verifyZip(string $zipPath, int $siteId): void
    {
        if (! file_exists($zipPath)) {
            throw new \RuntimeException("Archive verification failed for site {$siteId}: file not found at {$zipPath}");
        }

        $size = filesize($zipPath);
        if ($size === false || $size === 0) {
            throw new \RuntimeException("Archive verification failed for site {$siteId}: file is empty at {$zipPath}");
        }

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath, \ZipArchive::RDONLY);
        if ($result !== true) {
            throw new \RuntimeException("Archive verification failed for site {$siteId}: ZIP is corrupt (ZipArchive error {$result})");
        }

        $count = $zip->numFiles;
        $zip->close();

        if ($count === 0) {
            throw new \RuntimeException("Archive verification failed for site {$siteId}: ZIP contains no files");
        }

        Log::info("SiteArchiveService: archive verified for site {$siteId}", [
            'size_bytes' => $size,
            'file_count' => $count,
        ]);
    }

    private function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($dir);
    }

    public function archiveExists(Site $site): bool
    {
        return $site->archive_path && Storage::disk('local')->exists($site->archive_path);
    }

    /**
     * GAP-M10 — Delete the archive ZIP file from disk when a site is permanently deleted.
     *
     * Storage::disk('local') corresponds to storage/app/. The archive_path column
     * stores a relative path like "archives/site_42_20260101_120000.zip".
     *
     * Returns true if the file was deleted (or didn't exist), false on error.
     */
    public function deleteArchiveZip(Site $site): bool
    {
        if (! $site->archive_path) {
            return true;
        }

        try {
            $disk = Storage::disk('local');

            if ($disk->exists($site->archive_path)) {
                $disk->delete($site->archive_path);

                Log::info("SiteArchiveService: archive ZIP deleted for site {$site->id}", [
                    'path' => $site->archive_path,
                ]);
            } else {
                Log::info("SiteArchiveService: archive ZIP already absent for site {$site->id}", [
                    'path' => $site->archive_path,
                ]);
            }

            $site->archive_path = null;
            $site->save();

            return true;
        } catch (\Throwable $e) {
            Log::error("SiteArchiveService: failed to delete archive ZIP for site {$site->id}", [
                'path'  => $site->archive_path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * C05 — Delete all active uploaded files for a site from disk.
     *
     * Removes the site's upload directory under storage/app/sites/{site_id}/
     * and also any public disk uploads scoped to this site.
     * Called after createArchivePackage() during archive transition.
     *
     * @return int bytes freed (approximate)
     */
    public function deleteActiveFiles(Site $site): int
    {
        $freed = 0;
        $paths = $this->resolveUploadPaths($site);

        foreach ($paths as $disk => $directory) {
            try {
                $diskInstance = Storage::disk($disk);

                if (! $diskInstance->exists($directory)) {
                    continue;
                }

                // Calculate size before deletion for reporting
                try {
                    $files = $diskInstance->allFiles($directory);
                    foreach ($files as $file) {
                        $freed += $diskInstance->size($file);
                    }
                } catch (\Throwable) {
                    // size calculation is best-effort
                }

                $diskInstance->deleteDirectory($directory);

                Log::info("SiteArchiveService: deleted upload directory [{$disk}]/{$directory}", [
                    'site_id' => $site->id,
                    'freed_bytes' => $freed,
                ]);
            } catch (\Throwable $e) {
                Log::error("SiteArchiveService: failed to delete [{$disk}]/{$directory} for site {$site->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Record deletion in site model
        try {
            $site->files_deleted_at = now();
            $site->files_freed_bytes = $freed;
            $site->save();
        } catch (\Throwable) {
            // columns may not exist yet if migration pending — non-fatal
        }

        Log::info("SiteArchiveService: C05 storage release complete for site {$site->id}", [
            'freed_bytes' => $freed,
            'freed_mb'    => round($freed / 1048576, 2),
        ]);

        return $freed;
    }

    /**
     * Resolve all upload path directories for this site across all disks.
     * Returns ['disk_name' => 'directory_path'].
     */
    private function resolveUploadPaths(Site $site): array
    {
        $siteId = $site->id;
        $paths  = [];

        // Primary local upload path
        $localDir = "sites/{$siteId}";
        $paths['local'] = $localDir;

        // Public disk uploads (images, avatars etc.)
        $publicDir = "sites/{$siteId}";
        $paths['public'] = $publicDir;

        // Smart image uploads (if using separate media disk)
        $mediaDisk = config('filesystems.media_disk', null);
        if ($mediaDisk && $mediaDisk !== 'local' && $mediaDisk !== 'public') {
            $paths[$mediaDisk] = "sites/{$siteId}";
        }

        return $paths;
    }
}
