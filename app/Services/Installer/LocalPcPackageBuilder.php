<?php

namespace App\Services\Installer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Builds a portable backup package for download to the admin's local PC (LEVEL 3 DR).
 *
 * Package structure (all paths relative — no server IPs, hostnames, or absolute paths):
 *   Platform_YYYY-MM-DD_HHmmss.zip
 *   ├── manifest.json              ← SHA-256 checksums + self-signature; NO absolute paths
 *   ├── database/
 *   │   └── platform.sql.gz        ← fresh platform mysqldump
 *   ├── metadata/
 *   │   ├── platform_info.json     ← version, PHP, Laravel (NO server IP or paths)
 *   │   └── sites_overview.json    ← domains + status (NO filesystem paths)
 *   └── customers/                 ← only in full package
 *       └── site_{id}_{domain}.sql.gz
 *
 * Portability guarantees:
 *   - Zero absolute filesystem paths anywhere in the package
 *   - Zero server IP or hostname
 *   - Zero VPS-specific identifiers
 *   - Restores cleanly to any VPS, cPanel, DirectAdmin, SFTP, or Docker target
 *
 * Upload & Restore:
 *   Use extractAndVerify() to validate an uploaded ZIP and obtain the DB file path.
 */
class LocalPcPackageBuilder
{
    private string $buildDir    = '';
    private string $packageName = '';

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Build a platform-only portable package.
     * Contains: platform DB + metadata + manifest.
     *
     * @return array{success: bool, zip_path: string|null, filename: string|null, size: int|null, message: string}
     */
    public function buildPlatformPackage(): array
    {
        return $this->build(includeCustomers: false);
    }

    /**
     * Build a full package: platform + all customer site DB dumps.
     *
     * @return array{success: bool, zip_path: string|null, filename: string|null, size: int|null, message: string}
     */
    public function buildFullPackage(): array
    {
        return $this->build(includeCustomers: true);
    }

    /**
     * Extract an uploaded ZIP to a temp directory and verify its integrity.
     * Checks:
     *   1. ZIP opens successfully
     *   2. manifest.json exists and is valid JSON
     *   3. manifest self-signature is intact (tamper detection)
     *   4. SHA-256 of each listed file matches
     *   5. database/platform.sql.gz is present
     *
     * Returns the extract_dir and db_path so the caller can pass db_path to RestoreService.
     * Caller MUST clean up extract_dir after restore completes.
     *
     * @return array{valid: bool, message: string, extract_dir: string|null, db_path: string|null, manifest: array|null}
     */
    public function extractAndVerify(string $zipPath): array
    {
        if (!file_exists($zipPath) || filesize($zipPath) < 100) {
            return ['valid' => false, 'message' => 'فایل ZIP معتبر نیست یا یافت نشد', 'extract_dir' => null, 'db_path' => null, 'manifest' => null];
        }

        $extractDir = storage_path('app/pc-restore-uploads/extracted_' . time() . '_' . substr(md5($zipPath), 0, 8));
        @mkdir($extractDir, 0750, true);

        // Safe extraction (zip-slip protection)
        $safeResult = $this->safeExtract($zipPath, $extractDir);
        if (!$safeResult) {
            $this->deleteDirectory($extractDir);
            return ['valid' => false, 'message' => 'ZIP باز نشد یا حاوی مسیر مخرب است', 'extract_dir' => null, 'db_path' => null, 'manifest' => null];
        }

        // Read manifest
        $manifestPath = $extractDir . '/manifest.json';
        if (!file_exists($manifestPath)) {
            $this->deleteDirectory($extractDir);
            return ['valid' => false, 'message' => 'manifest.json در پکیج یافت نشد — ممکن است فایل صحیح نباشد', 'extract_dir' => null, 'db_path' => null, 'manifest' => null];
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            $this->deleteDirectory($extractDir);
            return ['valid' => false, 'message' => 'manifest.json نامعتبر است', 'extract_dir' => null, 'db_path' => null, 'manifest' => null];
        }

        // Verify manifest self-signature (tamper detection)
        $storedSig = $manifest['manifest_signature'] ?? null;
        if (!is_string($storedSig) || !preg_match('/^[a-f0-9]{64}$/', $storedSig)) {
            $this->deleteDirectory($extractDir);
            return ['valid' => false, 'message' => 'manifest_signature معتبر در پکیج وجود ندارد', 'extract_dir' => null, 'db_path' => null, 'manifest' => $manifest];
        }
        $body = $manifest;
        unset($body['manifest_signature']);
        $computed = hash('sha256', json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (!hash_equals($storedSig, $computed)) {
            $this->deleteDirectory($extractDir);
            return ['valid' => false, 'message' => 'manifest.json دستکاری شده — امضا نامعتبر است', 'extract_dir' => null, 'db_path' => null, 'manifest' => $manifest];
        }

        if (!isset($manifest['files']['database/platform.sql.gz'])) {
            $this->deleteDirectory($extractDir);
            return ['valid' => false, 'message' => 'دیتابیس پلتفرم در checksum manifest ثبت نشده است', 'extract_dir' => null, 'db_path' => null, 'manifest' => $manifest];
        }

        // Verify SHA-256 of each listed file (streaming)
        foreach ($manifest['files'] ?? [] as $relativePath => $expectedSha) {
            $filePath = $extractDir . '/' . $relativePath;
            if (!file_exists($filePath)) {
                $this->deleteDirectory($extractDir);
                return ['valid' => false, 'message' => "فایل غایب در پکیج: {$relativePath}", 'extract_dir' => null, 'db_path' => null, 'manifest' => $manifest];
            }
            $actualSha = hash_file('sha256', $filePath);
            if ($actualSha !== $expectedSha) {
                $this->deleteDirectory($extractDir);
                return ['valid' => false, 'message' => "SHA-256 مغایرت: {$relativePath} — پکیج خراب است", 'extract_dir' => null, 'db_path' => null, 'manifest' => $manifest];
            }
        }

        // Confirm DB file is present
        $dbPath = $extractDir . '/database/platform.sql.gz';
        if (!file_exists($dbPath) || filesize($dbPath) < 100) {
            $this->deleteDirectory($extractDir);
            return ['valid' => false, 'message' => 'database/platform.sql.gz در پکیج یافت نشد یا خالی است', 'extract_dir' => null, 'db_path' => null, 'manifest' => $manifest];
        }

        return [
            'valid'       => true,
            'message'     => 'پکیج معتبر — تمام فایل‌ها با SHA-256 تأیید شدند',
            'extract_dir' => $extractDir,
            'db_path'     => $dbPath,
            'manifest'    => $manifest,
        ];
    }

    // ─── Core Build ───────────────────────────────────────────────────────────

    private function build(bool $includeCustomers): array
    {
        $this->packageName = 'Platform_' . date('Y-m-d_His');
        $this->buildDir    = sys_get_temp_dir() . '/saas_pc_build_' . time() . '_' . rand(1000, 9999);

        @mkdir($this->buildDir . '/database', 0750, true);
        @mkdir($this->buildDir . '/metadata', 0750, true);
        if ($includeCustomers) {
            @mkdir($this->buildDir . '/customers', 0750, true);
        }

        try {
            // 1. Fresh platform DB dump
            $dbResult = $this->dumpPlatformDb();
            if (!$dbResult['success']) {
                $this->cleanBuildDir();
                return ['success' => false, 'zip_path' => null, 'filename' => null, 'size' => null, 'message' => 'DB dump ناموفق: ' . $dbResult['message']];
            }

            // 2. Metadata (portable — no server IPs, no absolute paths, no credentials)
            $this->writePlatformInfo();
            $this->writeSitesOverview();

            // 3. Customer DB dumps (optional; uses existing LocalRecoveryLog baselines)
            if ($includeCustomers) {
                $this->writeCustomerDumps();
            }

            // 4. Manifest must be last (SHA-256 of all other files)
            $this->writeManifest($includeCustomers);

            // 5. ZIP all build files
            $zipPath = sys_get_temp_dir() . '/' . $this->packageName . '.zip';
            if (!$this->createZip($zipPath)) {
                $this->cleanBuildDir();
                return ['success' => false, 'zip_path' => null, 'filename' => null, 'size' => null, 'message' => 'ساخت ZIP ناموفق'];
            }

            $this->cleanBuildDir();

            return [
                'success'  => true,
                'zip_path' => $zipPath,
                'filename' => $this->packageName . '.zip',
                'size'     => filesize($zipPath),
                'message'  => 'پکیج پرتابل آماده است',
            ];
        } catch (\Throwable $e) {
            Log::error('LocalPcPackageBuilder build failed: ' . $e->getMessage());
            $this->cleanBuildDir();
            return ['success' => false, 'zip_path' => null, 'filename' => null, 'size' => null, 'message' => 'خطای داخلی: ' . $e->getMessage()];
        }
    }

    // ─── DB Dump ─────────────────────────────────────────────────────────────

    private function dumpPlatformDb(): array
    {
        $host    = config('database.connections.mysql.host', '127.0.0.1');
        $port    = (int) config('database.connections.mysql.port', 3306);
        $db      = config('database.connections.mysql.database');
        $user    = config('database.connections.mysql.username');
        $pass    = config('database.connections.mysql.password', '');
        $outFile = $this->buildDir . '/database/platform.sql.gz';

        if (empty($db) || empty($user)) {
            return ['success' => false, 'message' => 'پیکربندی دیتابیس ناقص است'];
        }

        // MYSQL_PWD avoids password in process list; single-transaction for consistency
        $cmd = sprintf(
            'MYSQL_PWD=%s mysqldump -u%s -h%s -P%d --single-transaction --quick --routines --triggers %s | gzip > %s 2>&1',
            escapeshellarg($pass),
            escapeshellarg($user),
            escapeshellarg($host),
            $port,
            escapeshellarg($db),
            escapeshellarg($outFile)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($outFile) || filesize($outFile) < 20) {
            return ['success' => false, 'message' => 'mysqldump ناموفق (exit=' . $exitCode . ')'];
        }

        return ['success' => true, 'path' => $outFile, 'size' => filesize($outFile)];
    }

    // ─── Metadata Files (All Portable) ───────────────────────────────────────

    private function writePlatformInfo(): void
    {
        // Deliberately excluded: server IP, VPS hostname, DB host/port, filesystem paths
        $info = [
            'created_at'            => now()->toISOString(),
            'created_by'            => 'SaaS Platform — Local PC Backup',
            'package_type'          => 'platform_pc_backup',
            'package_format_version'=> '1.0',
            'platform_version'      => config('app.version', '1.0'),
            'laravel_version'       => app()->version(),
            'php_version'           => PHP_VERSION,
            'restore_instructions'  => 'پکیج را در installer wizard آپلود کنید (Local PC source)',
            'portable'              => true,
        ];

        file_put_contents(
            $this->buildDir . '/metadata/platform_info.json',
            json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function writeSitesOverview(): void
    {
        try {
            $sites = DB::table('sites')
                ->select('id', 'title', 'domain', 'status', 'created_at')
                ->get()
                ->map(fn($s) => [
                    'id'         => $s->id,
                    'title'      => $s->title,
                    'domain'     => $s->domain,   // domain only — no server IP
                    'status'     => $s->status,
                    'created_at' => $s->created_at,
                    // Excluded: filesystem paths, server credentials, DB connection info
                ])
                ->all();

            file_put_contents(
                $this->buildDir . '/metadata/sites_overview.json',
                json_encode(
                    ['count' => count($sites), 'sites' => $sites],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                )
            );
        } catch (\Throwable) {
            // DB might not be accessible in some contexts; write empty overview
            file_put_contents(
                $this->buildDir . '/metadata/sites_overview.json',
                json_encode(['count' => 0, 'sites' => [], 'note' => 'DB not accessible at build time'])
            );
        }
    }

    private function writeCustomerDumps(): void
    {
        // Use verified LocalRecoveryLog baselines (most recent per site)
        try {
            $logs = \App\Models\Infrastructure\LocalRecoveryLog::customer()
                ->verified()
                ->orderByDesc('created_at')
                ->get()
                ->unique('site_id');

            foreach ($logs as $log) {
                $dumpFile = $log->local_path . DIRECTORY_SEPARATOR . 'customer_baseline.sql.gz';
                if (!file_exists($dumpFile)) continue;

                // Build portable filename (domain-based, no server paths)
                $domain = '';
                try {
                    $siteRow = DB::table('sites')->where('id', $log->site_id)->value('domain');
                    $domain  = preg_replace('/[^a-z0-9\-.]/', '_', strtolower($siteRow ?? ''));
                } catch (\Throwable) {}

                $destName = 'site_' . $log->site_id . ($domain ? '_' . $domain : '') . '.sql.gz';
                @copy($dumpFile, $this->buildDir . '/customers/' . $destName);
            }
        } catch (\Throwable $e) {
            Log::warning('LocalPcPackageBuilder: customer dumps skipped — ' . $e->getMessage());
        }
    }

    // ─── Manifest (SHA-256 Self-Signed) ──────────────────────────────────────

    private function writeManifest(bool $includeCustomers): void
    {
        $files = [];

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->buildDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iter as $file) {
            if (!$file->isFile()) continue;
            // Relative path within ZIP, always forward slashes
            $relative = ltrim(str_replace($this->buildDir, '', $file->getPathname()), DIRECTORY_SEPARATOR . '/\\');
            $relative = str_replace('\\', '/', $relative);
            $files[$relative] = hash_file('sha256', $file->getPathname());
        }

        $manifest = [
            'package_type'          => $includeCustomers ? 'full_backup' : 'platform_backup',
            'package_format_version'=> '1.0',
            'created_at'            => now()->toISOString(),
            'files'                 => $files,
            // NOTE: server_ip, db_host, absolute_paths, vps_id are intentionally absent
        ];

        // Self-sign the manifest body
        $bodyJson  = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash('sha256', $bodyJson);
        $manifest['manifest_signature'] = $signature;

        file_put_contents(
            $this->buildDir . '/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    // ─── ZIP ─────────────────────────────────────────────────────────────────

    private function createZip(string $zipPath): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->buildDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iter as $file) {
            if (!$file->isFile()) continue;
            $relative = ltrim(str_replace($this->buildDir, '', $file->getPathname()), DIRECTORY_SEPARATOR . '/\\');
            $relative = str_replace('\\', '/', $relative);
            $zip->addFile($file->getPathname(), $relative);
        }

        return $zip->close();
    }

    /**
     * ZIP extraction with zip-slip (path traversal) protection.
     */
    private function safeExtract(string $zipPath, string $extractDir): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        $realBase = realpath($extractDir);
        if ($realBase === false) {
            $zip->close();
            return false;
        }
        $realBase .= DIRECTORY_SEPARATOR;

        // Validate every entry before extraction
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Reject ../ or absolute paths
            if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\..')) {
                $zip->close();
                Log::warning("LocalPcPackageBuilder: zip-slip attempt — entry: {$name}");
                return false;
            }
        }

        $zip->extractTo($extractDir);
        $zip->close();
        return true;
    }

    // ─── Cleanup Helpers ──────────────────────────────────────────────────────

    private function cleanBuildDir(): void
    {
        if ($this->buildDir && is_dir($this->buildDir)) {
            $this->deleteDirectory($this->buildDir);
        }
    }

    public function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
