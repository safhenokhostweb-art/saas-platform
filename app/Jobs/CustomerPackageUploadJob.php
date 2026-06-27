<?php

namespace App\Jobs;

use App\Models\CustomerInstallation;
use App\Services\CustomerInstallation\CustomerReleasePackageService;
use App\Services\CustomerInstallation\RemoteConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CustomerPackageUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int    $installationId,
        public readonly string $localPath,
        public readonly string $remotePath,
        public readonly string $version,
    ) {}

    public function handle(
        CustomerReleasePackageService $packages,
        RemoteConnectionService $connectionService
    ): void {
        $installation = CustomerInstallation::with('remoteServer')->find($this->installationId);

        if (!$installation || !$installation->remoteServer) {
            Log::error("CIE Upload: Installation or server not found");
            return;
        }

        $connection = $connectionService->buildDriver($installation->remoteServer);
        $connection->connect();

        try {
            $packages->uploadToRemote(
                $this->localPath,
                $connection,
                $this->remotePath,
                function (int $pct) {
                    try {
                        Redis::rpush("install_progress_{$this->installationId}", json_encode([
                            'stage'        => 'UPLOAD_PACKAGE',
                            'status'       => 'running',
                            'progress_pct' => $pct,
                            'message'      => "آپلود بسته: {$pct}٪",
                            'timestamp'    => now()->toIso8601String(),
                        ]));
                    } catch (\Throwable) {}
                }
            );

            Log::info("CIE Upload: Package {$this->version} uploaded to {$this->remotePath}");

        } finally {
            $connection->disconnect();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("CIE Upload: Package upload job failed", [
            'installation_id' => $this->installationId,
            'version'         => $this->version,
            'error'           => $exception->getMessage(),
        ]);

        @unlink($this->localPath);
    }
}
