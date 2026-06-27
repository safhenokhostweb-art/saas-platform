<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Contracts\CustomerPackageInterface;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use Illuminate\Support\Facades\Redis;

class UploadPackageStage extends AbstractStage
{
    public function __construct(private readonly CustomerPackageInterface $packages) {}

    public function name(): string { return 'UPLOAD_PACKAGE'; }
    public function label(): string { return 'آپلود بسته نصب'; }
    public function estimatedSeconds(): int { return 120; }
    public function canRollback(): bool { return true; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $version = $this->packages->latestVersion();
            $manifest = $this->packages->getManifest($version);

            $localPath = $this->packages->downloadLocally($version);

            if (!$this->packages->verify($localPath, $manifest->sha256)) {
                $this->packages->cleanup($localPath);
                return StageResult::failure($this->name(), 'Local SHA-256 verification failed', $this->elapsed());
            }

            $remotePath = '/tmp/customer_install_' . $ctx->installation->id . '_' . $version . '.zip';
            $installId = $ctx->installation->id;

            $this->packages->uploadToRemote(
                $localPath,
                $ctx->connection,
                $remotePath,
                function (int $pct, int $uploaded, int $total) use ($installId) {
                    try {
                        Redis::rpush("install_progress_{$installId}", json_encode([
                            'stage'    => 'UPLOAD_PACKAGE',
                            'status'   => 'running',
                            'progress_pct' => $pct,
                            'message'  => "آپلود بسته: {$pct}٪",
                            'timestamp'=> now()->toIso8601String(),
                        ]));
                    } catch (\Throwable) {}
                }
            );

            $this->packages->cleanup($localPath);

            $ctx->installation->update([
                'package_version' => $version,
                'package_sha256'  => $manifest->sha256,
            ]);

            return StageResult::success($this->name(), [
                'version'     => $version,
                'remote_path' => $remotePath,
                'sha256'      => $manifest->sha256,
                'size_bytes'  => $manifest->sizeBytes,
            ], ['remote_path' => $remotePath], $this->elapsed());
        });
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        if (!empty($snapshot['remote_path'])) {
            $ctx->connection->execute('rm -f ' . escapeshellarg($snapshot['remote_path']));
        }
    }
}
