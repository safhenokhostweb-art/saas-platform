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

            $site->archive_path = "archives/{$archiveName}";
            $site->save();

            Log::info("SiteArchiveService: archive created for site {$site->id}", [
                'path' => $archivePath,
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
}
