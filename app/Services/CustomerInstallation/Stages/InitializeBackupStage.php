<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Env\RemoteEnvFile;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Installer\LocalRecoveryRepository;
use Illuminate\Support\Facades\Log;

class InitializeBackupStage extends AbstractStage
{
    public function __construct(
        private readonly ArvanBackupService      $arvan,
        private readonly LocalRecoveryRepository $localRepo,
    ) {}

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

            // Step 1: Write backup config into .env (upserts: a retried stage
            // used to append the same keys again with `echo >>`).
            $envFile = $webRoot . '/.env';
            $ctx->connection->execute(RemoteEnvFile::set($envFile, 'BACKUP_ENABLED', 'true'));
            $ctx->connection->execute(RemoteEnvFile::set($envFile, 'BACKUP_RETENTION', '30'));

            // Step 2: Create DB dump on the remote server. The credentials are
            // decoded the way Laravel reads them; `cut -d= -f2 | tr -d '"'`
            // broke on a `=` in the value and ignored dotenv quoting/escapes.
            $db = RemoteEnvFile::decode((string) $ctx->connection->execute(
                RemoteEnvFile::read($envFile, ['DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'])
            )->stdout);
            $dbName    = (string) ($db['DB_DATABASE'] ?? '');
            $dbUser    = (string) ($db['DB_USERNAME'] ?? '');
            $dbPassRaw = (string) ($db['DB_PASSWORD'] ?? '');

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

                            // Step 5a: Copy to Local Recovery Repository (non-fatal)
                            $localRepoResult = null;
                            if ($this->localRepo->shouldUseLocal()) {
                                try {
                                    $localRepoResult = $this->localRepo->storeCustomerBaseline(
                                        siteId:            (int) $siteId,
                                        installationUuid:  $ctx->installation->uuid ?? (string) $ctx->installation->id,
                                        sqlLocalPath:      $localPath,
                                        sha256:            $checksum,
                                    );
                                } catch (\Throwable $e) {
                                    Log::warning("CIE InitializeBackup: Local repo copy failed — {$e->getMessage()}");
                                }
                            }

                            // Step 5b: Upload to Arvan (non-fatal when policy includes local)
                            $arvanKey  = "customers/site-{$siteId}/baselines/INITIAL_CUSTOMER_BASELINE_{$timestamp}.sql.gz";
                            $uploaded  = $this->localRepo->shouldUseArvan()
                                ? $this->arvan->upload($localPath, $arvanKey)
                                : ['success' => false, 'message' => 'Arvan disabled by policy'];

                            // Policy: abort only when BOTH fail
                            $arvanOk     = $uploaded['success'] ?? false;
                            $localRepoOk = $localRepoResult['success'] ?? false;

                            if ($arvanOk || $localRepoOk) {
                                $baselineCreated = true;

                                $ctx->installation->update([
                                    'initial_baseline_at'         => now(),
                                    'initial_baseline_arvan_key'  => $arvanOk ? $arvanKey : null,
                                    'initial_baseline_sha256'     => $checksum,
                                ]);
                            } else {
                                Log::warning("CIE InitializeBackup: Both Arvan and Local repo failed for site={$siteId}");
                            }
                        } catch (\Throwable $e) {
                            Log::warning("CIE InitializeBackup: baseline creation failed — {$e->getMessage()}");
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
