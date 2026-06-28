<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Contracts\CustomerPackageInterface;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UploadPackageStage extends AbstractStage
{
    public function __construct(private readonly CustomerPackageInterface $packages) {}

    public function name(): string { return 'UPLOAD_PACKAGE'; }
    public function label(): string { return 'آپلود بسته نصب'; }
    public function estimatedSeconds(): int { return 300; }
    public function canRollback(): bool { return true; }

    /**
     * Pre-snapshot: the remote /tmp path is deterministic, so we can record it
     * before execution. This ensures rollback can clean the file even if the
     * upload fails mid-way.
     */
    public function buildPreSnapshot(InstallationContext $ctx): array
    {
        try {
            $version = $this->packages->latestVersion();
            return ['remote_path' => $this->remotePathFor($ctx, $version)];
        } catch (\Throwable) {
            return [];
        }
    }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $installId = $ctx->installation->id;
            $t = [];

            // Phase 1: Resolve version + manifest
            $t['phase1_start'] = microtime(true);
            $version  = $this->packages->latestVersion();
            $manifest = $this->packages->getManifest($version);
            $t['phase1_end'] = microtime(true);

            $remotePath = $this->remotePathFor($ctx, $version);
            $sizeHuman  = $this->formatBytes($manifest->sizeBytes ?? 0);

            $this->logPhase($installId, 'UPLOAD_PACKAGE', "Phase 1 — manifest resolved: v{$version} ({$sizeHuman})", $t['phase1_start'], $t['phase1_end']);

            // Phase 2: Download package locally (local copy or Arvan)
            $t['phase2_start'] = microtime(true);
            $localPath = $this->packages->downloadLocally($version);
            $t['phase2_end'] = microtime(true);

            $this->logPhase($installId, 'UPLOAD_PACKAGE', 'Phase 2 — local package ready: ' . basename($localPath), $t['phase2_start'], $t['phase2_end']);

            // Phase 3: Local SHA-256 verification
            $t['phase3_start'] = microtime(true);
            $localVerified = $this->packages->verify($localPath, $manifest->sha256);
            $t['phase3_end'] = microtime(true);

            if (!$localVerified) {
                $this->packages->cleanup($localPath);
                $this->logPhase($installId, 'UPLOAD_PACKAGE', 'Phase 3 — LOCAL SHA-256 MISMATCH', $t['phase3_start'], $t['phase3_end']);
                return StageResult::failure($this->name(), 'Local SHA-256 verification failed — package file is corrupted', $this->elapsed());
            }

            $this->logPhase($installId, 'UPLOAD_PACKAGE', 'Phase 3 — local SHA-256 verified', $t['phase3_start'], $t['phase3_end']);

            // Phase 4: SFTP upload to remote server
            $t['phase4_start'] = microtime(true);

            try {
                $this->packages->uploadToRemote(
                    $localPath,
                    $ctx->connection,
                    $remotePath,
                    function (int $pct, int $uploaded, int $total) use ($installId, $t) {
                        $elapsed = round((microtime(true) - $t['phase4_start']), 1);
                        $kbps    = $elapsed > 0 ? round($uploaded / 1024 / $elapsed) : 0;
                        try {
                            Redis::rpush("install_progress_{$installId}", json_encode([
                                'stage'        => 'UPLOAD_PACKAGE',
                                'status'       => 'running',
                                'progress_pct' => $pct,
                                'message'      => "آپلود بسته: {$pct}٪ — {$kbps} KB/s",
                                'timestamp'    => now()->toIso8601String(),
                            ]));
                        } catch (\Throwable) {}
                    }
                );
            } catch (\Throwable $e) {
                // Upload failed — the driver already cleaned the partial remote file.
                // Clean local temp and return failure with full diagnostic detail.
                $this->packages->cleanup($localPath);
                $t['phase4_end'] = microtime(true);
                $uploadSecs = round($t['phase4_end'] - $t['phase4_start'], 2);

                $this->logPhase($installId, 'UPLOAD_PACKAGE', "Phase 4 — UPLOAD FAILED after {$uploadSecs}s: " . $e->getMessage(), $t['phase4_start'], $t['phase4_end']);

                // Belt-and-suspenders: clean remote via SSH in case SFTP driver couldn't.
                try {
                    $ctx->connection->execute('rm -f ' . escapeshellarg($remotePath));
                } catch (\Throwable) {}

                return StageResult::failure(
                    $this->name(),
                    "Upload failed after {$uploadSecs}s — " . $e->getMessage(),
                    $this->elapsed()
                );
            }

            $t['phase4_end'] = microtime(true);
            $uploadSecs = round($t['phase4_end'] - $t['phase4_start'], 2);
            $actualSize = filesize($localPath) ?: $manifest->sizeBytes ?? 0;
            $actualKbps = $uploadSecs > 0 ? round($actualSize / 1024 / $uploadSecs) : 0;

            $this->logPhase($installId, 'UPLOAD_PACKAGE', "Phase 4 — upload complete in {$uploadSecs}s ({$actualKbps} KB/s)", $t['phase4_start'], $t['phase4_end']);

            // Phase 5: Verify remote file size via SSH stat
            $t['phase5_start'] = microtime(true);
            $statResult = $ctx->connection->execute('stat -c%s ' . escapeshellarg($remotePath) . ' 2>/dev/null || echo 0');
            $remoteSize = (int) trim($statResult->stdout);
            $t['phase5_end'] = microtime(true);

            $this->logPhase($installId, 'UPLOAD_PACKAGE', "Phase 5 — remote size check: {$remoteSize} bytes (expected ~{$manifest->sizeBytes})", $t['phase5_start'], $t['phase5_end']);

            if ($remoteSize < 1024) {
                $this->packages->cleanup($localPath);
                $ctx->connection->execute('rm -f ' . escapeshellarg($remotePath));
                return StageResult::failure(
                    $this->name(),
                    "Remote file size {$remoteSize} bytes is suspiciously small — upload may have silently failed",
                    $this->elapsed()
                );
            }

            // Cleanup local temp
            $this->packages->cleanup($localPath);

            // Persist version + sha256 on installation record
            $ctx->installation->update([
                'package_version' => $version,
                'package_sha256'  => $manifest->sha256,
            ]);

            return StageResult::success($this->name(), [
                'version'       => $version,
                'remote_path'   => $remotePath,
                'sha256'        => $manifest->sha256,
                'size_bytes'    => $manifest->sizeBytes,
                'upload_secs'   => $uploadSecs,
                'upload_kbps'   => $actualKbps,
                'timing'        => [
                    'manifest_ms' => $this->msElapsed($t['phase1_start'], $t['phase1_end']),
                    'download_ms' => $this->msElapsed($t['phase2_start'], $t['phase2_end']),
                    'verify_ms'   => $this->msElapsed($t['phase3_start'], $t['phase3_end']),
                    'upload_ms'   => $this->msElapsed($t['phase4_start'], $t['phase4_end']),
                    'stat_ms'     => $this->msElapsed($t['phase5_start'], $t['phase5_end']),
                ],
            ], ['remote_path' => $remotePath], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['remote_path'])) {
            $ctx->connection->execute('rm -f ' . escapeshellarg($snapshot['remote_path']));
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function remotePathFor(InstallationContext $ctx, string $version): string
    {
        return '/tmp/customer_install_' . $ctx->installation->id . '_' . $version . '.zip';
    }

    private function logPhase(int $installId, string $stage, string $message, float $start, float $end): void
    {
        $ms = (int) round(($end - $start) * 1000);
        Log::info("CIE {$stage}: {$message} [{$ms}ms]", ['installation_id' => $installId]);
    }

    private function msElapsed(float $start, float $end): int
    {
        return (int) round(($end - $start) * 1000);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}
