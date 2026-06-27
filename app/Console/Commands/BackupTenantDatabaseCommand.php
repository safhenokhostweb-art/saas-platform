<?php
namespace App\Console\Commands;

use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Site;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupTenantDatabaseCommand extends Command
{
    protected $signature = 'db:backup-tenants
                            {--site=  : Only backup this site_id (optional)}
                            {--retention=7 : Number of backup files to keep per tenant}
                            {--dry-run : Show what would be backed up without running}';

    protected $description = 'Backup all active tenant databases to local storage and ArvanCloud';

    public function handle(ArvanBackupService $arvan, SlackAlertService $slack): int
    {
        $dryRun    = $this->option('dry-run');
        $retention = max(1, (int) $this->option('retention'));
        $siteOpt   = $this->option('site');

        $host     = config('database.connections.mysql.host');
        $port     = config('database.connections.mysql.port', 3306);
        $user     = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $query = Site::where(fn($q) =>
            $q->where('lifecycle_status', Site::LIFECYCLE_ACTIVE)
              ->orWhereNull('lifecycle_status')
        );

        if ($siteOpt !== null) {
            $query->where('id', (int) $siteOpt);
        }

        $sites = $query->get(['id', 'title', 'domain']);

        if ($sites->isEmpty()) {
            $this->info('No active sites found — nothing to backup.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[dry-run] Would backup {$sites->count()} tenant database(s)");
            foreach ($sites as $site) {
                $this->line("  site_id={$site->id}: {$site->title} ({$site->domain})");
            }
            return self::SUCCESS;
        }

        $customerDb = config('database.connections.mysql_customer.database', 'customer_site');
        $backupDir  = storage_path('app/backups/tenants');

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0750, true);
        }

        $envPwd    = $password !== '' ? 'MYSQL_PWD=' . escapeshellarg($password) . ' ' : '';
        $connFlags = sprintf(
            '-h %s -P %s -u %s',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($user)
        );

        $successCount = 0;
        $failCount    = 0;

        foreach ($sites as $site) {
            $siteId   = $site->id;
            $filename = "db_backup_{$siteId}_" . now()->format('Y-m-d_His') . '.sql.gz';
            $fullPath = $backupDir . DIRECTORY_SEPARATOR . $filename;

            $run       = InfraBackupRun::startRun('database', $siteId);
            $startedAt = microtime(true);

            $cmd = sprintf(
                '%smysqldump --single-transaction --quick --lock-tables=false --where=%s -h %s -P %s -u %s %s | gzip > %s 2>&1',
                $envPwd,
                escapeshellarg("site_id={$siteId}"),
                escapeshellarg($host),
                escapeshellarg((string) $port),
                escapeshellarg($user),
                escapeshellarg($customerDb),
                escapeshellarg($fullPath)
            );

            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || !file_exists($fullPath) || filesize($fullPath) < 32) {
                $msg = "Tenant {$siteId} backup failed (exit {$exitCode})";
                $this->error($msg);
                $run->endFailed($msg);
                $failCount++;
                Log::error('db:backup-tenants failed', ['site_id' => $siteId, 'error' => $msg]);
                continue;
            }

            $sizeMb   = round(filesize($fullPath) / 1024 / 1024, 2);
            $duration = (int) (microtime(true) - $startedAt);
            $run->endSuccess($fullPath, $sizeMb, $duration);
            $successCount++;

            $this->line("Backed up site {$siteId} ({$sizeMb} MB)");

            // Upload to Arvan
            try {
                $remotePath = $arvan->tenantDbPath($siteId, $filename);
                $result     = $arvan->upload($fullPath, $remotePath);

                if ($result['success']) {
                    $this->line("  Arvan upload OK: {$remotePath}");
                } else {
                    $this->warn("  Arvan upload failed for site {$siteId}: {$result['message']}");
                    Log::warning('db:backup-tenants arvan failed', ['site_id' => $siteId, 'error' => $result['message']]);
                }

                // Prune old Arvan backups for this tenant
                $prefix = "customer-sites/tenant-{$siteId}/database/";
                $pruned = $arvan->prune($prefix, $retention);
                if ($pruned['pruned'] > 0) {
                    $this->line("  Arvan pruned {$pruned['pruned']} old backup(s) for site {$siteId}");
                }

            } catch (\Throwable $e) {
                $this->warn("  Arvan exception for site {$siteId}: " . $e->getMessage());
                Log::error('db:backup-tenants arvan exception', ['site_id' => $siteId, 'error' => $e->getMessage()]);
            } finally {
                // Always remove local file — Arvan is the durable copy
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }

        $this->info("Tenant backup complete: {$successCount} success, {$failCount} failed");

        if ($failCount > 0) {
            $slack->warning(
                'Tenant Backup Partial Failure',
                "{$failCount} tenant backup(s) failed out of " . $sites->count() . " total",
                ['success' => $successCount, 'failed' => $failCount]
            );
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
