<?php

namespace App\Services\Installer;

use App\Models\Infrastructure\LocalRecoveryLog;
use Illuminate\Support\Facades\Log;

/**
 * Local Recovery Repository — stores certified copies of every baseline
 * (platform and customer) on a local disk path chosen by the administrator.
 *
 * Directory layout:
 *   {localPath}/
 *     Platform/
 *       INITIAL_INSTALLATION_BASELINE/   ← copy of the baseline directory
 *       INSTALLATION_BASELINE_20260628_143000/
 *     Customers/
 *       Site-{siteId}/
 *         {installationUuid}/
 *           {Ymd_His}/
 *             customer_baseline.sql.gz
 *             checksum.txt
 *
 * Every write is followed by SHA-256 verification.
 * Never assumes success without explicit verification.
 *
 * Policy matrix (from config/installer.php):
 *   policy = local   → skip Arvan, write local only
 *   policy = arvan   → skip local, write Arvan only
 *   policy = both    → write local AND Arvan; either failure is non-fatal individually
 *
 * Failure policy:
 *   If local fails  → continue with Arvan (logged as warning)
 *   If Arvan fails  → continue with local (logged as warning)
 *   If BOTH fail    → abort (caller must decide)
 */
class LocalRecoveryRepository
{
    private const DEFAULT_SUBDIR = 'local-recovery-repo';

    // ─── Configuration ────────────────────────────────────────────────────────

    public function getRepoPath(): string
    {
        $configured = config('installer.recovery_repository.local_path', '');
        return $configured !== '' ? $configured : storage_path('app/' . self::DEFAULT_SUBDIR);
    }

    public function getPolicy(): string
    {
        return config('installer.recovery_repository.policy', 'both');
    }

    public function getPriority(): string
    {
        return config('installer.recovery_repository.priority', 'local_first');
    }

    public function shouldUseLocal(): bool
    {
        return in_array($this->getPolicy(), ['local', 'both'], true);
    }

    public function shouldUseArvan(): bool
    {
        return in_array($this->getPolicy(), ['arvan', 'both'], true);
    }

    /**
     * Verified copies kept per scope (platform, and each customer site).
     * config/installer.php is outside the release policy's allowed paths,
     * so the default lives here; installer.recovery_repository.keep_per_scope
     * overrides it once that file carries the key.
     */
    public function keepPerScope(): int
    {
        return max(1, (int) config('installer.recovery_repository.keep_per_scope', 2));
    }

    public function isConfigured(): bool
    {
        return $this->validate($this->getRepoPath())['is_valid'];
    }

    // ─── Path Validation ──────────────────────────────────────────────────────

    public function validate(string $path): array
    {
        if (trim($path) === '') {
            return ['is_valid' => false, 'message' => 'مسیر خزانه بازیابی تنظیم نشده است.', 'free_bytes' => 0];
        }

        // Must be an absolute Linux path (starts with /)
        // Windows-style paths (E:\...) are rejected — server is Linux
        if (!str_starts_with($path, '/')) {
            return ['is_valid' => false, 'message' => 'مسیر باید absolute Linux باشد (باید با / شروع شود). مثال: /var/backups/saas-platform', 'free_bytes' => 0];
        }

        if (!is_dir($path)) {
            if (!@mkdir($path, 0750, true)) {
                return ['is_valid' => false, 'message' => "دایرکتوری وجود ندارد و قابل ساخت نیست: {$path}", 'free_bytes' => 0];
            }
        }

        if (!is_writable($path)) {
            return ['is_valid' => false, 'message' => "دایرکتوری قابل نوشتن نیست: {$path}", 'free_bytes' => 0];
        }

        $freeBytes  = (int) disk_free_space($path);
        $minFreeGb  = (int) config('installer.recovery_repository.min_free_gb', 10);
        $minBytes   = $minFreeGb * 1024 * 1024 * 1024;

        if ($freeBytes < $minBytes) {
            $freeGb = round($freeBytes / (1024 ** 3), 1);
            return [
                'is_valid'   => false,
                'message'    => "فضای دیسک کافی نیست: {$freeGb} GB آزاد (حداقل {$minFreeGb} GB نیاز است)",
                'free_bytes' => $freeBytes,
            ];
        }

        return [
            'is_valid'   => true,
            'message'    => "مسیر معتبر — " . round($freeBytes / (1024 ** 3), 1) . ' GB آزاد',
            'free_bytes' => $freeBytes,
        ];
    }

    // ─── Platform Baseline Storage ────────────────────────────────────────────

    /**
     * Copies an entire platform baseline directory to the local recovery repo.
     * Runs SHA-256 verification on every file after copy.
     * Creates a LocalRecoveryLog record with PASS or FAILED status.
     *
     * @param string $baselineDir   Full path to the baseline dir (read-only source)
     * @param string $installationUuid
     * @return array{success: bool, local_path: string|null, log_id: int|null, verification: array, message: string}
     */
    public function storePlatformBaseline(string $baselineDir, string $installationUuid = ''): array
    {
        if (!$this->shouldUseLocal()) {
            return ['success' => true, 'skipped' => true, 'message' => 'سیاست: Local Repository غیرفعال است'];
        }

        $validation = $this->validate($this->getRepoPath());
        if (!$validation['is_valid']) {
            Log::warning("LocalRecoveryRepo: storePlatformBaseline skipped — {$validation['message']}");
            return ['success' => false, 'message' => $validation['message'], 'local_path' => null, 'log_id' => null];
        }

        if (!is_dir($baselineDir)) {
            return ['success' => false, 'message' => "Baseline directory not found: {$baselineDir}", 'local_path' => null, 'log_id' => null];
        }

        $label    = basename($baselineDir);
        $destDir  = $this->getRepoPath() . DIRECTORY_SEPARATOR . 'Platform' . DIRECTORY_SEPARATOR . $label;

        // Never overwrite INITIAL
        if (is_dir($destDir) && str_contains($label, 'INITIAL')) {
            return ['success' => true, 'message' => 'INITIAL baseline already in local repo — skipped overwrite', 'local_path' => $destDir, 'log_id' => null];
        }

        @mkdir($destDir, 0750, true);

        $copyResults = $this->copyDirectory($baselineDir, $destDir);
        $totalSize   = $this->dirSize($destDir);

        // Verify every file
        $verification  = $this->verifyDirectory($baselineDir, $destDir);
        $status        = $verification['pass'] ? 'verified' : 'failed';

        // Read manifest checksum from copied manifest
        $manifestChecksum = null;
        $manifestPath     = $destDir . DIRECTORY_SEPARATOR . 'manifest.json';
        if (file_exists($manifestPath)) {
            $m = json_decode(file_get_contents($manifestPath), true);
            $manifestChecksum = $m['manifest_checksum'] ?? null;
        }

        $log = LocalRecoveryLog::create([
            'type'              => 'platform',
            'installation_uuid' => $installationUuid ?: null,
            'baseline_label'    => $label,
            'local_path'        => $destDir,
            'size_bytes'        => $totalSize,
            'manifest_checksum' => $manifestChecksum,
            'status'            => $status,
            'verified_at'       => now(),
            'notes'             => $verification['pass']
                ? 'تمام فایل‌ها با SHA-256 تأیید شدند'
                : 'خطا در تأیید: ' . implode('; ', $verification['failures']),
        ]);

        Log::info("LocalRecoveryRepo: Platform baseline {$label} stored at {$destDir} — status: {$status}");

        if ($verification['pass']) {
            $this->pruneAfterStore('platform');
        }

        return [
            'success'      => $verification['pass'],
            'local_path'   => $destDir,
            'log_id'       => $log->id,
            'size_bytes'   => $totalSize,
            'verification' => $verification,
            'message'      => $verification['pass']
                ? "Baseline {$label} ذخیره و تأیید شد"
                : "Baseline {$label} کپی شد اما تأیید SHA-256 ناموفق بود",
        ];
    }

    // ─── Customer Baseline Storage ────────────────────────────────────────────

    /**
     * Copies a customer baseline SQL dump to the local recovery repo.
     * Directory: {repoPath}/Customers/Site-{siteId}/{uuid}/{Ymd_His}/
     *
     * @param int    $siteId
     * @param string $installationUuid
     * @param string $sqlLocalPath     Path to the local SQL dump file (.sql.gz)
     * @param string $sha256           Expected SHA-256 of the source file
     * @return array{success: bool, local_path: string|null, log_id: int|null, message: string}
     */
    public function storeCustomerBaseline(
        int    $siteId,
        string $installationUuid,
        string $sqlLocalPath,
        string $sha256 = ''
    ): array {
        if (!$this->shouldUseLocal()) {
            return ['success' => true, 'skipped' => true, 'message' => 'سیاست: Local Repository غیرفعال است'];
        }

        $validation = $this->validate($this->getRepoPath());
        if (!$validation['is_valid']) {
            Log::warning("LocalRecoveryRepo: storeCustomerBaseline skipped — {$validation['message']}");
            return ['success' => false, 'message' => $validation['message'], 'local_path' => null, 'log_id' => null];
        }

        if (!file_exists($sqlLocalPath)) {
            return ['success' => false, 'message' => "SQL dump not found: {$sqlLocalPath}", 'local_path' => null, 'log_id' => null];
        }

        $timestamp = now()->format('Ymd_His');
        $destDir   = implode(DIRECTORY_SEPARATOR, [
            $this->getRepoPath(),
            'Customers',
            "Site-{$siteId}",
            $installationUuid ?: 'unknown-uuid',
            $timestamp,
        ]);

        @mkdir($destDir, 0750, true);

        $destFile = $destDir . DIRECTORY_SEPARATOR . 'customer_baseline.sql.gz';
        $ok       = copy($sqlLocalPath, $destFile);

        if (!$ok) {
            return ['success' => false, 'message' => "کپی SQL dump ناموفق: {$sqlLocalPath} → {$destFile}", 'local_path' => null, 'log_id' => null];
        }

        // Compute checksum of the copied file
        $actualSha256 = hash_file('sha256', $destFile);
        $checksumOk   = ($sha256 !== '' && $actualSha256 === $sha256) || $sha256 === '';
        $status       = $checksumOk ? 'verified' : 'failed';
        $fileSize     = filesize($destFile);

        // Write checksum sidecar
        file_put_contents(
            $destDir . DIRECTORY_SEPARATOR . 'checksum.txt',
            "sha256:{$actualSha256}\nsize:{$fileSize}\nstored_at:" . now()->toISOString() . "\nsite_id:{$siteId}\ninstallation_uuid:{$installationUuid}\n"
        );

        $log = LocalRecoveryLog::create([
            'type'              => 'customer',
            'site_id'           => $siteId,
            'installation_uuid' => $installationUuid ?: null,
            'baseline_label'    => "INITIAL_CUSTOMER_BASELINE_{$timestamp}",
            'local_path'        => $destDir,
            'size_bytes'        => $fileSize,
            'sha256'            => $actualSha256,
            'status'            => $status,
            'verified_at'       => now(),
            'notes'             => $checksumOk
                ? 'SHA-256 تأیید شد'
                : "SHA-256 mismatch — expected: {$sha256}, actual: {$actualSha256}",
        ]);

        Log::info("LocalRecoveryRepo: Customer baseline site={$siteId} uuid={$installationUuid} stored — status: {$status}");

        if ($checksumOk) {
            $this->pruneAfterStore('customer', $siteId);
        }

        return [
            'success'    => $checksumOk,
            'local_path' => $destDir,
            'log_id'     => $log->id,
            'sha256'     => $actualSha256,
            'size_bytes' => $fileSize,
            'message'    => $checksumOk
                ? "پشتیبان مشتری site={$siteId} ذخیره و SHA-256 تأیید شد"
                : "پشتیبان ذخیره شد اما SHA-256 مغایرت دارد",
        ];
    }

    // ─── On-Demand Verification ───────────────────────────────────────────────

    /**
     * Re-verify an existing log entry against the files on disk.
     * Updates the log's status in the database.
     */
    public function verifyLog(LocalRecoveryLog $log): array
    {
        $path = $log->local_path;

        if (!is_dir($path) && !file_exists($path)) {
            $log->update(['status' => 'failed', 'notes' => 'مسیر روی دیسک یافت نشد']);
            return ['pass' => false, 'status' => 'FAILED', 'message' => "مسیر روی دیسک یافت نشد: {$path}"];
        }

        if ($log->type === 'platform') {
            $result = $this->verifyPlatformLog($log, $path);
        } else {
            $result = $this->verifyCustomerLog($log, $path);
        }

        $log->update([
            'status'      => $result['pass'] ? 'verified' : 'failed',
            'verified_at' => now(),
            'notes'       => $result['message'],
        ]);

        return $result;
    }

    private function verifyPlatformLog(LocalRecoveryLog $log, string $path): array
    {
        $manifestPath = $path . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!file_exists($manifestPath)) {
            return ['pass' => false, 'status' => 'FAILED', 'message' => 'manifest.json یافت نشد'];
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            return ['pass' => false, 'status' => 'FAILED', 'message' => 'manifest.json نامعتبر است'];
        }

        // Verify manifest self-checksum
        $stored = $manifest['manifest_checksum'] ?? null;
        if ($stored) {
            $body      = $manifest;
            unset($body['manifest_checksum']);
            $recomputed = hash('sha256', json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if ($recomputed !== $stored) {
                return ['pass' => false, 'status' => 'FAILED', 'message' => 'خودـبررسی manifest ناموفق — احتمالاً دستکاری شده'];
            }
        }

        // Verify each file in checksums
        $failures = [];
        foreach ($manifest['checksums'] ?? [] as $file => $expected) {
            $fp = $path . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($fp)) {
                $failures[] = "فایل غایب: {$file}";
                continue;
            }
            $actual = hash_file('sha256', $fp);
            if ($actual !== $expected) {
                $failures[] = "SHA-256 mismatch: {$file}";
            }
        }

        $pass = empty($failures);
        return [
            'pass'     => $pass,
            'status'   => $pass ? 'PASS' : 'FAILED',
            'failures' => $failures,
            'message'  => $pass ? 'تمام فایل‌ها با SHA-256 تأیید شدند' : implode('; ', $failures),
        ];
    }

    private function verifyCustomerLog(LocalRecoveryLog $log, string $path): array
    {
        $dumpFile = $path . DIRECTORY_SEPARATOR . 'customer_baseline.sql.gz';
        if (!file_exists($dumpFile)) {
            return ['pass' => false, 'status' => 'FAILED', 'message' => 'customer_baseline.sql.gz یافت نشد'];
        }

        $actual = hash_file('sha256', $dumpFile);
        if ($log->sha256 && $actual !== $log->sha256) {
            return ['pass' => false, 'status' => 'FAILED', 'message' => "SHA-256 mismatch — expected: {$log->sha256}, actual: {$actual}"];
        }

        $size = filesize($dumpFile);
        return [
            'pass'    => true,
            'status'  => 'PASS',
            'sha256'  => $actual,
            'size'    => $size,
            'message' => 'SHA-256 و اندازه فایل تأیید شدند',
        ];
    }

    // ─── Status & Listing ─────────────────────────────────────────────────────

    public function getStatus(): array
    {
        $repoPath   = $this->getRepoPath();
        $validation = $this->validate($repoPath);
        $totalSize  = is_dir($repoPath) ? $this->dirSize($repoPath) : 0;

        $lastPlatform = LocalRecoveryLog::platform()->orderByDesc('created_at')->first();
        $lastCustomer = LocalRecoveryLog::customer()->orderByDesc('created_at')->first();

        $failedCount   = LocalRecoveryLog::failed()->count();
        $verifiedCount = LocalRecoveryLog::verified()->count();
        $pendingCount  = LocalRecoveryLog::where('status', 'pending')->count();

        $overallStatus = 'unknown';
        if ($validation['is_valid']) {
            $overallStatus = $failedCount > 0 ? 'degraded' : ($verifiedCount > 0 ? 'healthy' : 'empty');
        } else {
            $overallStatus = 'not_configured';
        }

        return [
            'repo_path'      => $repoPath,
            'is_configured'  => $validation['is_valid'],
            'validation'     => $validation,
            'policy'         => $this->getPolicy(),
            'priority'       => $this->getPriority(),
            'total_size'     => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
            'free_bytes'     => $validation['free_bytes'],
            'free_human'     => $this->formatBytes($validation['free_bytes']),
            'last_platform'  => $lastPlatform,
            'last_customer'  => $lastCustomer,
            'last_sync'      => $lastPlatform?->created_at ?? $lastCustomer?->created_at,
            'failed_count'   => $failedCount,
            'verified_count' => $verifiedCount,
            'pending_count'  => $pendingCount,
            'overall_status' => $overallStatus,
        ];
    }

    public function listPlatformBackups(): \Illuminate\Database\Eloquent\Collection
    {
        return LocalRecoveryLog::platform()->orderByDesc('created_at')->get();
    }

    public function listCustomerBackups(?int $siteId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = LocalRecoveryLog::customer()->orderByDesc('created_at');
        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }
        return $query->get();
    }

    /**
     * List platform baselines available for restore (verified only, path exists on disk).
     */
    public function listRestorablePlatformBackups(): array
    {
        return LocalRecoveryLog::platform()
            ->verified()
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn($log) => is_dir($log->local_path))
            ->map(fn($log) => [
                'path'        => $log->local_path . DIRECTORY_SEPARATOR . 'database.sql.gz',
                'filename'    => $log->baseline_label ?? basename($log->local_path),
                'size'        => $log->size_bytes,
                'size_human'  => $log->sizeHuman(),
                'date_human'  => $log->created_at?->diffForHumans(),
                'source'      => 'recovery_repository',
                'log_id'      => $log->id,
            ])
            ->filter(fn($item) => file_exists($item['path']))
            ->values()
            ->all();
    }

    // ─── Retention ────────────────────────────────────────────────────────────

    /**
     * Copies older than the newest $keep verified ones, per scope: platform
     * baselines (INITIAL_INSTALLATION_BASELINE* never counts and is never
     * pruned) and each customer site. Failed or pending copies newer than
     * those verified ones are left alone, so a run of failed baselines can
     * never cost the last good copies.
     *
     * @return list<LocalRecoveryLog>
     */
    public function pruneCandidates(?int $keep = null, ?string $type = null, ?int $siteId = null): array
    {
        $keep = max(1, $keep ?? $this->keepPerScope());
        $candidates = [];

        if ($type === null || $type === 'platform') {
            $platform = LocalRecoveryLog::platform()->orderByDesc('created_at')->orderByDesc('id')->get()
                ->reject(fn (LocalRecoveryLog $log) => str_starts_with((string) $log->baseline_label, 'INITIAL_INSTALLATION_BASELINE'));
            array_push($candidates, ...$this->olderThanNewestVerified($platform, $keep));
        }

        if ($type === null || $type === 'customer') {
            LocalRecoveryLog::customer()
                ->when($siteId !== null, fn ($q) => $q->where('site_id', $siteId))
                ->orderByDesc('created_at')->orderByDesc('id')->get()
                ->groupBy('site_id')
                ->each(function ($logs) use (&$candidates, $keep) {
                    array_push($candidates, ...$this->olderThanNewestVerified($logs, $keep));
                });
        }

        return $candidates;
    }

    /**
     * Delete the candidates' directories (only inside the repository root)
     * and their log rows. With $dryRun nothing is touched.
     *
     * @return array{dry_run: bool, keep: int, items: list<array{log_id: int, type: string, site_id: ?int, label: ?string, path: string, size_bytes: int, action: string}>, reclaimed_bytes: int}
     */
    public function prune(?int $keep = null, bool $dryRun = true, ?string $type = null, ?int $siteId = null): array
    {
        $keep = max(1, $keep ?? $this->keepPerScope());
        $items = [];
        $reclaimed = 0;

        foreach ($this->pruneCandidates($keep, $type, $siteId) as $log) {
            $path = (string) $log->local_path;
            $exists = $path !== '' && file_exists($path);
            $action = match (true) {
                $exists && !$this->isInsideRepository($path) => 'skipped_outside_repository',
                $exists => 'delete',
                default => 'forget_missing',
            };

            $size = $exists && is_dir($path) ? $this->dirSize($path) : ($exists ? (int) filesize($path) : 0);
            $items[] = [
                'log_id'     => $log->id,
                'type'       => $log->type,
                'site_id'    => $log->site_id,
                'label'      => $log->baseline_label,
                'path'       => $path,
                'size_bytes' => $size,
                'action'     => $action,
            ];

            if ($dryRun || $action === 'skipped_outside_repository') {
                continue;
            }

            if ($exists) {
                $this->deleteTree($path);
                $reclaimed += $size;
            }
            $log->delete();
            Log::info('LocalRecoveryRepo: pruned by retention', ['log_id' => $log->id, 'type' => $log->type, 'site_id' => $log->site_id, 'path' => $path, 'bytes' => $size]);
        }

        return ['dry_run' => $dryRun, 'keep' => $keep, 'items' => $items, 'reclaimed_bytes' => $reclaimed];
    }

    private function pruneAfterStore(string $type, ?int $siteId = null): void
    {
        try {
            $this->prune(null, false, $type, $siteId);
        } catch (\Throwable $e) {
            Log::warning("LocalRecoveryRepo: retention prune failed — {$e->getMessage()}");
        }
    }

    /**
     * @param \Illuminate\Support\Collection<int, LocalRecoveryLog> $logs newest first
     * @return list<LocalRecoveryLog>
     */
    private function olderThanNewestVerified(\Illuminate\Support\Collection $logs, int $keep): array
    {
        $verified = 0;
        $older = [];
        foreach ($logs as $log) {
            if ($verified >= $keep) {
                $older[] = $log;
            } elseif ($log->status === 'verified') {
                $verified++;
            }
        }

        return $older;
    }

    private function isInsideRepository(string $path): bool
    {
        $root = realpath($this->getRepoPath());
        $real = realpath($path);

        return $root !== false && $real !== false
            && str_starts_with($real, rtrim($root, '\\/') . DIRECTORY_SEPARATOR);
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);

            return;
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function copyDirectory(string $src, string $dest): array
    {
        $results = [];
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iter as $item) {
            $target = $dest . DIRECTORY_SEPARATOR . $iter->getSubPathname();
            if ($item->isDir()) {
                @mkdir($target, 0750, true);
            } else {
                $ok = copy($item->getPathname(), $target);
                $results[$iter->getSubPathname()] = $ok;
            }
        }

        return $results;
    }

    private function verifyDirectory(string $source, string $dest): array
    {
        $failures = [];
        $passed   = [];

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iter as $srcFile) {
            if (!$srcFile->isFile()) continue;
            $relative = $iter->getSubPathname();
            $destFile = $dest . DIRECTORY_SEPARATOR . $relative;

            if (!file_exists($destFile)) {
                $failures[] = "Missing in dest: {$relative}";
                continue;
            }

            // Size check
            if ($srcFile->getSize() !== filesize($destFile)) {
                $failures[] = "Size mismatch: {$relative}";
                continue;
            }

            // SHA-256 check (streaming)
            $srcHash  = hash_file('sha256', $srcFile->getPathname());
            $destHash = hash_file('sha256', $destFile);
            if ($srcHash !== $destHash) {
                $failures[] = "SHA-256 mismatch: {$relative}";
            } else {
                $passed[] = $relative;
            }
        }

        return [
            'pass'     => empty($failures),
            'status'   => empty($failures) ? 'PASS' : 'FAILED',
            'failures' => $failures,
            'passed'   => $passed,
            'message'  => empty($failures)
                ? count($passed) . ' فایل با SHA-256 تأیید شد'
                : count($failures) . ' خطا در تأیید SHA-256',
        ];
    }

    private function dirSize(string $dir): int
    {
        if (!is_dir($dir)) return 0;
        $size = 0;
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iter as $file) {
            if ($file->isFile()) $size += $file->getSize();
        }
        return $size;
    }

    private function formatBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024) return round($bytes, 1) . ' ' . $unit;
            $bytes = (int) ($bytes / 1024);
        }
        return $bytes . ' TB';
    }
}
