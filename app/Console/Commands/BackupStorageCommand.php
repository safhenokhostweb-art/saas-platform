<?php
namespace App\Console\Commands;

use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupStorageCommand extends Command
{
    protected $signature = 'storage:backup
                            {--dry-run : Show what would be backed up without running}';

    protected $description = 'Create a compressed snapshot of platform public storage and upload to ArvanCloud';

    public function handle(): int
    {
        $dryRun    = $this->option('dry-run');
        $sourceDir = storage_path('app/public');
        $filename  = 'storage_backup_' . now()->format('Y-m-d_His') . '.tar.gz';
        $tempPath  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        if ($dryRun) {
            $this->info("[dry-run] Would backup {$sourceDir} to Arvan as {$filename}");
            return self::SUCCESS;
        }

        if (! is_dir($sourceDir)) {
            $this->warn("Source directory does not exist: {$sourceDir} — nothing to backup.");
            return self::SUCCESS;
        }

        $startedAt = microtime(true);

        $cmd = sprintf(
            'tar -czf %s -C %s . 2>&1',
            escapeshellarg($tempPath),
            escapeshellarg($sourceDir)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($tempPath) || filesize($tempPath) < 32) {
            $msg = "Storage backup failed (exit {$exitCode}). Output: " . implode(' ', $output);
            $this->error($msg);
            Log::error('storage:backup failed', ['error' => $msg]);
            app(SlackAlertService::class)->critical(
                'Platform Storage Backup Failed',
                $msg,
                ['source' => $sourceDir]
            );
            return self::FAILURE;
        }

        $sizeMb   = round(filesize($tempPath) / 1024 / 1024, 2);
        $duration = (int) (microtime(true) - $startedAt);

        $this->info("Storage snapshot created: {$filename} ({$sizeMb} MB, {$duration}s)");
        Log::info('storage:backup snapshot created', ['filename' => $filename, 'size_mb' => $sizeMb]);

        // Upload to ArvanCloud using platformStoragePath()
        try {
            $arvan      = app(ArvanBackupService::class);
            $remotePath = $arvan->platformStoragePath($filename);
            $result     = $arvan->upload($tempPath, $remotePath);

            if ($result['success']) {
                $this->info("Arvan offsite storage backup: {$result['message']}");
                Log::info('storage:backup arvan success', [
                    'filename'    => $filename,
                    'remote_path' => $remotePath,
                    'size_mb'     => $sizeMb,
                ]);
            } else {
                $this->warn("Arvan offsite storage backup failed: {$result['message']}");
                Log::warning('storage:backup arvan failed', [
                    'filename' => $filename,
                    'error'    => $result['message'],
                ]);
                app(SlackAlertService::class)->warning(
                    'Arvan Storage Backup Failed',
                    "Storage snapshot created locally but offsite upload failed: {$result['message']}",
                    ['filename' => $filename, 'size_mb' => $sizeMb]
                );
            }
        } catch (\Throwable $e) {
            $this->warn("Arvan upload exception: " . $e->getMessage());
            Log::error('storage:backup arvan exception', ['error' => $e->getMessage()]);
        } finally {
            // Always remove the temporary tar.gz — original files remain on disk
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }

        return self::SUCCESS;
    }
}
