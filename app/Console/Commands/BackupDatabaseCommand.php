<?php
namespace App\Console\Commands;

use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Infrastructure\InfraSchedulerRun;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupDatabaseCommand extends Command
{
    /** Name the run is recorded under (InfraSchedulerRun::recentSummary()). */
    public const SCHEDULER_RUN_NAME = 'db:backup';

    protected $signature = 'db:backup
                            {--retention=2 : Local backup files to keep on this server}
                            {--remote-retention=7 : Offsite (Arvan) backup files to keep}
                            {--dry-run     : Show what would be backed up without running}';

    protected $description = 'Create a gzip-compressed MySQL backup and prune old backups';

    public function handle(): int
    {
        $dryRun    = $this->option('dry-run');
        $retention = max(1, (int) $this->option('retention'));
        // Offsite copies are the durable ones; they are no longer tied to
        // the (smaller) local retention.
        $remoteRetention = max(1, (int) $this->option('remote-retention'));

        $db       = config('database.connections.mysql.database');
        $host     = config('database.connections.mysql.host');
        $port     = config('database.connections.mysql.port', 3306);
        $user     = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $filename  = 'db_backup_' . now()->format('Y-m-d_His') . '.sql.gz';
        $backupDir = storage_path('app/backups');
        $fullPath  = $backupDir . DIRECTORY_SEPARATOR . $filename;

        if ($dryRun) {
            $this->info("[dry-run] Would write backup to: {$fullPath}");
            return self::SUCCESS;
        }

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0750, true);
        }

        $backupRun = InfraBackupRun::startRun('database');
        $schedRun  = InfraSchedulerRun::startRun(self::SCHEDULER_RUN_NAME);
        $startedAt = microtime(true);

        $envPwd = $password !== '' ? 'MYSQL_PWD=' . escapeshellarg($password) . ' ' : '';
        $cmd = sprintf(
            '%smysqldump --single-transaction --quick --lock-tables=false -h %s -P %s -u %s %s | gzip > %s 2>&1',
            $envPwd,
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($user),
            escapeshellarg($db),
            escapeshellarg($fullPath)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($fullPath) || filesize($fullPath) < 32) {
            $msg = "Backup failed (exit {$exitCode}). Output: " . implode(' ', $output);
            $this->error($msg);
            $backupRun->endFailed($msg);
            $schedRun->endFailed($msg);
            app(SlackAlertService::class)->critical('Database Backup Failed', $msg, ['database' => $db]);
            return self::FAILURE;
        }

        $sizeMb   = round(filesize($fullPath) / 1024 / 1024, 2);
        $duration = (int) (microtime(true) - $startedAt);

        $this->info("Backup created: {$filename} ({$sizeMb} MB)");
        $backupRun->endSuccess($fullPath, $sizeMb, $duration);
        $schedRun->endSuccess("Backup: {$filename} ({$sizeMb} MB)");

        // ARVAN: Upload backup to offsite storage
        try {
            $arvan = app(ArvanBackupService::class);
            $remotePath = $arvan->platformDbPath($filename);
            $result = $arvan->upload($fullPath, $remotePath);

            if ($result['success']) {
                $this->info("Arvan offsite backup: {$result['message']}");
                Log::info("db:backup arvan success", ['filename' => $filename, 'size' => $sizeMb]);
            } else {
                $this->warn("Arvan offsite backup failed: {$result['message']}");
                Log::warning("db:backup arvan failed", ['filename' => $filename, 'error' => $result['message']]);
                app(SlackAlertService::class)->warning(
                    'Arvan Offsite Backup Failed',
                    "Database backup created locally but offsite upload failed: {$result['message']}",
                    ['filename' => $filename, 'size_mb' => $sizeMb]
                );
            }
        } catch (\Throwable $e) {
            $this->warn("Arvan upload exception: " . $e->getMessage());
            Log::error("db:backup arvan exception", ['error' => $e->getMessage()]);
        }

        $this->pruneOldBackups($backupDir, $retention);

        // Prune Arvan remote files to their own retention
        try {
            $arvan  = app(ArvanBackupService::class);
            $pruned = $arvan->prune($arvan->platformDbPath(''), $remoteRetention);
            if ($pruned['pruned'] > 0) {
                $this->line("Arvan pruned {$pruned['pruned']} old backup(s), kept {$pruned['kept']}");
                Log::info("db:backup arvan prune", $pruned);
            }
        } catch (\Throwable $e) {
            $this->warn("Arvan prune failed: " . $e->getMessage());
            Log::warning("db:backup arvan prune failed", ['error' => $e->getMessage()]);
        }

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $dir, int $keep): void
    {
        $files = glob($dir . DIRECTORY_SEPARATOR . 'db_backup_*.sql.gz');
        if (! $files || count($files) <= $keep) {
            return;
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $toDelete = array_slice($files, $keep);

        foreach ($toDelete as $file) {
            @unlink($file);
            $this->line('Pruned: ' . basename($file));
        }
    }
}
