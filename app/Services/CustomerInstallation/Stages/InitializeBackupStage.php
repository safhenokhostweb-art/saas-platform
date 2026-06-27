<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\Infrastructure\ArvanBackupService;
use Illuminate\Support\Facades\Log;

class InitializeBackupStage extends AbstractStage
{
    public function __construct(private readonly ArvanBackupService $arvan) {}

    public function name(): string { return 'INITIALIZE_BACKUP'; }
    public function label(): string { return 'راه‌اندازی پشتیبان‌گیری اولیه'; }
    public function estimatedSeconds(): int { return 60; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $webRoot      = $ctx->webRoot();
            $safeWebRoot  = escapeshellarg($webRoot);
            $policy       = $ctx->request->backupPolicy;
            $siteId       = $ctx->installation->site_id ?? $ctx->installation->id;
            $timestamp    = now()->format('Ymd_His');
            $dumpFile     = "/tmp/cie_baseline_{$siteId}_{$timestamp}.sql.gz";

            // Step 1: Write backup config into .env
            $envPath = escapeshellarg($webRoot . '/.env');
            $ctx->connection->execute("echo 'BACKUP_ENABLED=true' >> {$envPath}");
            $ctx->connection->execute("echo 'BACKUP_RETENTION=30' >> {$envPath}");

            // Step 2: Create DB dump on the remote server
            $dbNameResult = $ctx->connection->execute(
                "grep '^DB_DATABASE=' {$envPath} 2>/dev/null | cut -d= -f2 | tr -d '\"'"
            );
            $dbName    = trim($dbNameResult->stdout);
            $dbUser    = trim($ctx->connection->execute("grep '^DB_USERNAME=' {$envPath} 2>/dev/null | cut -d= -f2 | tr -d '\"'")->stdout);
            $dbPassRaw = trim($ctx->connection->execute("grep '^DB_PASSWORD=' {$envPath} 2>/dev/null | cut -d= -f2 | tr -d '\"'")->stdout);

            $baselineCreated  = false;
            $arvanKey         = null;
            $checksum         = null;

            if (!empty($dbName) && !empty($dbUser)) {
                $dumpResult = $ctx->connection->execute(
                    "MYSQL_PWD=" . escapeshellarg($dbPassRaw) . " mysqldump -u " . escapeshellarg($dbUser)
                    . " " . escapeshellarg($dbName)
                    . " 2>/dev/null | gzip > " . escapeshellarg($dumpFile)
                    . " && echo 'dump-ok' || echo 'dump-failed'"
                );

                if (str_contains($dumpResult->stdout, 'dump-ok')) {
                    // Verify dump is non-empty
                    $sizeResult = $ctx->connection->execute("stat -c%s " . escapeshellarg($dumpFile) . " 2>/dev/null || echo 0");
                    $dumpSize   = (int) trim($sizeResult->stdout);

                    if ($dumpSize > 100) {
                        // Step 3: Download dump to platform local temp
                        $localPath = storage_path("app/install-packages/baseline_{$siteId}_{$timestamp}.sql.gz");

                        try {
                            $ctx->connection->download($dumpFile, $localPath);

                            // Step 4: Compute checksum
                            $checksum = hash_file('sha256', $localPath);

                            // Step 5: Upload to Arvan
                            $arvanKey  = "customers/site-{$siteId}/baselines/INITIAL_CUSTOMER_BASELINE_{$timestamp}.sql.gz";
                            $uploaded  = $this->arvan->upload($localPath, $arvanKey);

                            if ($uploaded['success'] ?? false) {
                                $baselineCreated = true;

                                // Persist on installation record
                                $ctx->installation->update([
                                    'initial_baseline_at'         => now(),
                                    'initial_baseline_arvan_key'  => $arvanKey,
                                    'initial_baseline_sha256'     => $checksum,
                                ]);
                            }
                        } catch (\Throwable $e) {
                            Log::warning("CIE InitializeBackup: Arvan upload failed — {$e->getMessage()}");
                        } finally {
                            @unlink($localPath);
                        }

                        // Cleanup dump on remote
                        $ctx->connection->execute('rm -f ' . escapeshellarg($dumpFile));
                    }
                }
            }

            // Step 6: Trigger the customer-site's built-in backup command (non-blocking)
            $artisanBackup = $ctx->connection->execute(
                "cd {$safeWebRoot} && php artisan db:backup 2>&1 | head -3 || echo 'backup-queued'"
            );

            return StageResult::success($this->name(), [
                'backup_enabled'        => true,
                'backup_policy'         => $policy,
                'initial_baseline'      => $baselineCreated,
                'baseline_arvan_key'    => $arvanKey,
                'baseline_sha256'       => $checksum,
                'artisan_backup_output' => trim($artisanBackup->stdout),
            ], [], $this->elapsed());
        });
    }
}
