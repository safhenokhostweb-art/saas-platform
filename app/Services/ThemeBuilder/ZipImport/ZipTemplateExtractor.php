<?php

namespace App\Services\ThemeBuilder\ZipImport;

use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Phase 1: Safe ZIP extraction and content discovery.
 *
 * Security guards:
 *  - Path traversal (..) blocked
 *  - Executable extensions blocked (php, phtml, phar, exe, sh, bat)
 *  - Max 500 files per archive
 *  - Max 100MB total extracted size
 *
 * Discovery output:
 *  - json_files      → paths to all .json files (primary template candidates)
 *  - screenshot_files → images (jpg/png/webp/gif/svg)
 *  - asset_files     → css/js/fonts
 *  - metadata_files  → txt/md/xml (README, style.css, package.json)
 *  - all_files       → relative paths of everything extracted
 */
class ZipTemplateExtractor
{
    private const MAX_EXTRACT_BYTES = 100 * 1024 * 1024; // 100 MB
    private const MAX_FILES         = 500;
    private const BLOCKED_EXTS      = ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'ps1'];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Extract an uploaded ZIP file and return a discovery map.
     *
     * @param  UploadedFile $file
     * @return array{
     *   temp_dir:         string,
     *   original_name:    string,
     *   json_files:       string[],
     *   screenshot_files: string[],
     *   asset_files:      string[],
     *   metadata_files:   string[],
     *   all_files:        string[],
     * }
     * @throws \RuntimeException on invalid archive or security violation
     */
    public function extract(UploadedFile $file): array
    {
        $zip    = new ZipArchive();
        $opened = $zip->open($file->getRealPath());

        if ($opened !== true) {
            throw new \RuntimeException("باز کردن فایل ZIP ناموفق بود (کد خطا: {$opened})");
        }

        $this->validateArchive($zip);

        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zip-import-' . uniqid('', true);
        mkdir($tempDir, 0755, true);

        $this->safeExtract($zip, $tempDir);
        $zip->close();

        return $this->discover($tempDir, $file->getClientOriginalName());
    }

    /**
     * Remove extracted temp directory after processing.
     */
    public function cleanup(string $tempDir): void
    {
        if (is_dir($tempDir)) {
            $this->rmRecursive($tempDir);
        }
    }

    // ── Validation ────────────────────────────────────────────────────────────

    private function validateArchive(ZipArchive $zip): void
    {
        $fileCount = $zip->numFiles;

        if ($fileCount > self::MAX_FILES) {
            $zip->close();
            throw new \RuntimeException("آرشیو ZIP بیش از " . self::MAX_FILES . " فایل دارد ({$fileCount} فایل).");
        }

        $totalSize = 0;
        for ($i = 0; $i < $fileCount; $i++) {
            $stat = $zip->statIndex($i);
            $totalSize += $stat['size'] ?? 0;
        }

        $maxMB = self::MAX_EXTRACT_BYTES / 1024 / 1024;
        if ($totalSize > self::MAX_EXTRACT_BYTES) {
            $zip->close();
            throw new \RuntimeException("حجم استخراج‌شده بیش از {$maxMB}MB است.");
        }
    }

    // ── Extraction ────────────────────────────────────────────────────────────

    private function safeExtract(ZipArchive $zip, string $targetDir): void
    {
        $count = $zip->numFiles;

        for ($i = 0; $i < $count; $i++) {
            $name = $zip->getNameIndex($i);

            // Block path traversal
            if (str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
                continue;
            }

            // Block executable extensions
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, self::BLOCKED_EXTS, true)) {
                continue;
            }

            $targetPath = realpath($targetDir) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);

            // Ensure final path stays inside targetDir (second traversal guard)
            if (!str_starts_with($targetPath, realpath($targetDir))) {
                continue;
            }

            // Directory entry
            if (substr($name, -1) === '/') {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
                continue;
            }

            // File entry
            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $contents = $zip->getFromIndex($i);
            if ($contents !== false) {
                file_put_contents($targetPath, $contents);
            }
        }
    }

    // ── Discovery ─────────────────────────────────────────────────────────────

    private function discover(string $dir, string $originalName): array
    {
        $result = [
            'temp_dir'         => $dir,
            'original_name'    => $originalName,
            'json_files'       => [],
            'screenshot_files' => [],
            'asset_files'      => [],
            'metadata_files'   => [],
            'all_files'        => [],
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $absPath = $file->getPathname();
            $ext     = strtolower($file->getExtension());
            $rel     = ltrim(str_replace($dir, '', $absPath), DIRECTORY_SEPARATOR . '/');

            $result['all_files'][] = $rel;

            if ($ext === 'json') {
                $result['json_files'][] = $absPath;
                continue;
            }

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $result['screenshot_files'][] = $absPath;
                continue;
            }

            if (in_array($ext, ['svg'], true)) {
                // SVG could be icon or screenshot — put in screenshots (smaller)
                $result['screenshot_files'][] = $absPath;
                continue;
            }

            if (in_array($ext, ['css', 'js', 'woff', 'woff2', 'ttf', 'otf', 'eot'], true)) {
                $result['asset_files'][] = $absPath;
                continue;
            }

            if (in_array($ext, ['txt', 'md', 'xml'], true) || in_array(strtolower(basename($absPath)), ['style.css', 'package.json', 'composer.json'], true)) {
                $result['metadata_files'][] = $absPath;
                continue;
            }

            // Also capture package.json / composer.json by name (they have .json ext, already captured above)
        }

        // Sort JSON by file size descending (largest = most content)
        usort($result['json_files'], fn($a, $b) => filesize($b) - filesize($a));

        // Sort screenshots: prefer larger files (actual screenshots vs icons)
        usort($result['screenshot_files'], fn($a, $b) => filesize($b) - filesize($a));

        return $result;
    }

    // ── Cleanup ───────────────────────────────────────────────────────────────

    private function rmRecursive(string $dir): void
    {
        $items = array_diff((array) scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->rmRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
