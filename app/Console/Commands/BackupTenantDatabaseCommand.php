<?php
namespace App\Console\Commands;

use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Site;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Infrastructure\SlackAlertService;
use App\Services\Infrastructure\TenantScopedTableResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupTenantDatabaseCommand extends Command
{
    protected $signature = 'db:backup-tenants
                            {--site=  : Only backup this site_id (optional)}
                            {--retention=7 : Number of backup files to keep per tenant}
                            {--dry-run : Show what would be backed up without running}';

    protected $description = 'Backup all active tenant databases to local storage and ArvanCloud';

    public function handle(ArvanBackupService $arvan, SlackAlertService $slack, TenantScopedTableResolver $resolver): int
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

        $customerDb = config('database.connections.mysql_customer.database', 'customer_site');

        if ($dryRun) {
            $this->info("[dry-run] Would backup {$sites->count()} tenant database(s)");
            foreach ($sites as $site) {
                $this->line("  site_id={$site->id}: {$site->title} ({$site->domain})");
            }
            $this->line('');
            $this->info('[dry-run] Tables that would be attempted (tenant-scoped, in order):');
            foreach ($resolver->tables() as $table) {
                $this->line("  {$table}: WHERE " . $resolver->whereClauseFor($table, $siteOpt !== null ? (int) $siteOpt : 0));
            }
            return self::SUCCESS;
        }

        $backupDir = storage_path('app/backups/tenants');

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

        $existingTables = $this->fetchExistingTables($envPwd, $connFlags, $customerDb);

        $successCount = 0;
        $failCount    = 0;

        foreach ($sites as $site) {
            $siteId   = $site->id;
            $filename = "db_backup_{$siteId}_" . now()->format('Y-m-d_His') . '.sql.gz';
            $fullPath = $backupDir . DIRECTORY_SEPARATOR . $filename;

            $run       = InfraBackupRun::startRun('database', $siteId);
            $startedAt = microtime(true);

            $result = $this->buildTenantSqlFile($resolver, $existingTables, $envPwd, $connFlags, $customerDb, $siteId);

            if (!$result['ok']) {
                $msg = $result['error'];
                $this->error("Tenant {$siteId} backup failed: {$msg}");
                $run->endFailed($msg, ['completeness_status' => 'failed']);
                $failCount++;
                Log::error('db:backup-tenants failed', ['site_id' => $siteId, 'error' => $msg]);
                continue;
            }

            $sqlPath = $result['sql_path'];

            // gzip is now compressing a local file we already fully built and
            // verified — no mysqldump on the other end of a pipe, so this
            // exit code genuinely reflects the only process running.
            $gzExit = $this->gzipFile($sqlPath, $fullPath);
            @unlink($sqlPath);

            if ($gzExit !== 0 || !file_exists($fullPath)) {
                $msg = "gzip failed (exit {$gzExit})";
                $this->error("Tenant {$siteId} backup failed: {$msg}");
                $run->endFailed($msg, ['completeness_status' => 'failed']);
                $failCount++;
                Log::error('db:backup-tenants gzip failed', ['site_id' => $siteId, 'exit' => $gzExit]);
                continue;
            }

            $validation = $this->validateGeneratedBackup($fullPath, count($result['included']));

            if (!$validation['valid']) {
                $msg = 'Generated backup failed validation: ' . implode('; ', $validation['errors']);
                $this->error("Tenant {$siteId} backup failed: {$msg}");
                @unlink($fullPath);
                $run->endFailed($msg, ['completeness_status' => 'failed']);
                $failCount++;
                Log::error('db:backup-tenants validation failed', ['site_id' => $siteId, 'errors' => $validation['errors']]);
                continue;
            }

            $sizeMb   = round(filesize($fullPath) / 1024 / 1024, 4);
            $duration = (int) (microtime(true) - $startedAt);

            $completeness = empty($result['skipped']) ? 'complete' : 'partial';

            $run->endSuccess($fullPath, $sizeMb, $duration, [
                'included_tables'      => $result['included'],
                'skipped_tables'       => $result['skipped'],
                'insert_counts'        => $result['insert_counts'],
                'total_insert_count'   => $validation['insert_count'],
                'completeness_status' => $completeness,
            ]);
            $successCount++;

            $this->line(
                "Backed up site {$siteId} ({$sizeMb} MB, {$validation['table_count']} tables, " .
                "{$validation['insert_count']} rows, completeness={$completeness})"
            );
            if (!empty($result['skipped'])) {
                foreach ($result['skipped'] as $skip) {
                    $this->warn("  skipped {$skip['table']}: {$skip['reason']}");
                }
            }

            // Upload to Arvan
            try {
                $remotePath = $arvan->tenantDbPath($siteId, $filename);
                $uploadResult = $arvan->upload($fullPath, $remotePath);

                if ($uploadResult['success']) {
                    $this->line("  Arvan upload OK: {$remotePath}");
                } else {
                    $this->warn("  Arvan upload failed for site {$siteId}: {$uploadResult['message']}");
                    Log::warning('db:backup-tenants arvan failed', ['site_id' => $siteId, 'error' => $uploadResult['message']]);
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

    /**
     * One SELECT against information_schema, shared across every site in
     * this run — cheaper than checking table existence per site, and the
     * schema can't change mid-run.
     *
     * @return list<string>
     */
    private function fetchExistingTables(string $envPwd, string $connFlags, string $customerDb): array
    {
        $sql = sprintf(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s",
            $this->mysqlQuote($customerDb)
        );

        $cmd = sprintf(
            '%smysql %s -N -e %s 2>&1',
            $envPwd,
            $connFlags,
            escapeshellarg($sql)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            Log::error('db:backup-tenants: could not read information_schema.TABLES', ['output' => implode(' ', $output)]);
            return [];
        }

        return array_values(array_filter(array_map('trim', $output)));
    }

    /**
     * Dumps every resolver-known table for one tenant into a single local
     * .sql file, one independent mysqldump invocation per table — never
     * piped into gzip. A table missing from the schema is skipped (recorded,
     * not fatal); an actual mysqldump failure on a table that DOES exist
     * aborts the whole tenant backup, matching the "atomic-looking" restore
     * contract rather than shipping a silently partial file.
     *
     * @return array{ok: bool, error?: string, sql_path?: string, included?: list<string>, skipped?: list<array{table:string,reason:string}>, insert_counts?: array<string,int>}
     */
    private function buildTenantSqlFile(
        TenantScopedTableResolver $resolver,
        array $existingTables,
        string $envPwd,
        string $connFlags,
        string $customerDb,
        int $siteId
    ): array {
        $ts      = now()->format('YmdHis');
        $sqlPath = sys_get_temp_dir() . "/tenant-backup-{$siteId}-{$ts}.sql";

        file_put_contents($sqlPath, "-- tenant-scoped backup for site_id={$siteId}\nSET FOREIGN_KEY_CHECKS=0;\n");

        $included     = [];
        $skipped      = [];
        $insertCounts = [];

        foreach ($resolver->tables() as $table) {
            if (!in_array($table, $existingTables, true)) {
                $skipped[] = ['table' => $table, 'reason' => 'table not found in schema'];
                continue;
            }

            $where     = $resolver->whereClauseFor($table, $siteId);
            $chunkPath = sys_get_temp_dir() . "/tenant-backup-{$siteId}-{$table}-{$ts}.sql";

            $cmd = sprintf(
                '%smysqldump --single-transaction --quick --lock-tables=false --where=%s %s %s %s > %s 2>%s',
                $envPwd,
                escapeshellarg($where),
                $connFlags,
                escapeshellarg($customerDb),
                escapeshellarg($table),
                escapeshellarg($chunkPath),
                escapeshellarg($chunkPath . '.err')
            );

            exec($cmd, $unused, $exitCode);

            if ($exitCode !== 0) {
                $errOutput = @file_get_contents($chunkPath . '.err') ?: '';
                @unlink($chunkPath);
                @unlink($chunkPath . '.err');
                @unlink($sqlPath);

                return [
                    'ok'    => false,
                    'error' => "mysqldump failed for table '{$table}' (exit {$exitCode}): " . trim($errOutput),
                ];
            }

            $chunk = file_get_contents($chunkPath) ?: '';
            @unlink($chunkPath);
            @unlink($chunkPath . '.err');

            $insertCounts[$table] = $this->countInserts($chunk);
            $included[]           = $table;

            file_put_contents($sqlPath, $chunk, FILE_APPEND);
        }

        file_put_contents($sqlPath, "\nSET FOREIGN_KEY_CHECKS=1;\n", FILE_APPEND);

        if (empty($included)) {
            @unlink($sqlPath);
            return ['ok' => false, 'error' => 'None of the tenant-scoped tables were found in the schema — nothing to back up.'];
        }

        return [
            'ok'            => true,
            'sql_path'      => $sqlPath,
            'included'      => $included,
            'skipped'       => $skipped,
            'insert_counts' => $insertCounts,
        ];
    }

    private function countInserts(string $sql): int
    {
        return preg_match_all('/^INSERT INTO/m', $sql);
    }

    /**
     * Compresses a fully-built local SQL file. This is the ONLY place gzip
     * runs, and nothing is piped into it — its exit code is unambiguous.
     */
    private function gzipFile(string $sqlPath, string $destGzPath): int
    {
        exec(sprintf('gzip -c %s > %s 2>&1', escapeshellarg($sqlPath), escapeshellarg($destGzPath)), $output, $exitCode);
        return $exitCode;
    }

    /**
     * Validates the backup that was just produced: gzip integrity, that the
     * decompressed content is well-formed enough to count CREATE TABLE
     * statements (catches truncation gzip -t alone would miss on a file
     * that's valid gzip but was cut short before compression), that the
     * table count matches what was actually attempted, and the INSERT
     * count. Never treats "valid, zero rows" as invalid — a genuinely
     * empty tenant is not a backup failure.
     *
     * @return array{valid: bool, table_count: int, insert_count: int, errors: list<string>}
     */
    private function validateGeneratedBackup(string $gzPath, int $expectedTableCount): array
    {
        $errors = [];

        exec('gzip -t ' . escapeshellarg($gzPath) . ' 2>&1', $output, $gzTestExit);
        if ($gzTestExit !== 0) {
            $errors[] = 'gzip integrity check failed';
            return ['valid' => false, 'table_count' => 0, 'insert_count' => 0, 'errors' => $errors];
        }

        $handle  = gzopen($gzPath, 'rb');
        $content = $handle ? stream_get_contents($handle) : false;
        if ($handle) {
            gzclose($handle);
        }

        if ($content === false) {
            $errors[] = 'could not decompress backup for content validation';
            return ['valid' => false, 'table_count' => 0, 'insert_count' => 0, 'errors' => $errors];
        }

        $tableCount  = preg_match_all('/^CREATE TABLE/m', $content);
        $insertCount = $this->countInserts($content);

        if ($tableCount !== $expectedTableCount) {
            $errors[] = "expected {$expectedTableCount} CREATE TABLE statements, found {$tableCount} (possible truncation)";
        }

        return [
            'valid'       => empty($errors),
            'table_count' => $tableCount,
            'insert_count' => $insertCount,
            'errors'      => $errors,
        ];
    }

    private function mysqlQuote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
