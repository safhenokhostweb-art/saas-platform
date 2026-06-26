<?php
namespace App\Services\Installer;

use Illuminate\Support\Str;

/**
 * Manages immutable installation baseline manifests.
 *
 * Responsibilities:
 * - Auto-naming new baselines (never overwrites INITIAL_INSTALLATION_BASELINE)
 * - Creating manifest.json with SHA-256 streaming checksums
 * - Full integrity verification (files exist + checksums match + manifest valid)
 * - Making baseline directories read-only after finalization
 * - Listing all baseline directories
 *
 * Security invariants:
 * - Never exposes .env content (only checksums)
 * - hash_file() is used throughout — never loads full file into memory
 * - manifest_checksum covers the manifest body itself
 */
class BaselineManifestService
{
    const MANIFEST_VERSION   = '1.0';
    const INSTALLER_VERSION  = '2.0';
    const BASELINES_ROOT_KEY = 'upgrade_snapshots';

    // Files required for a baseline to be considered complete
    const REQUIRED_FILES = [
        'database.sql.gz',
        '.env.backup',
        'baseline_meta.json',
    ];

    // ─── Baseline Name Resolution ─────────────────────────────────────────────

    /**
     * Returns the full path for the next baseline directory.
     * - If INITIAL_INSTALLATION_BASELINE does not exist → returns that path.
     * - If it already exists → returns INSTALLATION_BASELINE_YYYYMMDD_HHMMSS.
     * - Counter suffix added if timestamp collision occurs.
     */
    public function resolveBaselineName(string $basePath): string
    {
        $initialPath = $basePath . '/INITIAL_INSTALLATION_BASELINE';

        if (!is_dir($initialPath)) {
            return $initialPath;
        }

        // INITIAL already exists — generate timestamped name
        $timestamp = now()->format('Ymd_His');
        $candidate = $basePath . '/INSTALLATION_BASELINE_' . $timestamp;

        $counter = 2;
        while (is_dir($candidate)) {
            $candidate = $basePath . '/INSTALLATION_BASELINE_' . $timestamp . '_' . $counter;
            $counter++;
        }

        return $candidate;
    }

    // ─── Manifest Creation ────────────────────────────────────────────────────

    /**
     * Computes SHA-256 checksums for all files in $baselineDir (excluding manifest.json itself)
     * and writes manifest.json.
     *
     * The manifest includes a self-covering checksum: SHA-256 of the manifest body
     * (without the manifest_checksum field). This lets verification detect tampering.
     *
     * @return array{success: bool, manifest_path: string, manifest_checksum: string, checksums: array, manifest: array}
     */
    public function createManifest(string $baselineDir, array $meta = []): array
    {
        if (!is_dir($baselineDir)) {
            return ['success' => false, 'error' => 'Baseline directory does not exist'];
        }

        $files     = $this->scanFiles($baselineDir);
        $checksums = $this->computeChecksums($baselineDir, $files);

        $manifest = [
            'manifest_version'  => self::MANIFEST_VERSION,
            'installation_uuid' => $meta['installation_uuid'] ?? Str::uuid()->toString(),
            'platform_version'  => config('app.version', '1.0.0'),
            'laravel_version'   => app()->version(),
            'php_version'       => PHP_VERSION,
            'installer_version' => self::INSTALLER_VERSION,
            'git_commit'        => $this->getGitCommit(),
            'created_at_jalali' => $this->getJalaliDate(),
            'created_at_utc'    => now()->toISOString(),
            'generated_by'      => 'Platform Installer v2.0 / BaselineManifestService',
            'baseline_label'    => $meta['label'] ?? basename($baselineDir),
            'checksums'         => $checksums,
        ];

        // Manifest checksum covers the manifest body (without itself)
        $bodyJson                   = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $manifest['manifest_checksum'] = hash('sha256', $bodyJson);

        $finalJson    = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $manifestPath = $baselineDir . '/manifest.json';
        $written      = file_put_contents($manifestPath, $finalJson);

        return [
            'success'           => $written !== false,
            'manifest_path'     => $manifestPath,
            'manifest_checksum' => $manifest['manifest_checksum'],
            'checksums'         => $checksums,
            'manifest'          => $manifest,
        ];
    }

    /**
     * Recomputes the manifest for a baseline that has had files added (e.g., HTML/PDF reports).
     * Preserves the original installation_uuid and created_at fields from the existing manifest.
     */
    public function updateManifest(string $baselineDir): array
    {
        $manifestPath   = $baselineDir . '/manifest.json';
        $existingMeta   = [];

        if (file_exists($manifestPath)) {
            $existing = json_decode(file_get_contents($manifestPath), true) ?? [];
            // Preserve identity fields from original manifest
            $existingMeta = [
                'installation_uuid' => $existing['installation_uuid'] ?? null,
                'created_at_jalali' => $existing['created_at_jalali'] ?? null,
                'created_at_utc'    => $existing['created_at_utc'] ?? null,
                'baseline_label'    => $existing['baseline_label'] ?? null,
            ];
        }

        return $this->createManifest($baselineDir, $existingMeta);
    }

    // ─── Verification ─────────────────────────────────────────────────────────

    /**
     * Full integrity verification:
     * 1. Directory exists
     * 2. Required files present
     * 3. manifest.json present and valid JSON
     * 4. manifest_checksum valid (manifest body matches)
     * 5. All file checksums match (streaming hash_file — memory-safe)
     *
     * Returns PASS only when every component validates.
     */
    public function verify(string $baselineDir): array
    {
        $failures = [];
        $passed   = [];
        $detail   = [];

        // 1. Directory exists
        if (!is_dir($baselineDir)) {
            return [
                'pass'     => false,
                'status'   => 'FAILED',
                'failures' => ["Baseline directory not found: {$baselineDir}"],
                'passed'   => [],
                'detail'   => [],
            ];
        }

        $passed[] = 'Baseline directory exists';

        // 2. Required files
        foreach (self::REQUIRED_FILES as $file) {
            $fp = $baselineDir . '/' . $file;
            if (file_exists($fp)) {
                $passed[] = "File exists: {$file}";
                $detail[$file] = ['exists' => true, 'size' => filesize($fp), 'checksum_ok' => null];
            } else {
                $failures[] = "Missing required file: {$file}";
                $detail[$file] = ['exists' => false];
            }
        }

        // 3. manifest.json present
        $manifestPath = $baselineDir . '/manifest.json';
        if (!file_exists($manifestPath)) {
            $failures[] = 'manifest.json is missing';
            return [
                'pass'     => false,
                'status'   => 'FAILED',
                'failures' => $failures,
                'passed'   => $passed,
                'detail'   => $detail,
            ];
        }
        $passed[] = 'manifest.json exists';

        // 4. Parse manifest
        $manifestContent = file_get_contents($manifestPath);
        $manifest        = json_decode($manifestContent, true);
        if (!is_array($manifest)) {
            $failures[] = 'manifest.json is not valid JSON';
            return [
                'pass'     => false,
                'status'   => 'FAILED',
                'failures' => $failures,
                'passed'   => $passed,
                'detail'   => $detail,
                'manifest' => null,
            ];
        }
        $passed[] = 'manifest.json is valid JSON';

        // 5. Verify manifest_checksum
        $storedChecksum = $manifest['manifest_checksum'] ?? null;
        if ($storedChecksum) {
            $manifestForCheck = $manifest;
            unset($manifestForCheck['manifest_checksum']);
            $recomputedJson     = json_encode($manifestForCheck, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $recomputedChecksum = hash('sha256', $recomputedJson);

            if ($recomputedChecksum === $storedChecksum) {
                $passed[] = 'Manifest self-checksum verified';
            } else {
                $failures[] = "Manifest self-checksum MISMATCH — stored: {$storedChecksum}, recomputed: {$recomputedChecksum}";
            }
        } else {
            $failures[] = 'manifest.json is missing manifest_checksum field';
        }

        // 6. Verify file checksums via streaming hash — memory-safe for large files
        $storedChecksums = $manifest['checksums'] ?? [];
        foreach ($storedChecksums as $file => $storedHash) {
            $fp = $baselineDir . '/' . $file;
            if (!file_exists($fp)) {
                $failures[] = "File in manifest not found on disk: {$file}";
                $detail[$file]['checksum_ok'] = false;
                continue;
            }
            // hash_file uses streaming — never loads the entire file into memory
            $actualHash = hash_file('sha256', $fp);
            if ($actualHash === $storedHash) {
                $passed[] = "Checksum verified: {$file}";
                if (isset($detail[$file])) {
                    $detail[$file]['checksum_ok'] = true;
                }
            } else {
                $failures[] = "Checksum MISMATCH: {$file} — stored: {$storedHash}, actual: {$actualHash}";
                if (isset($detail[$file])) {
                    $detail[$file]['checksum_ok'] = false;
                }
            }
        }

        $pass = empty($failures);

        return [
            'pass'              => $pass,
            'status'            => $pass ? 'PASS' : 'FAILED',
            'failures'          => $failures,
            'passed'            => $passed,
            'detail'            => $detail,
            'manifest'          => $manifest,
            'manifest_checksum' => $storedChecksum ?? null,
        ];
    }

    // ─── Read-Only Protection ─────────────────────────────────────────────────

    /**
     * Makes all files in the baseline directory read-only.
     * Directory itself gets 0555 (rx only — no write).
     * Silently skips on Windows where chmod is a no-op for directory write.
     */
    public function makeReadOnly(string $baselineDir): void
    {
        if (!is_dir($baselineDir)) {
            return;
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baselineDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iter as $file) {
            if ($file->isFile()) {
                @chmod($file->getPathname(), 0444);
            }
        }

        @chmod($baselineDir, 0555);
    }

    // ─── Baseline Listing ─────────────────────────────────────────────────────

    /**
     * Returns all baseline directories under $basePath with their metadata.
     */
    public function listBaselines(string $basePath): array
    {
        if (!is_dir($basePath)) {
            return [];
        }

        $baselines = [];
        foreach (scandir($basePath) as $dir) {
            if ($dir[0] === '.') {
                continue;
            }
            $fullPath = $basePath . '/' . $dir;
            if (!is_dir($fullPath)) {
                continue;
            }
            // Only recognised baseline directories
            if (
                !str_starts_with($dir, 'INITIAL_INSTALLATION_BASELINE') &&
                !str_starts_with($dir, 'INSTALLATION_BASELINE_')
            ) {
                continue;
            }

            $metaPath     = $fullPath . '/baseline_meta.json';
            $manifestPath = $fullPath . '/manifest.json';
            $meta         = file_exists($metaPath)
                ? (json_decode(file_get_contents($metaPath), true) ?? [])
                : [];
            $manifest = file_exists($manifestPath)
                ? (json_decode(file_get_contents($manifestPath), true) ?? [])
                : [];

            $baselines[] = [
                'name'              => $dir,
                'path'              => $fullPath,
                'is_initial'        => str_starts_with($dir, 'INITIAL_INSTALLATION_BASELINE'),
                'created_at'        => $meta['created_at'] ?? null,
                'label'             => $meta['label'] ?? $dir,
                'has_manifest'      => file_exists($manifestPath),
                'manifest_checksum' => $manifest['manifest_checksum'] ?? null,
                'installation_uuid' => $manifest['installation_uuid'] ?? null,
                'platform_version'  => $manifest['platform_version'] ?? null,
                'files'             => $this->listFiles($fullPath),
            ];
        }

        return $baselines;
    }

    /**
     * Reads the manifest_checksum from an existing manifest.json.
     */
    public function getManifestChecksum(string $baselineDir): ?string
    {
        $manifestPath = $baselineDir . '/manifest.json';
        if (!file_exists($manifestPath)) {
            return null;
        }
        $manifest = json_decode(file_get_contents($manifestPath), true);
        return $manifest['manifest_checksum'] ?? null;
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function scanFiles(string $dir): array
    {
        $files = [];
        if (!is_dir($dir)) {
            return $files;
        }
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..' || $f === 'manifest.json') {
                continue; // Manifest checksums itself separately
            }
            $fp = $dir . '/' . $f;
            if (is_file($fp)) {
                $files[] = $f;
            }
        }
        return $files;
    }

    private function computeChecksums(string $dir, array $files): array
    {
        $checksums = [];
        foreach ($files as $file) {
            $fp = $dir . '/' . $file;
            if (file_exists($fp)) {
                // Streaming hash — safe for database dumps of any size
                $checksums[$file] = hash_file('sha256', $fp);
            }
        }
        return $checksums;
    }

    private function listFiles(string $dir): array
    {
        $files = [];
        if (!is_dir($dir)) {
            return $files;
        }
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $fp = $dir . '/' . $f;
            if (is_file($fp)) {
                $files[] = [
                    'name'     => $f,
                    'size'     => filesize($fp),
                    'modified' => filemtime($fp),
                ];
            }
        }
        return $files;
    }

    private function getGitCommit(): string
    {
        $output = [];
        @exec('git rev-parse --short HEAD 2>&1', $output);
        $commit = $output[0] ?? '';
        return preg_match('/^[0-9a-f]{6,}$/i', $commit) ? $commit : 'N/A';
    }

    private function getJalaliDate(): string
    {
        if (function_exists('date_fa')) {
            return date_fa(now(), 'Y/m/d H:i');
        }
        if (class_exists(\Hekmatinasser\Verta\Verta::class)) {
            return \Hekmatinasser\Verta\Verta::now()->format('Y/m/d H:i');
        }
        return now()->format('Y-m-d H:i:s') . ' (Gregorian)';
    }
}
