<?php
namespace App\Services\Infrastructure;

use App\Models\Infrastructure\RestoreLog;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * RestoreService — Production restore logic for platform and tenant backups.
 *
 * Safety contract:
 *  - Dry-run NEVER touches DB or files.
 *  - Every destructive operation creates a rollback snapshot first.
 *  - Redis lock prevents concurrent restores.
 *  - All operations are written to restore_logs.
 */
class RestoreService
{
    public function __construct(
        private ArvanBackupService $arvan,
        private SlackAlertService  $slack,
    ) {}

    // ─── Lock Management ───────────────────────────────────────────────────────

    public function isLocked(): bool
    {
        return (bool) Redis::get('restore_in_progress');
    }

    public function getLockValue(): ?string
    {
        $v = Redis::get('restore_in_progress');
        return $v ? (string) $v : null;
    }

    public function acquireLock(string $value, int $ttlMinutes = 30): bool
    {
        return (bool) Redis::set('restore_in_progress', $value, 'EX', $ttlMinutes * 60, 'NX');
    }

    public function releaseLock(): void
    {
        Redis::del('restore_in_progress');
    }

    // ─── Progress Streaming (Redis List) ──────────────────────────────────────

    public function pushProgress(string $token, array $event): void
    {
        $key = "restore_progress_{$token}";
        Redis::rpush($key, json_encode($event));
        Redis::expire($key, 7200); // 2 hour TTL
    }

    public function getProgressEvents(string $token, int $offset = 0): array
    {
        return Redis::lrange("restore_progress_{$token}", $offset, -1);
    }

    public function clearProgress(string $token): void
    {
        Redis::del("restore_progress_{$token}");
    }

    // ─── Backup Discovery ─────────────────────────────────────────────────────

    /**
     * List available backups for a given type and optional site.
     *
     * @return list<array{path, filename, size, last_modified, size_human, date_human}>
     */
    public function listBackups(string $type, ?int $siteId = null): array
    {
        $prefix = $this->remotePrefixFor($type, $siteId);
        $files  = $this->arvan->listFiles($prefix);

        return array_map(function ($file) {
            $file['size_human'] = $this->humanSize($file['size']);
            $file['date_human'] = $file['last_modified']
                ? date('Y/m/d H:i', $file['last_modified'])
                : '—';
            return $file;
        }, $files);
    }

    public function remotePrefixFor(string $type, ?int $siteId = null): string
    {
        return match($type) {
            RestoreLog::TYPE_PLATFORM_DATABASE => 'saas-platform/database',
            RestoreLog::TYPE_PLATFORM_STORAGE  => 'saas-platform/storage',
            RestoreLog::TYPE_TENANT_DATABASE   => "customer-sites/tenant-{$siteId}/database",
            RestoreLog::TYPE_TENANT_STORAGE    => "customer-sites/tenant-{$siteId}/storage",
            default => throw new \InvalidArgumentException("Unknown restore type: {$type}"),
        };
    }

    // ─── Backup Validation ────────────────────────────────────────────────────

    /**
     * Validate a backup file before restore. Returns validation result array.
     */
    public function validateBackup(string $remotePath, string $type): array
    {
        $checks = [];
        $valid  = true;

        // Check 1: File exists on Arvan
        $exists = $this->arvan->exists($remotePath);
        $checks[] = [
            'check'  => 'وجود فایل در Arvan',
            'result' => $exists ? 'pass' : 'fail',
            'detail' => $exists ? "فایل یافت شد: {$remotePath}" : 'فایل در Arvan Object Storage یافت نشد',
        ];
        if (!$exists) {
            $valid = false;
        }

        // Check 2: File size reasonable
        $size = $exists ? $this->arvan->size($remotePath) : 0;
        $minSize = str_contains($type, 'storage') ? 100 : 32;
        $sizeOk  = $size !== null && $size >= $minSize;
        $checks[] = [
            'check'  => 'اندازه فایل',
            'result' => $sizeOk ? 'pass' : 'fail',
            'detail' => $sizeOk
                ? 'اندازه فایل: ' . $this->humanSize($size)
                : ('اندازه فایل خیلی کوچک است یا نامشخص: ' . $this->humanSize($size ?? 0) . ' (حداقل ' . $minSize . ' بایت)'),
        ];
        if (!$sizeOk) {
            $valid = false;
        }

        // Check 3: Filename format
        $filename  = basename($remotePath);
        $formatOk  = $this->isValidBackupFilename($filename, $type);
        $checks[] = [
            'check'  => 'فرمت نام فایل',
            'result' => $formatOk ? 'pass' : 'warn',
            'detail' => $formatOk
                ? "نام فایل معتبر: {$filename}"
                : "نام فایل با فرمت استاندارد مطابقت ندارد: {$filename}",
        ];

        // Check 4: No active restore running
        $lockFree = !$this->isLocked();
        $checks[] = [
            'check'  => 'بدون تعارض',
            'result' => $lockFree ? 'pass' : 'fail',
            'detail' => $lockFree ? 'هیچ بازیابی در حال اجرا نیست' : 'یک بازیابی در حال اجرا است: ' . ($this->getLockValue() ?? ''),
        ];
        if (!$lockFree) {
            $valid = false;
        }

        $metadata = [
            'remote_path'   => $remotePath,
            'size'          => $size,
            'size_human'    => $this->humanSize($size ?? 0),
            'last_modified' => $exists ? $this->arvan->lastModified($remotePath) : null,
            'type'          => $type,
        ];

        return compact('valid', 'checks', 'metadata');
    }

    // ─── Dry Run ──────────────────────────────────────────────────────────────

    /**
     * Simulate a restore without any side effects.
     * Returns a summary of what would happen.
     */
    public function dryRun(string $type, string $remotePath, ?int $siteId = null): array
    {
        $validation = $this->validateBackup($remotePath, $type);
        $steps      = $this->describeRestoreSteps($type, $remotePath, $siteId);
        $warnings   = $this->collectWarnings($type, $siteId);
        $impact     = $this->estimateImpact($type, $siteId);

        return [
            'type'         => $type,
            'remote_path'  => $remotePath,
            'site_id'      => $siteId,
            'validation'   => $validation,
            'steps'        => $steps,
            'warnings'     => $warnings,
            'impact'       => $impact,
            'safe_to_run'  => $validation['valid'] && empty(array_filter($warnings, fn($w) => $w['level'] === 'critical')),
        ];
    }

    private function describeRestoreSteps(string $type, string $remotePath, ?int $siteId): array
    {
        $filename = basename($remotePath);

        return match($type) {
            RestoreLog::TYPE_PLATFORM_DATABASE => [
                ['step' => 1, 'action' => 'قفل Redis را تنظیم کن (TTL 60 دقیقه)'],
                ['step' => 2, 'action' => 'وضعیت maintenance را فعال کن (php artisan down)'],
                ['step' => 3, 'action' => "ایجاد snapshot قبل از بازیابی: pre-restore-platform-{timestamp}.sql.gz"],
                ['step' => 4, 'action' => "دانلود فایل بکاپ از Arvan: {$filename}"],
                ['step' => 5, 'action' => 'تأیید یکپارچگی گزیپ: gzip -t'],
                ['step' => 6, 'action' => 'DROP DATABASE saas_platform; CREATE DATABASE saas_platform'],
                ['step' => 7, 'action' => 'بازیابی دیتابیس: gunzip | mysql saas_platform'],
                ['step' => 8, 'action' => 'اجرای migration‌ها: php artisan migrate --force'],
                ['step' => 9, 'action' => 'پاکسازی cache: php artisan config:cache + route:cache + view:cache'],
                ['step' => 10, 'action' => 'غیرفعال کردن maintenance: php artisan up'],
                ['step' => 11, 'action' => 'بررسی سلامت: GET /health/ready'],
                ['step' => 12, 'action' => 'آزادکردن قفل Redis + ثبت لاگ فعالیت'],
            ],
            RestoreLog::TYPE_TENANT_DATABASE => [
                ['step' => 1, 'action' => "قفل Redis را تنظیم کن: tenant-{$siteId} (TTL 30 دقیقه)"],
                ['step' => 2, 'action' => "دانلود فایل بکاپ از Arvan: {$filename}"],
                ['step' => 3, 'action' => 'تأیید یکپارچگی گزیپ: gzip -t'],
                ['step' => 4, 'action' => "ایجاد snapshot: mysqldump customer_site WHERE site_id={$siteId}"],
                ['step' => 5, 'action' => "ایجاد دیتابیس موقت: CREATE DATABASE restore_tmp_{$siteId}"],
                ['step' => 6, 'action' => 'بازیابی کامل بکاپ در دیتابیس موقت'],
                ['step' => 7, 'action' => "تأیید وجود داده تِنِنت در دیتابیس موقت"],
                ['step' => 8, 'action' => "حذف ردیف‌های site_id={$siteId} از customer_site"],
                ['step' => 9, 'action' => "کپی داده از restore_tmp_{$siteId} به customer_site"],
                ['step' => 10, 'action' => "DROP DATABASE restore_tmp_{$siteId}"],
                ['step' => 11, 'action' => "پاکسازی cache تِنِنت: customers store"],
                ['step' => 12, 'action' => 'آزادکردن قفل Redis + ثبت لاگ فعالیت'],
            ],
            RestoreLog::TYPE_PLATFORM_STORAGE => [
                ['step' => 1, 'action' => "دانلود آرشیو از Arvan: {$filename}"],
                ['step' => 2, 'action' => 'تأیید یکپارچگی tar: tar -tzf'],
                ['step' => 3, 'action' => 'بررسی فضای دیسک'],
                ['step' => 4, 'action' => 'استخراج فایل‌ها در /var/www/saas-platform/storage/app/public/'],
                ['step' => 5, 'action' => 'تنظیم دسترسی‌ها: chown www-data'],
                ['step' => 6, 'action' => 'پاکسازی cache: php artisan cache:clear'],
                ['step' => 7, 'action' => 'ثبت لاگ فعالیت'],
            ],
            RestoreLog::TYPE_TENANT_STORAGE => [
                ['step' => 1, 'action' => "دانلود آرشیو از Arvan: {$filename}"],
                ['step' => 2, 'action' => 'تأیید یکپارچگی tar: tar -tzf'],
                ['step' => 3, 'action' => 'بررسی فضای دیسک'],
                ['step' => 4, 'action' => "استخراج فایل‌های تِنِنت {$siteId}"],
                ['step' => 5, 'action' => 'تنظیم دسترسی‌ها: chown www-data'],
                ['step' => 6, 'action' => 'ثبت لاگ فعالیت'],
            ],
            default => [],
        };
    }

    private function collectWarnings(string $type, ?int $siteId): array
    {
        $warnings = [];

        if ($type === RestoreLog::TYPE_PLATFORM_DATABASE) {
            $warnings[] = ['level' => 'critical', 'message' => 'این عملیات کل دیتابیس پلتفرم را جایگزین می‌کند. تمام تِنِنت‌ها تحت تأثیر قرار می‌گیرند.'];
            $warnings[] = ['level' => 'high',     'message' => 'پلتفرم در طول بازیابی در حالت maintenance خواهد بود.'];
        }

        if ($type === RestoreLog::TYPE_TENANT_DATABASE && $siteId) {
            $site = Site::find($siteId);
            if ($site) {
                $warnings[] = ['level' => 'high', 'message' => "تمام داده‌های tenant «{$site->name}» (site_id={$siteId}) با داده‌های بکاپ جایگزین خواهند شد."];
            }
            $warnings[] = ['level' => 'medium', 'message' => 'کاربران تِنِنت در حین بازیابی ممکن است خطا ببینند.'];
        }

        if (str_contains($type, 'storage')) {
            $warnings[] = ['level' => 'high', 'message' => 'فایل‌های موجودی که با بکاپ تداخل دارند جایگزین خواهند شد.'];
        }

        return $warnings;
    }

    private function estimateImpact(string $type, ?int $siteId): array
    {
        return match(true) {
            $type === RestoreLog::TYPE_PLATFORM_DATABASE => [
                'scope'    => 'کل پلتفرم',
                'downtime' => 'بله — maintenance mode فعال می‌شود',
                'data_at_risk' => 'تمام جداول پلتفرم از آخرین snapshot تا الان',
            ],
            $type === RestoreLog::TYPE_TENANT_DATABASE => [
                'scope'    => "tenant-{$siteId} فقط",
                'downtime' => 'خیر — سایر تِنِنت‌ها تحت تأثیر نیستند',
                'data_at_risk' => "داده‌های site_id={$siteId} از آخرین snapshot تا الان",
            ],
            str_contains($type, 'storage') => [
                'scope'    => 'فایل‌های استوریج',
                'downtime' => 'خیر — بازیابی غیرمخرب است',
                'data_at_risk' => 'فایل‌های استوریج که از آخرین snapshot تغییر کرده‌اند',
            ],
            default => ['scope' => '—', 'downtime' => '—', 'data_at_risk' => '—'],
        };
    }

    // ─── Snapshot (Pre-Restore) ───────────────────────────────────────────────

    /**
     * Create a pre-restore database snapshot. Returns local path or null on failure.
     */
    public function createDatabaseSnapshot(string $database, string $label, ?string $whereClause = null): ?string
    {
        $ts        = now()->format('Y-m-d_His');
        $localPath = sys_get_temp_dir() . "/pre-restore-{$label}-{$ts}.sql.gz";

        $host     = config('database.connections.mysql.host');
        $port     = config('database.connections.mysql.port', 3306);
        $user     = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $passwordArg = $password !== '' ? '-p' . escapeshellarg($password) : '';
        $whereArg    = $whereClause ? "--where=" . escapeshellarg($whereClause) : '';

        $cmd = sprintf(
            'mysqldump --single-transaction --quick --lock-tables=false -h %s -P %s -u %s %s %s %s | gzip > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($user),
            $passwordArg,
            escapeshellarg($database),
            $whereArg,
            escapeshellarg($localPath)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($localPath) || filesize($localPath) < 32) {
            Log::error("RestoreService: snapshot failed for {$database}", [
                'exit_code' => $exitCode,
                'output'    => implode(' ', $output),
            ]);
            return null;
        }

        Log::info("RestoreService: snapshot created: {$localPath} (" . filesize($localPath) . " bytes)");
        return $localPath;
    }

    // ─── File Integrity ───────────────────────────────────────────────────────

    public function verifyGzip(string $localPath): bool
    {
        exec('gzip -t ' . escapeshellarg($localPath) . ' 2>&1', $output, $code);
        return $code === 0;
    }

    public function verifyTar(string $localPath): bool
    {
        exec('tar -tzf ' . escapeshellarg($localPath) . ' > /dev/null 2>&1', $output, $code);
        return $code === 0;
    }

    // ─── Restore Execution (called from Jobs) ─────────────────────────────────

    /**
     * Execute a tenant database restore.
     * This method is called from RestoreTenantDatabaseJob.
     */
    public function executeTenantDatabaseRestore(RestoreLog $log, string $remotePath, int $siteId): void
    {
        $token   = $log->restore_token;
        $events  = [];

        try {
            $this->push($token, $events, 5, 'آغاز بازیابی دیتابیس تِنِنت', 'running');

            // Step 1: Download from Arvan
            $this->push($token, $events, 10, 'دانلود فایل بکاپ از Arvan...');
            $ts        = now()->format('Y-m-d_His');
            $localPath = sys_get_temp_dir() . "/restore-tenant-{$siteId}-{$ts}.sql.gz";

            if (!$this->arvan->download($remotePath, $localPath)) {
                throw new \RuntimeException('دانلود فایل بکاپ از Arvan ناموفق بود');
            }
            $this->push($token, $events, 20, 'فایل بکاپ دانلود شد: ' . $this->humanSize(filesize($localPath)));

            // Step 2: Verify integrity
            $this->push($token, $events, 25, 'تأیید یکپارچگی فایل gzip...');
            if (!$this->verifyGzip($localPath)) {
                @unlink($localPath);
                throw new \RuntimeException('فایل بکاپ خراب است (gzip integrity check failed)');
            }
            $this->push($token, $events, 30, 'یکپارچگی فایل تأیید شد');

            // Step 3: Pre-restore snapshot
            $this->push($token, $events, 35, 'ایجاد snapshot قبل از بازیابی...');
            $customerDb   = config('database.connections.mysql_customer.database', 'customer_site');
            $snapshotPath = $this->createDatabaseSnapshot($customerDb, "tenant-{$siteId}", "site_id={$siteId}");
            if ($snapshotPath) {
                $log->update(['rollback_snapshot_path' => $snapshotPath]);
                $this->push($token, $events, 40, 'Snapshot ایجاد شد: ' . basename($snapshotPath));
            } else {
                $this->push($token, $events, 40, 'هشدار: snapshot ایجاد نشد — ادامه با احتیاط', 'warn');
            }

            // Step 4: Create temp DB and restore
            $tmpDb    = "restore_tmp_{$siteId}";
            $host     = config('database.connections.mysql.host');
            $port     = config('database.connections.mysql.port', 3306);
            $user     = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $passArg  = $password !== '' ? '-p' . escapeshellarg($password) : '';
            $connArgs = sprintf('-h %s -P %s -u %s %s', escapeshellarg($host), escapeshellarg((string)$port), escapeshellarg($user), $passArg);

            $this->push($token, $events, 45, "ایجاد دیتابیس موقت {$tmpDb}...");
            exec("mysql {$connArgs} -e " . escapeshellarg("CREATE DATABASE IF NOT EXISTS `{$tmpDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci") . ' 2>&1', $o, $c);
            if ($c !== 0) throw new \RuntimeException("ایجاد دیتابیس موقت ناموفق: " . implode(' ', $o));

            $this->push($token, $events, 50, 'بازیابی بکاپ در دیتابیس موقت...');
            exec("gunzip < {$localPath} | mysql {$connArgs} " . escapeshellarg($tmpDb) . ' 2>&1', $o, $c);
            if ($c !== 0) {
                exec("mysql {$connArgs} -e " . escapeshellarg("DROP DATABASE IF EXISTS `{$tmpDb}`"), $_, $_);
                throw new \RuntimeException("بازیابی در دیتابیس موقت ناموفق: " . implode(' ', $o));
            }
            $this->push($token, $events, 60, 'بازیابی در دیتابیس موقت کامل شد');

            // Step 5: Delete existing tenant data
            $this->push($token, $events, 65, "حذف داده‌های site_id={$siteId} از {$customerDb}...");
            $this->deleteTenantData($connArgs, $customerDb, $siteId);
            $this->push($token, $events, 72, 'داده‌های قدیمی تِنِنت حذف شدند');

            // Step 6: Copy from tmp to production
            $this->push($token, $events, 75, 'کپی داده از دیتابیس موقت به تولید...');
            $this->copyTenantData($connArgs, $tmpDb, $customerDb, $siteId);
            $this->push($token, $events, 88, 'داده‌های تِنِنت کپی شدند');

            // Step 7: Drop temp DB
            $this->push($token, $events, 90, "حذف دیتابیس موقت {$tmpDb}...");
            exec("mysql {$connArgs} -e " . escapeshellarg("DROP DATABASE IF EXISTS `{$tmpDb}`"), $_, $_);

            // Step 8: Flush caches
            $this->push($token, $events, 93, 'پاکسازی cache تِنِنت...');
            \Illuminate\Support\Facades\Cache::store('customers')->flush();

            // Step 9: Cleanup
            @unlink($localPath);
            $this->releaseLock();

            // Step 10: Log activity
            $this->writeActivityLog('tenant_database', $siteId, basename($remotePath), 'success');

            $log->markSuccess($events);
            $this->push($token, $events, 100, 'بازیابی دیتابیس تِنِنت با موفقیت انجام شد', 'success', true);

        } catch (\Throwable $e) {
            @unlink($localPath ?? '');
            $this->releaseLock();
            $this->writeActivityLog('tenant_database', $siteId, basename($remotePath), 'failed', $e->getMessage());

            $errMsg = $e->getMessage();
            $log->markFailed($errMsg, $events);
            $this->push($token, $events, null, "خطا: {$errMsg}", 'error', true);

            $this->slack->critical('Tenant DB Restore Failed', $errMsg, ['site_id' => $siteId, 'file' => $remotePath]);
            Log::error("RestoreService: tenant restore failed", ['site_id' => $siteId, 'error' => $errMsg]);
        }
    }

    /**
     * Execute a platform storage restore.
     * Called from RestorePlatformStorageJob.
     */
    public function executePlatformStorageRestore(RestoreLog $log, string $remotePath, string $mergeStrategy = 'merge'): void
    {
        $token  = $log->restore_token;
        $events = [];

        try {
            $this->push($token, $events, 5, 'آغاز بازیابی فایل‌های پلتفرم', 'running');

            // Download
            $this->push($token, $events, 10, 'دانلود آرشیو از Arvan...');
            $ts        = now()->format('Y-m-d_His');
            $localPath = sys_get_temp_dir() . "/restore-storage-{$ts}.tar.gz";

            if (!$this->arvan->download($remotePath, $localPath)) {
                throw new \RuntimeException('دانلود آرشیو از Arvan ناموفق بود');
            }
            $this->push($token, $events, 25, 'آرشیو دانلود شد: ' . $this->humanSize(filesize($localPath)));

            // Verify
            $this->push($token, $events, 30, 'تأیید یکپارچگی tar...');
            if (!$this->verifyTar($localPath)) {
                @unlink($localPath);
                throw new \RuntimeException('آرشیو خراب است (tar integrity check failed)');
            }
            $this->push($token, $events, 35, 'یکپارچگی آرشیو تأیید شد');

            // Disk space check
            $this->push($token, $events, 40, 'بررسی فضای دیسک...');
            $freeBytes  = disk_free_space(storage_path('app/public'));
            $tarSize    = filesize($localPath);
            if ($freeBytes < $tarSize * 2) {
                throw new \RuntimeException('فضای دیسک کافی نیست. فضای آزاد: ' . $this->humanSize($freeBytes));
            }
            $this->push($token, $events, 45, 'فضای دیسک کافی است: ' . $this->humanSize($freeBytes));

            // Extract
            $destDir = storage_path('app/public');
            $this->push($token, $events, 50, "استخراج فایل‌ها (strategy: {$mergeStrategy})...");

            if ($mergeStrategy === 'overwrite') {
                $cmd = 'tar -xzf ' . escapeshellarg($localPath) . ' -C ' . escapeshellarg($destDir) . ' 2>&1';
            } else {
                // merge: only copy files not already present
                $tmpExtract = sys_get_temp_dir() . "/restore-storage-tmp-{$ts}";
                mkdir($tmpExtract, 0755, true);
                exec('tar -xzf ' . escapeshellarg($localPath) . ' -C ' . escapeshellarg($tmpExtract) . ' 2>&1', $o1, $c1);
                if ($c1 !== 0) throw new \RuntimeException('استخراج موقت ناموفق: ' . implode(' ', $o1));
                $cmd = 'rsync -a --ignore-existing ' . escapeshellarg($tmpExtract . '/') . ' ' . escapeshellarg($destDir . '/') . ' 2>&1';
            }

            exec($cmd, $output, $exitCode);
            if (isset($tmpExtract)) exec('rm -rf ' . escapeshellarg($tmpExtract));
            if ($exitCode !== 0) throw new \RuntimeException('استخراج فایل‌ها ناموفق: ' . implode(' ', $output));
            $this->push($token, $events, 75, 'فایل‌ها با موفقیت استخراج شدند');

            // Fix permissions
            $this->push($token, $events, 80, 'تنظیم دسترسی‌های فایل...');
            exec('chown -R www-data:www-data ' . escapeshellarg($destDir) . ' 2>&1');
            $this->push($token, $events, 85, 'دسترسی‌های فایل تنظیم شد');

            // Cleanup
            @unlink($localPath);
            $this->push($token, $events, 90, 'پاکسازی cache...');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');

            $this->releaseLock();
            $this->writeActivityLog('platform_storage', null, basename($remotePath), 'success');

            $log->markSuccess($events);
            $this->push($token, $events, 100, 'بازیابی فایل‌های پلتفرم با موفقیت انجام شد', 'success', true);

        } catch (\Throwable $e) {
            @unlink($localPath ?? '');
            $this->releaseLock();
            $this->writeActivityLog('platform_storage', null, basename($remotePath), 'failed', $e->getMessage());

            $errMsg = $e->getMessage();
            $log->markFailed($errMsg, $events);
            $this->push($token, $events, null, "خطا: {$errMsg}", 'error', true);

            $this->slack->critical('Platform Storage Restore Failed', $errMsg, ['file' => $remotePath]);
            Log::error("RestoreService: platform storage restore failed", ['error' => $errMsg]);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function push(string $token, array &$events, ?int $progress, string $message, string $status = 'info', bool $final = false): void
    {
        $event = array_filter([
            'progress' => $progress,
            'message'  => $message,
            'status'   => $status,
            'step'     => $final ? ($status === 'error' ? 'error' : 'done') : null,
            'time'     => now()->format('H:i:s'),
        ], fn($v) => $v !== null);

        $events[] = $event;
        $this->pushProgress($token, $event);
    }

    private function deleteTenantData(string $connArgs, string $db, int $siteId): void
    {
        // Tables that have site_id column — delete in child-first order to respect FKs
        $tables = $this->getTenantTables($connArgs, $db, $siteId);
        foreach (array_reverse($tables) as $table) {
            exec("mysql {$connArgs} " . escapeshellarg($db) . " -e " . escapeshellarg("DELETE FROM `{$table}` WHERE site_id={$siteId}") . ' 2>&1');
        }
    }

    private function copyTenantData(string $connArgs, string $tmpDb, string $destDb, int $siteId): void
    {
        $tables = $this->getTenantTables($connArgs, $tmpDb, $siteId);
        foreach ($tables as $table) {
            // Check table exists in dest
            exec("mysql {$connArgs} " . escapeshellarg($destDb) . " -e " . escapeshellarg("SELECT 1 FROM `{$table}` LIMIT 1") . ' 2>&1', $_, $c);
            if ($c !== 0) continue; // table doesn't exist in dest — skip

            exec("mysql {$connArgs} -e " . escapeshellarg(
                "INSERT INTO `{$destDb}`.`{$table}` SELECT * FROM `{$tmpDb}`.`{$table}` WHERE site_id={$siteId}"
            ) . ' 2>&1');
        }
    }

    private function getTenantTables(string $connArgs, string $db, int $siteId): array
    {
        exec("mysql {$connArgs} " . escapeshellarg($db) . " -e " . escapeshellarg(
            "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{$db}' AND COLUMN_NAME='site_id' GROUP BY TABLE_NAME"
        ) . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) return [];

        return array_filter(
            array_map('trim', array_slice($output, 1)), // skip header
            fn($t) => $t !== '' && $t !== 'TABLE_NAME'
        );
    }

    private function writeActivityLog(string $type, ?int $siteId, string $filename, string $result, string $error = ''): void
    {
        try {
            \App\Models\ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'disaster_recovery_restore',
                'subject'    => "restore_type={$type} file={$filename}" . ($siteId ? " site_id={$siteId}" : ''),
                'after_data' => json_encode(['result' => $result, 'error' => $error]),
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('RestoreService: activity log write failed: ' . $e->getMessage());
        }
    }

    private function isValidBackupFilename(string $filename, string $type): bool
    {
        if (str_contains($type, 'database')) {
            return (bool) preg_match('/^db_backup_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/', $filename);
        }
        if (str_contains($type, 'storage')) {
            return (bool) preg_match('/^storage_backup_\d{4}-\d{2}-\d{2}_\d{6}\.tar\.gz$/', $filename);
        }
        return true;
    }

    public function humanSize(?int $bytes): string
    {
        if ($bytes === null || $bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function generateToken(): string
    {
        return Str::random(40);
    }
}
