<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerInstallationPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Automates synchronization of customer-site installation packages in saas-platform
 * whenever a new release of customer-site is deployed.
 *
 * Guarantees:
 * 1. Atomicity: Builds to a temporary file, verifies integrity, and renames into place.
 * 2. Fail-safe: Any failure leaves the active package and previous packages untouched.
 * 3. Idempotency: Duplicate calls for the same commit skip repackaging without error.
 * 4. Safe permissions: Files created with 0664, directories with 0775 (www-data group).
 */
class CustomerPackageSyncService
{
    /** Critical marker files that must exist in source directory to be valid customer-site */
    public const REQUIRED_SOURCE_MARKERS = [
        'artisan',
        'composer.json',
        'bootstrap/app.php',
        'app/Services/ThemeCanvas/ThemeInstanceRevisionService.php',
    ];

    /** Patterns to exclude when packaging the release zip */
    public const EXCLUSION_PATTERNS = [
        '.git',
        '.github',
        '.githooks',
        '.continue',
        '.clinerules',
        '.env',
        '.env.backup',
        'bootstrap/cache/*.php',
        'public/storage',
        'storage/logs',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/app/public/uploads',
        'public/uploads',
        'installed.lock',
    ];

    /**
     * Synchronize and package the deployed customer-site into saas-platform installation packages.
     *
     * @param string $sourcePath Path to the deployed customer-site (e.g. /var/www/customer-site)
     * @param string|null $commit Commit hash of the release
     * @param string|null $explicitVersion Optional explicit semver (e.g. 1.1.1)
     * @param string|null $notes Optional release notes
     * @param bool $force Force repackaging even if package for this commit is already active
     * @return CustomerInstallationPackage The activated package model
     */
    public function syncFromDeployedSite(
        string $sourcePath,
        ?string $commit = null,
        ?string $explicitVersion = null,
        ?string $notes = null,
        bool $force = false
    ): CustomerInstallationPackage {
        $sourcePath = rtrim(realpath($sourcePath) ?: $sourcePath, '/\\');

        $this->validateSourceDirectory($sourcePath);

        $resolvedCommit = $this->resolveCommit($sourcePath, $commit);
        $resolvedVersion = $this->resolveVersion($explicitVersion);

        // Idempotency check: if already active for this commit and file exists, return directly
        $currentActive = CustomerInstallationPackage::active();
        if (!$force && $currentActive !== null) {
            $shortCommit = substr($resolvedCommit, 0, 8);
            $notesMatch = !empty($resolvedCommit) && (
                str_contains((string) $currentActive->release_notes, $resolvedCommit) ||
                (!empty($shortCommit) && str_contains((string) $currentActive->release_notes, $shortCommit))
            );
            if ($notesMatch && !empty($currentActive->local_path) && file_exists($currentActive->local_path)) {
                Log::info("CustomerPackageSync: Package v{$currentActive->version} for commit {$resolvedCommit} is already active. Skipping.", [
                    'package_id' => $currentActive->id,
                    'path'       => $currentActive->local_path,
                ]);
                return $currentActive;
            }
        }

        $destDir = config('customer_installation.package.local_temp_path', storage_path('app/install-packages'));
        if (!is_dir($destDir)) {
            if (!@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
                throw new RuntimeException("Failed to create package destination directory: {$destDir}");
            }
        }

        $targetZipName = "customer-site-v{$resolvedVersion}.zip";
        $finalPath = $destDir . DIRECTORY_SEPARATOR . $targetZipName;
        $tempZipPath = $destDir . DIRECTORY_SEPARATOR . ".tmp-pkg-{$resolvedVersion}-" . uniqid() . '.zip';

        try {
            // Step 1: Build the zip archive to temporary file
            $this->createArchive($sourcePath, $tempZipPath);

            // Step 2: Thorough integrity check of the temporary zip
            $this->verifyArchiveIntegrity($tempZipPath);

            // Step 3: Atomic rename into the permanent filename
            if (file_exists($finalPath)) {
                // Keep backup copy of prior file under same name if overwriting
                @copy($finalPath, $finalPath . '.bak-' . date('YmdHis'));
            }

            if (!@rename($tempZipPath, $finalPath)) {
                // Fallback to copy + unlink if cross-filesystem rename fails
                if (!@copy($tempZipPath, $finalPath)) {
                    throw new RuntimeException("Failed to move temporary package from {$tempZipPath} to {$finalPath}");
                }
                @unlink($tempZipPath);
            }

            @chmod($finalPath, 0664);

            $sha256 = hash_file('sha256', $finalPath);
            if ($sha256 === false) {
                throw new RuntimeException("Failed to compute SHA-256 for package: {$finalPath}");
            }
            $sizeBytes = filesize($finalPath);

            $releaseNotes = $notes ?: (
                "customer-site " . $resolvedCommit .
                " (auto-synchronized on deployment at " . now()->toIso8601String() . ")"
            );

            // Step 4: Register and activate in database atomically
            return DB::transaction(function () use ($resolvedVersion, $finalPath, $sha256, $sizeBytes, $releaseNotes) {
                CustomerInstallationPackage::where('is_active', true)->update(['is_active' => false]);

                $pkg = CustomerInstallationPackage::updateOrCreate(
                    ['version' => $resolvedVersion],
                    [
                        'arvan_key'     => '',
                        'local_path'    => $finalPath,
                        'sha256'        => $sha256,
                        'size_bytes'    => $sizeBytes,
                        'released_at'   => now(),
                        'is_active'     => true,
                        'release_notes' => $releaseNotes,
                    ]
                );

                Log::info("CustomerPackageSync: Registered and activated package v{$resolvedVersion}", [
                    'package_id' => $pkg->id,
                    'sha256'     => $sha256,
                    'size_bytes' => $sizeBytes,
                    'path'       => $finalPath,
                ]);

                return $pkg;
            });
        } catch (\Throwable $e) {
            if (file_exists($tempZipPath)) {
                @unlink($tempZipPath);
            }
            Log::error("CustomerPackageSync failed: " . $e->getMessage(), [
                'source'  => $sourcePath,
                'version' => $resolvedVersion,
                'commit'  => $resolvedCommit,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate that source directory exists and contains necessary customer-site files.
     */
    public function validateSourceDirectory(string $sourcePath): void
    {
        if (!is_dir($sourcePath)) {
            throw new InvalidArgumentException("Source customer-site directory not found: {$sourcePath}");
        }

        foreach (self::REQUIRED_SOURCE_MARKERS as $marker) {
            $markerPath = $sourcePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $marker);
            if (!file_exists($markerPath)) {
                throw new InvalidArgumentException("Source directory is missing required customer-site file: {$marker} ({$markerPath})");
            }
        }
    }

    /**
     * Resolve commit hash from argument, release-manifest, or git.
     */
    public function resolveCommit(string $sourcePath, ?string $commit): string
    {
        if (!empty($commit)) {
            return trim($commit);
        }

        $manifestPath = $sourcePath . '/.deployment/release-manifest.json';
        if (file_exists($manifestPath)) {
            $manifest = json_decode((string) @file_get_contents($manifestPath), true);
            if (!empty($manifest['commit'])) {
                return trim((string) $manifest['commit']);
            }
        }

        $gitHeadPath = $sourcePath . '/.git/HEAD';
        if (file_exists($gitHeadPath)) {
            $headContent = trim((string) @file_get_contents($gitHeadPath));
            if (str_starts_with($headContent, 'ref: ')) {
                $refFile = $sourcePath . '/.git/' . substr($headContent, 5);
                if (file_exists($refFile)) {
                    return trim((string) @file_get_contents($refFile));
                }
            } elseif (strlen($headContent) >= 40) {
                return $headContent;
            }
        }

        return 'rel-' . date('YmdHis');
    }

    /**
     * Compute next semantic version if not explicitly passed.
     */
    public function resolveVersion(?string $explicitVersion): string
    {
        if (!empty($explicitVersion)) {
            return trim($explicitVersion);
        }

        $latestPkg = CustomerInstallationPackage::orderBy('id', 'desc')->first();
        $latestVersion = $latestPkg?->version ?? '1.1.0';

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $latestVersion, $matches)) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];
            $patch = (int) $matches[3] + 1;
            $next = "{$major}.{$minor}.{$patch}";
        } else {
            $next = '1.1.1';
        }

        if (version_compare($next, CustomerInstallationPackage::MINIMUM_SUPPORTED_VERSION, '<')) {
            $next = CustomerInstallationPackage::MINIMUM_SUPPORTED_VERSION;
        }

        return $next;
    }

    /**
     * Build the zip package with proper exclusions.
     * Prefers native CLI `zip` on systems where available (very fast), falls back to ZipArchive.
     */
    protected function createArchive(string $sourcePath, string $outputPath): void
    {
        $hasZipCli = $this->hasZipCli();

        if ($hasZipCli) {
            $this->createArchiveWithCli($sourcePath, $outputPath);
        } else {
            $this->createArchiveWithPhp($sourcePath, $outputPath);
        }
    }

    protected function hasZipCli(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return false;
        }
        $check = @shell_exec('command -v zip 2>/dev/null');
        return !empty($check);
    }

    protected function createArchiveWithCli(string $sourcePath, string $outputPath): void
    {
        $excludeArgs = [
            "-x '.git/*'",
            "-x '.github/*'",
            "-x '.githooks/*'",
            "-x '.continue/*'",
            "-x '.clinerules'",
            "-x '.env'",
            "-x '.env.backup*'",
            "-x '.env.local*'",
            "-x '*.log'",
            "-x 'storage/logs/*'",
            "-x 'storage/framework/cache/*'",
            "-x 'storage/framework/sessions/*'",
            "-x 'storage/framework/views/*'",
            "-x 'storage/app/public/uploads/*'",
            "-x 'public/uploads/*'",
            "-x 'public/uploads'",
            "-x 'public/storage'",
            "-x 'public/storage/*'",
            "-x 'public/storage/**'",
            "-x 'bootstrap/cache/*.php'",
            "-x 'installed.lock'",
            "-x '*/installed.lock'",
            "-x 'storage/installed.lock'",
            "-x '*.sql'",
            "-x '*.sql.gz'",
            "-x '*.zip'",
            "-x '*.tar'",
            "-x '*.bak'",
            "-x '*.backup*'",
        ];

        $cmd = sprintf(
            'cd %s && zip -r -q %s . %s',
            escapeshellarg($sourcePath),
            escapeshellarg($outputPath),
            implode(' ', $excludeArgs)
        );

        $output = [];
        $exitCode = 0;
        @exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($outputPath)) {
            throw new RuntimeException("CLI zip creation failed (exit code {$exitCode}): " . implode("\n", $output));
        }
    }

    protected function createArchiveWithPhp(string $sourcePath, string $outputPath): void
    {
        $zip = new ZipArchive();
        $res = $zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($res !== true) {
            throw new RuntimeException("Failed to open ZipArchive at {$outputPath}: code {$res}");
        }

        $sourcePathReal = realpath($sourcePath) ?: $sourcePath;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePathReal, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getRealPath();
            $relativePath = str_replace('\\', '/', substr($filePath, strlen($sourcePathReal) + 1));

            if ($this->shouldExcludePath($relativePath)) {
                continue;
            }

            $zip->addFile($filePath, $relativePath);
        }

        // Ensure storage directory skeleton exists in zip
        $emptyDirs = [
            'storage/logs',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'bootstrap/cache',
        ];
        foreach ($emptyDirs as $emptyDir) {
            $zip->addEmptyDir($emptyDir);
        }

        $zip->close();
    }

    public function shouldExcludePath(string $relativePath): bool
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');

        // Critical sensitive files
        if ($relativePath === '.env' || str_starts_with($relativePath, '.env.backup') || str_starts_with($relativePath, '.env.local')) {
            return true;
        }

        if ($relativePath === 'installed.lock') {
            return true;
        }

        if (str_starts_with($relativePath, '.git/') || $relativePath === '.git') {
            return true;
        }
        if (str_starts_with($relativePath, '.github/') || $relativePath === '.github') {
            return true;
        }
        if (str_starts_with($relativePath, '.githooks/') || $relativePath === '.githooks') {
            return true;
        }
        if (str_starts_with($relativePath, '.continue/') || $relativePath === '.continue') {
            return true;
        }
        if ($relativePath === '.clinerules') {
            return true;
        }

        // Compiled bootstrap cache files (config.php, routes-v7.php, services.php, events.php, packages.php)
        // Must preserve the bootstrap/cache folder skeleton and its .gitignore
        if (str_starts_with($relativePath, 'bootstrap/cache/') && str_ends_with($relativePath, '.php')) {
            return true;
        }

        // Storage symlink or physical public/storage directory
        if ($relativePath === 'public/storage' || str_starts_with($relativePath, 'public/storage/')) {
            return true;
        }

        // Runtime storage caches and logs
        if (str_starts_with($relativePath, 'storage/logs/') && !str_ends_with($relativePath, '.gitignore')) {
            return true;
        }
        if (str_starts_with($relativePath, 'storage/framework/cache/') && !str_ends_with($relativePath, '.gitignore')) {
            return true;
        }
        if (str_starts_with($relativePath, 'storage/framework/sessions/') && !str_ends_with($relativePath, '.gitignore')) {
            return true;
        }
        if (str_starts_with($relativePath, 'storage/framework/views/') && !str_ends_with($relativePath, '.gitignore')) {
            return true;
        }
        if (str_starts_with($relativePath, 'storage/app/public/uploads/')) {
            return true;
        }
        if (str_starts_with($relativePath, 'public/uploads/')) {
            return true;
        }

        // Temporary and database dumps
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['log', 'sql', 'gz', 'tar', 'bak'], true)) {
            return true;
        }
        if ($ext === 'zip' && !str_contains($relativePath, 'vendor/')) {
            return true;
        }

        return false;
    }

    /**
     * Verify that the zip archive is valid, readable, and satisfies installation requirements.
     */
    public function verifyArchiveIntegrity(string $zipPath, int $minSizeBytes = 1024): void
    {
        if (!file_exists($zipPath)) {
            throw new RuntimeException("Generated package zip does not exist: {$zipPath}");
        }

        $size = filesize($zipPath);
        if ($size === false || $size < $minSizeBytes) {
            throw new RuntimeException("Generated package zip is abnormally small ({$size} bytes): {$zipPath}");
        }

        $zip = new ZipArchive();
        $res = $zip->open($zipPath, ZipArchive::RDONLY);
        if ($res !== true) {
            throw new RuntimeException("Failed to open generated package zip (code {$res}): {$zipPath}");
        }

        // Check required and forbidden files
        $artisanFound = false;
        $themeServiceFound = false;
        $envFound = false;
        $lockFound = false;
        $bootstrapCacheFound = false;
        $publicStorageFound = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($i));
            if ($name === 'artisan') {
                $artisanFound = true;
            }
            if ($name === 'app/Services/ThemeCanvas/ThemeInstanceRevisionService.php') {
                $themeServiceFound = true;
            }
            if ($name === '.env') {
                $envFound = true;
            }
            if ($name === 'installed.lock') {
                $lockFound = true;
            }
            if (str_starts_with($name, 'bootstrap/cache/') && str_ends_with($name, '.php')) {
                $bootstrapCacheFound = true;
            }
            if ($name === 'public/storage' || str_starts_with($name, 'public/storage/')) {
                $publicStorageFound = true;
            }
        }

        $zip->close();

        if (!$artisanFound) {
            throw new RuntimeException("Generated package zip is missing 'artisan' entry: {$zipPath}");
        }

        if (!$themeServiceFound) {
            throw new RuntimeException("Generated package zip is missing ThemeInstanceRevisionService: {$zipPath}");
        }

        if ($envFound) {
            throw new RuntimeException("SECURITY ALERT: Generated package zip contains '.env'! Must be excluded: {$zipPath}");
        }

        if ($lockFound) {
            throw new RuntimeException("Generated package zip contains 'installed.lock'! Must be excluded: {$zipPath}");
        }

        if ($bootstrapCacheFound) {
            throw new RuntimeException("Generated package zip contains compiled 'bootstrap/cache/*.php'! Must be excluded: {$zipPath}");
        }

        if ($publicStorageFound) {
            throw new RuntimeException("Generated package zip contains 'public/storage'! Must be excluded: {$zipPath}");
        }
    }
}
