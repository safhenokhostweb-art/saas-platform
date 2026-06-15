<?php
namespace App\Console\Commands;

use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Infrastructure\InfraSchedulerRun;
use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Console\Command;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup
                            {--retention=7 : Number of daily backup files to keep}
                            {--dry-run     : Show what would be backed up without running}';

    protected $description = 'Create a gzip-compressed MySQL backup and prune old backups';

    public function handle(): int
    {
        $dryRun    = $this->option('dry-run');
        $retention = max(1, (int) $this->option('retention'));

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
        $schedRun  = InfraSchedulerRun::startRun('db:backup --retention=7');
        $startedAt = microtime(true);

        $passwordArg = $password !== '' ? '-p' . escapeshellarg($password) : '';
        $cmd = sprintf(
            'mysqldump --single-transaction --quick --lock-tables=false -h %s -P %s -u %s %s %s | gzip > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($user),
            $passwordArg,
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

        $this->pruneOldBackups($backupDir, $retention);

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
