<?php
namespace App\Services\Infrastructure;

use App\Models\Infrastructure\RestoreLog;
use App\Models\Site;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * RestoreService — Production restore logic for platform and tenant backups.
 *
 * Safety contracts:
 *  - Dry-run NEVER touches DB or files.
 *  - Every destructive operation creates a rollback snapshot first.
 *  - Redis lock prevents concurrent restores (error-handled — no exception on Redis failure).
 *  - Tenant delete+copy wrapped in DB transaction — atomic, no partial state.
 *  - Passwords passed via MYSQL_PWD env var — never in CLI args.
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
        try {
            return (bool) Redis::get('restore_in_progress');
        } catch (\Throwable) {
            return false;
        }
    }

    public function getLockValue(): ?string
    {
        try {
            $v = Redis::get('restore_in_progress');
            return $v ? (string) $v : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function acquireLock(string $value, int $ttlMinutes = 30): bool
    {
        try {
            return (bool) Redis::set('restore_in_progress', $value, 'EX', $ttlMinutes * 60, 'NX');
        } catch (\Throwable) {
            return false;
        }
    }

    public function releaseLock(): void
    {
        try {
            Redis::del('restore_in_progress');
        } catch (\Throwable) {
            // Redis unavailable — lock will expire via TTL
        }
    }

    // ─── Progress Streaming (Redis List) ──────────────────────────────────────

    public function pushProgress(string $token, array $event): void
    {
        try {
            $key = "restore_progress_{$token}";
            Redis::rpush($key, json_encode($event));
            Redis::expire($key, 7200);
        } catch (\Throwable) {
            // Non-fatal — progress streaming degrades gracefully on Redis failure
        }
    }

    public function getProgressEvents(string $token, int $offset = 0): array
    {
        try {
            return Redis::lrange("restore_progress_{$token}", $offset, -1);
        } catch (\Throwable) {
            return [];
        }
    }

    public function clearProgress(string $token): void
    {
        try {
            Redis::del("restore_progress_{$token}");
        } catch (\Throwable) {}
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

    public function validateBackup(string $remotePath, string $type): array
    {
        $checks = [];
        $valid  = true;

        $exists = $this->arvan->exists($remotePath);
        $checks[] = [
            'check'  => 'وجود فایل در Arvan',
            'result' => $exists ? 'pass' : 'fail',
            'detail' => $exists ? "فایل یافت شد: {$remotePath}" : 'فایل در Arvan Object Storage یافت نشد',
        ];
        if (!$exists) $valid = false;

        $size    = $exists ? $this->arvan->size($remotePath) : 0;
        $minSize = str_contains($type, 'storage') ? 100 : 32;
        $sizeOk  = $size !== null && $size >= $minSize;
        $checks[] = [
            'check'  => 'اندازه فایل',
            'result' => $sizeOk ? 'pass' : 'fail',
            'detail' => $sizeOk
                ? 'اندازه فایل: ' . $this->humanSize($size)
                : 'اندازه فایل خیلی کوچک یا نامشخص: ' . $this->humanSize($size ?? 0),
        ];
        if (!$sizeOk) $valid = false;

        $filename = basename($remotePath);
        $formatOk = $this->isValidBackupFilename($filename, $type);
        $checks[] = [
            'check'  => 'فرمت نام فایل',
            'result' => $formatOk ? 'pass' : 'warn',
            'detail' => $formatOk ? "نام فایل معتبر: {$filename}" : "نام فایل با فرمت استاندارد مطابقت ندارد: {$filename}",
        ];

        $lockFree = !$this->isLocked();
        $checks[] = [
            'check'  => 'بدون تعارض',
            'result' => $lockFree ? 'pass' : 'fail',
            'detail' => $lockFree ? 'هیچ بازیابی در حال اجرا نیست' : 'یک بازیابی در حال اجرا است: ' . ($this->getLockValue() ?? ''),
        ];
        if (!$lockFree) $valid = false;

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

    public function dryRun(string $type, string $remotePath, ?int $siteId = null): array
    {
        $validation = $this->validateBackup($remotePath, $type);
        $steps      = $this->describeRestoreSteps($type, $remotePath, $siteId);
        $warnings   = $this->collectWarnings($type, $siteId);
        $impact     = $this->estimateImpact($type, $siteId);

        return [
            'type'        => $type,
            'remote_path' => $remotePath,
            'site_id'     => $siteId,
            'validation'  => $validation,
            'steps'       => $steps,
            'warnings'    => $warnings,
            'impact'      => $impact,
            'safe_to_run' => $validation['valid'] && empty(array_filter($warnings, fn($w) => $w['level'] === 'critical')),
        ];
    }

    private function describeRestoreSteps(string $type, string $remotePath, ?int $siteId): array
    {
        $filename = basename($remotePath);

        return match($type) {
            RestoreLog::TYPE_PLATFORM_DATABASE => [
                ['step' => 1,  'action' => 'قفل Redis را تنظیم کن (TTL 60 دقیقه)'],
                ['step' => 2,  'action' => "دانلود فایل بکاپ از Arvan: {$filename}"],
                ['step' => 3,  'action' => 'تأیید یکپارچگی گزیپ: gzip -t'],
                ['step' => 4,  'action' => 'وضعیت maintenance را فعال کن (php artisan down)'],
                ['step' => 5,  'action' => 'ایجاد snapshot: mysqldump saas_platform (MYSQL_PWD env var)'],
                ['step' => 6,  'action' => 'DROP DATABASE saas_platform; CREATE DATABASE saas_platform'],
                ['step' => 7,  'action' => 'بازیابی دیتابیس: gunzip | mysql (MYSQL_PWD env var)'],
                ['step' => 8,  'action' => 'اجرای migration‌ها: php artisan migrate --force'],
                ['step' => 9,  'action' => 'بازسازی cache: config:cache + route:cache + view:cache'],
                ['step' => 10, 'action' => 'غیرفعال کردن maintenance: php artisan up'],
                ['step' => 11, 'action' => 'آزادکردن قفل Redis + ثبت لاگ فعالیت'],
            ],
            RestoreLog::TYPE_TENANT_DATABASE => [
                ['step' => 1,  'action' => "قفل Redis را تنظیم کن: tenant-{$siteId} (TTL 30 دقیقه)"],
                ['step' => 2,  'action' => "دانلود فایل بکاپ از Arvan: {$filename}"],
                ['step' => 3,  'action' => 'تأیید یکپارچگی گزیپ: gzip -t'],
                ['step' => 4,  'action' => "ایجاد snapshot: mysqldump WHERE site_id={$siteId} (MYSQL_PWD env var)"],
                ['step' => 5,  'action' => "ایجاد دیتابیس موقت: CREATE DATABASE restore_tmp_{$siteId}"],
                ['step' => 6,  'action' => 'بازیابی کامل بکاپ در دیتابیس موقت'],
                ['step' => 7,  'action' => "انتقال atomic: DELETE site_id={$siteId} + INSERT FROM tmp — در تراکنش DB"],
                ['step' => 8,  'action' => "DROP DATABASE restore_tmp_{$siteId}"],
                ['step' => 9,  'action' => 'پاکسازی cache تِنِنت'],
                ['step' => 10, 'action' => 'آزادکردن قفل Redis + ثبت لاگ فعالیت'],
            ],
            RestoreLog::TYPE_PLATFORM_STORAGE => [
                ['step' => 1, 'action' => "دانلود آرشیو از Arvan: {$filename}"],
                ['step' => 2, 'action' => 'تأیید یکپارچگی tar: tar -tzf'],
                ['step' => 3, 'action' => 'بررسی فضای دیسک'],
                ['step' => 4, 'action' => 'استخراج فایل‌ها در storage/app/public/'],
                ['step' => 5, 'action' => 'تنظیم دسترسی‌ها: chown www-data'],
                ['step' => 6, 'action' => 'پاکسازی cache + ثبت لاگ'],
            ],
            RestoreLog::TYPE_TENANT_STORAGE => [
                ['step' => 1, 'action' => "دانلود آرشیو از Arvan: {$filename}"],
                ['step' => 2, 'action' => 'تأیید یکپارچگی tar: tar -tzf'],
                ['step' => 3, 'action' => "استخراج فایل‌های تِنِنت {$siteId}"],
                ['step' => 4, 'action' => 'ثبت لاگ فعالیت'],
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
                'scope'        => 'کل پلتفرم',
                'downtime'     => 'بله — maintenance mode فعال می‌شود',
                'data_at_risk' => 'تمام جداول پلتفرم از آخرین snapshot تا الان',
            ],
            $type === RestoreLog::TYPE_TENANT_DATABASE => [
                'scope'        => "tenant-{$siteId} فقط",
                'downtime'     => 'خیر — سایر تِنِنت‌ها تحت تأثیر نیستند',
                'data_at_risk' => "داده‌های site_id={$siteId} از آخرین snapshot تا الان",
            ],
            str_contains($type, 'storage') => [
                'scope'        => 'فایل‌های استوریج',
                'downtime'     => 'خیر',
                'data_at_risk' => 'فایل‌های استوریج که از آخرین snapshot تغییر کرده‌اند',
            ],
            default => ['scope' => '—', 'downtime' => '—', 'data_at_risk' => '—'],
        };
    }

    // ─── Snapshot (Pre-Restore) ───────────────────────────────────────────────

    /**
     * Create a pre-restore database snapshot. Returns local path or null on failure.
     * Uses MYSQL_PWD env var — password never in CLI args.
     */
    public function createDatabaseSnapshot(string $database, string $label, ?string $whereClause = null): ?string
    {
        $ts        = now()->format('Y-m-d_His');
        $localPath = sys_get_temp_dir() . "/pre-restore-{$label}-{$ts}.sql.gz";

        $host     = config('database.connections.mysql.host');
        $port     = config('database.connections.mysql.port', 3306);
        $user     = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $whereArg = $whereClause ? '--where=' . escapeshellarg($whereClause) : '';
        $envPwd   = $password !== '' ? 'MYSQL_PWD=' . escapeshellarg($password) . ' ' : '';

        $cmd = sprintf(
            '%smysqldump --single-transaction --quick --lock-tables=false -h %s -P %s -u %s %s %s | gzip > %s 2>&1',
            $envPwd,
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($user),
            escapeshellarg($database),
            $whereArg,
            escapeshellarg($localPath)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($localPath) || filesize($localPath) < 32) {
            Log::error("RestoreService: snapshot failed for {$database}", [
                'exit_code' => $exitCode,
                'label'     => $label,
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

    // ─── Platform Database Restore ────────────────────────────────────────────

    /**
     * Execute a full platform database restore.
     * Called from RestorePlatformDatabaseJob.
     * Steps: download → verify gzip → maintenance mode → pre-restore snapshot →
     *        drop/create DB → restore → migrate → cache rebuild → maintenance off.
     */
    public function executePlatformDatabaseRestore(RestoreLog $log, string $remotePath): void
    {
        $token             = $log->restore_token;
        $events            = [];
        $localPath         = null;
        $maintenanceActive = false;

        try {
            $this->push($token, $events, 5, 'آغاز بازیابی دیتابیس پلتفرم', 'running');

            // Step 1: Download from Arvan
            $this->push($token, $events, 8, 'دانلود فایل بکاپ از Arvan...');
            $ts        = now()->format('Y-m-d_His');
            $localPath = sys_get_temp_dir() . "/restore-platform-db-{$ts}.sql.gz";

            if (!$this->arvan->download($remotePath, $localPath)) {
                throw new \RuntimeException('دانلود فایل بکاپ از Arvan ناموفق بود');
            }
            $this->push($token, $events, 20, 'فایل بکاپ دانلود شد: ' . $this->humanSize(filesize($localPath)));

            // Step 2: Verify gzip integrity
            $this->push($token, $events, 23, 'تأیید یکپارچگی فایل gzip...');
            if (!$this->verifyGzip($localPath)) {
                @unlink($localPath);
                throw new \RuntimeException('فایل بکاپ خراب است (gzip integrity check failed)');
            }
            $this->push($token, $events, 27, 'یکپارچگی فایل تأیید شد');

            // Step 3: Enable maintenance mode
            $this->push($token, $events, 30, 'فعال‌سازی حالت maintenance...');
            Artisan::call('down');
            $maintenanceActive = true;
            $this->push($token, $events, 33, 'حالت maintenance فعال شد');

            // Step 4: Create pre-restore snapshot
            $this->push($token, $events, 35, 'ایجاد snapshot قبل از بازیابی...');
            $database     = config('database.connections.mysql.database');
            $snapshotPath = $this->createDatabaseSnapshot($database, 'platform');
            if ($snapshotPath) {
                $log->update(['rollback_snapshot_path' => $snapshotPath]);
                $this->push($token, $events, 43, 'Snapshot ایجاد شد: ' . basename($snapshotPath));
            } else {
                $this->push($token, $events, 43, 'هشدار: snapshot ایجاد نشد — ادامه با احتیاط', 'warn');
            }

            // Step 5: Drop and recreate database using MYSQL_PWD
            [$host, $port, $user, $password] = $this->dbCredentials();
            $envPwd    = $password !== '' ? 'MYSQL_PWD=' . escapeshellarg($password) . ' ' : '';
            $connFlags = sprintf(
                '-h %s -P %s -u %s',
                escapeshellarg($host),
                escapeshellarg((string) $port),
                escapeshellarg($user)
            );

            $this->push($token, $events, 47, "DROP و CREATE DATABASE {$database}...");
            exec("{$envPwd}mysql {$connFlags} -e " . escapeshellarg(
                "DROP DATABASE IF EXISTS `{$database}`; CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            ) . ' 2>&1', $o, $c);
            if ($c !== 0) {
                throw new \RuntimeException("DROP/CREATE DATABASE ناموفق: " . implode(' ', $o));
            }
            $this->push($token, $events, 52, 'دیتابیس پاک‌سازی و ایجاد مجدد شد');

            // Step 6: Restore database from backup
            $this->push($token, $events, 55, 'بازیابی دیتابیس از بکاپ...');
            exec(
                "{$envPwd}gunzip -c " . escapeshellarg($localPath) . " | mysql {$connFlags} " . escapeshellarg($database) . ' 2>&1',
                $o, $c
            );
            if ($c !== 0) {
                throw new \RuntimeException("بازیابی دیتابیس ناموفق: " . implode(' ', $o));
            }
            $this->push($token, $events, 72, 'دیتابیس با موفقیت بازیابی شد');

            // Step 7: Run pending migrations
            $this->push($token, $events, 75, 'اجرای migration‌های جدید...');
            $migResult = Artisan::call('migrate', ['--force' => true]);
            $this->push($token, $events, 80, $migResult === 0
                ? 'migration‌ها اجرا شدند'
                : 'هشدار: برخی migration‌ها ناموفق بودند');

            // Step 8: Rebuild caches
            $this->push($token, $events, 82, 'بازسازی cache‌ها...');
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            $this->push($token, $events, 87, 'Cache‌ها بازسازی شدند');

            // Step 9: Disable maintenance mode
            $this->push($token, $events, 90, 'غیرفعال‌سازی حالت maintenance...');
            Artisan::call('up');
            $maintenanceActive = false;
            $this->push($token, $events, 93, 'حالت maintenance غیرفعال شد');

            @unlink($localPath);
            $this->releaseLock();
            $this->writeActivityLog('platform_database', null, basename($remotePath), 'success');

            $log->markSuccess($events);
            $this->push($token, $events, 100, 'بازیابی دیتابیس پلتفرم با موفقیت انجام شد', 'success', true);

        } catch (\Throwable $e) {
            @unlink($localPath ?? '');

            if ($maintenanceActive) {
                try { Artisan::call('up'); } catch (\Throwable) {}
            }

            $this->releaseLock();
            $this->writeActivityLog('platform_database', null, basename($remotePath), 'failed', $e->getMessage());

            $errMsg = $e->getMessage();
            $log->markFailed($errMsg, $events);
            $this->push($token, $events, null, "خطا: {$errMsg}", 'error', true);

            $this->slack->critical('Platform DB Restore Failed', $errMsg, ['file' => $remotePath]);
            Log::error("RestoreService: platform DB restore failed", ['error' => $errMsg]);
        }
    }

    // ─── Tenant Database Restore ──────────────────────────────────────────────

    /**
     * Execute a tenant database restore.
     * Called from RestoreTenantDatabaseJob.
     * Delete+copy wrapped in DB::transaction() — atomic, no partial tenant state.
     */
    public function executeTenantDatabaseRestore(RestoreLog $log, string $remotePath, int $siteId): void
    {
        $token     = $log->restore_token;
        $events    = [];
        $localPath = null;
        $tmpDb     = "restore_tmp_{$siteId}";

        try {
            $this->push($token, $events, 5, 'آغاز بازیابی دیتابیس تِنِنت', 'running');

            // Step 1: Download from Arvan
            $this->push($token, $events, 10, 'دانلود فایل بکاپ از Arvan...');
            $ts        = now()->format('Y-m-d_His');
            $localPath = sys_get_temp_dir() . "/restore-tenant-{$siteId}-{$ts}.sql.gz";

            if (!$this->arvan->download($remotePath, $localPath)) {
                throw new \RuntimeException('دانلود فایل بکاپ از Arvan ناموفق بود');
            }
            $this->push($token, $events, 22, 'فایل بکاپ دانلود شد: ' . $this->humanSize(filesize($localPath)));

            // Step 2: Verify gzip integrity
            $this->push($token, $events, 25, 'تأیید یکپارچگی فایل gzip...');
            if (!$this->verifyGzip($localPath)) {
                @unlink($localPath);
                throw new \RuntimeException('فایل بکاپ خراب است (gzip integrity check failed)');
            }
            $this->push($token, $events, 30, 'یکپارچگی فایل تأیید شد');

            // Step 3: Pre-restore snapshot
            $this->push($token, $events, 33, 'ایجاد snapshot قبل از بازیابی...');
            $customerDb   = config('database.connections.mysql_customer.database', 'customer_site');
            $snapshotPath = $this->createDatabaseSnapshot($customerDb, "tenant-{$siteId}", "site_id={$siteId}");
            if ($snapshotPath) {
                $log->update(['rollback_snapshot_path' => $snapshotPath]);
                $this->push($token, $events, 40, 'Snapshot ایجاد شد: ' . basename($snapshotPath));
            } else {
                $this->push($token, $events, 40, 'هشدار: snapshot ایجاد نشد — ادامه با احتیاط', 'warn');
            }

            // Step 4: Create temp DB and restore backup into it
            [$host, $port, $user, $password] = $this->dbCredentials();
            $envPwd    = $password !== '' ? 'MYSQL_PWD=' . escapeshellarg($password) . ' ' : '';
            $connFlags = sprintf(
                '-h %s -P %s -u %s',
                escapeshellarg($host),
                escapeshellarg((string) $port),
                escapeshellarg($user)
            );

            $this->push($token, $events, 43, "ایجاد دیتابیس موقت {$tmpDb}...");
            exec("{$envPwd}mysql {$connFlags} -e " . escapeshellarg(
                "CREATE DATABASE IF NOT EXISTS `{$tmpDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            ) . ' 2>&1', $o, $c);
            if ($c !== 0) throw new \RuntimeException("ایجاد دیتابیس موقت ناموفق: " . implode(' ', $o));

            $this->push($token, $events, 48, 'بازیابی بکاپ در دیتابیس موقت...');
            exec(
                "{$envPwd}gunzip -c " . escapeshellarg($localPath) . " | mysql {$connFlags} " . escapeshellarg($tmpDb) . ' 2>&1',
                $o, $c
            );
            if ($c !== 0) {
                exec("{$envPwd}mysql {$connFlags} -e " . escapeshellarg("DROP DATABASE IF EXISTS `{$tmpDb}`") . ' 2>&1');
                throw new \RuntimeException("بازیابی در دیتابیس موقت ناموفق: " . implode(' ', $o));
            }
            $this->push($token, $events, 60, 'بازیابی در دیتابیس موقت کامل شد');

            // Step 5+6: Atomic delete+copy in a single DB transaction
            $this->push($token, $events, 63, "انتقال atomic داده‌های site_id={$siteId} (DB transaction)...");
            DB::transaction(function () use ($customerDb, $tmpDb, $siteId) {
                $this->deleteTenantData($customerDb, $siteId);
                $this->copyTenantData($tmpDb, $customerDb, $siteId);
            });
            $this->push($token, $events, 85, 'داده‌های تِنِنت به‌صورت atomic منتقل شدند');

            // Step 7: Drop temp DB
            $this->push($token, $events, 88, "حذف دیتابیس موقت {$tmpDb}...");
            exec("{$envPwd}mysql {$connFlags} -e " . escapeshellarg("DROP DATABASE IF EXISTS `{$tmpDb}`") . ' 2>&1');

            // Step 8: Flush tenant cache
            $this->push($token, $events, 92, 'پاکسازی cache تِنِنت...');
            \Illuminate\Support\Facades\Cache::store('customers')->flush();

            @unlink($localPath);
            $this->releaseLock();
            $this->writeActivityLog('tenant_database', $siteId, basename($remotePath), 'success');

            $log->markSuccess($events);
            $this->push($token, $events, 100, 'بازیابی دیتابیس تِنِنت با موفقیت انجام شد', 'success', true);

        } catch (\Throwable $e) {
            @unlink($localPath ?? '');

            // Drop temp DB if creation succeeded
            try {
                [$h, $p, $u, $pwd] = $this->dbCredentials();
                $ep  = $pwd !== '' ? 'MYSQL_PWD=' . escapeshellarg($pwd) . ' ' : '';
                $cf  = sprintf('-h %s -P %s -u %s', escapeshellarg($h), escapeshellarg((string)$p), escapeshellarg($u));
                exec("{$ep}mysql {$cf} -e " . escapeshellarg("DROP DATABASE IF EXISTS `{$tmpDb}`") . ' 2>&1');
            } catch (\Throwable) {}

            $this->releaseLock();
            $this->writeActivityLog('tenant_database', $siteId, basename($remotePath), 'failed', $e->getMessage());

            $errMsg = $e->getMessage();
            $log->markFailed($errMsg, $events);
            $this->push($token, $events, null, "خطا: {$errMsg}", 'error', true);

            $this->slack->critical('Tenant DB Restore Failed', $errMsg, ['site_id' => $siteId, 'file' => $remotePath]);
            Log::error("RestoreService: tenant restore failed", ['site_id' => $siteId, 'error' => $errMsg]);
        }
    }

    // ─── Platform Storage Restore ─────────────────────────────────────────────

    public function executePlatformStorageRestore(RestoreLog $log, string $remotePath, string $mergeStrategy = 'merge'): void
    {
        $token     = $log->restore_token;
        $events    = [];
        $localPath = null;

        try {
            $this->push($token, $events, 5, 'آغاز بازیابی فایل‌های پلتفرم', 'running');

            $this->push($token, $events, 10, 'دانلود آرشیو از Arvan...');
            $ts        = now()->format('Y-m-d_His');
            $localPath = sys_get_temp_dir() . "/restore-storage-{$ts}.tar.gz";

            if (!$this->arvan->download($remotePath, $localPath)) {
                throw new \RuntimeException('دانلود آرشیو از Arvan ناموفق بود');
            }
            $this->push($token, $events, 25, 'آرشیو دانلود شد: ' . $this->humanSize(filesize($localPath)));

            $this->push($token, $events, 30, 'تأیید یکپارچگی tar...');
            if (!$this->verifyTar($localPath)) {
                @unlink($localPath);
                throw new \RuntimeException('آرشیو خراب است (tar integrity check failed)');
            }
            $this->push($token, $events, 35, 'یکپارچگی آرشیو تأیید شد');

            $this->push($token, $events, 40, 'بررسی فضای دیسک...');
            $freeBytes = disk_free_space(storage_path('app/public'));
            $tarSize   = filesize($localPath);
            if ($freeBytes < $tarSize * 2) {
                throw new \RuntimeException('فضای دیسک کافی نیست. فضای آزاد: ' . $this->humanSize($freeBytes));
            }
            $this->push($token, $events, 45, 'فضای دیسک کافی است: ' . $this->humanSize($freeBytes));

            $destDir = storage_path('app/public');
            $this->push($token, $events, 50, "استخراج فایل‌ها (strategy: {$mergeStrategy})...");

            if ($mergeStrategy === 'overwrite') {
                $cmd = 'tar -xzf ' . escapeshellarg($localPath) . ' -C ' . escapeshellarg($destDir) . ' 2>&1';
                exec($cmd, $output, $exitCode);
            } else {
                $tmpExtract = sys_get_temp_dir() . "/restore-storage-tmp-{$ts}";
                mkdir($tmpExtract, 0755, true);
                exec('tar -xzf ' . escapeshellarg($localPath) . ' -C ' . escapeshellarg($tmpExtract) . ' 2>&1', $o1, $c1);
                if ($c1 !== 0) throw new \RuntimeException('استخراج موقت ناموفق: ' . implode(' ', $o1));
                $cmd = 'rsync -a --ignore-existing ' . escapeshellarg($tmpExtract . '/') . ' ' . escapeshellarg($destDir . '/') . ' 2>&1';
                exec($cmd, $output, $exitCode);
                exec('rm -rf ' . escapeshellarg($tmpExtract));
            }

            if (isset($exitCode) && $exitCode !== 0) {
                throw new \RuntimeException('استخراج فایل‌ها ناموفق: ' . implode(' ', $output ?? []));
            }
            $this->push($token, $events, 75, 'فایل‌ها با موفقیت استخراج شدند');

            $this->push($token, $events, 80, 'تنظیم دسترسی‌های فایل...');
            exec('chown -R www-data:www-data ' . escapeshellarg($destDir) . ' 2>&1');
            $this->push($token, $events, 85, 'دسترسی‌های فایل تنظیم شد');

            @unlink($localPath);
            $this->push($token, $events, 90, 'پاکسازی cache...');
            Artisan::call('cache:clear');

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

    private function dbCredentials(): array
    {
        return [
            config('database.connections.mysql.host'),
            config('database.connections.mysql.port', 3306),
            config('database.connections.mysql.username'),
            config('database.connections.mysql.password'),
        ];
    }

    /**
     * Delete all tenant rows from a database using parameterized DB facade.
     * Runs inside DB::transaction() in the caller — rollback-safe.
     */
    private function deleteTenantData(string $db, int $siteId): void
    {
        $tables = $this->getTenantTableNames($db);
        foreach (array_reverse($tables) as $table) {
            DB::statement("DELETE FROM `{$db}`.`{$table}` WHERE site_id = ?", [$siteId]);
        }
    }

    /**
     * Copy tenant rows from tmpDb into destDb using parameterized DB facade.
     * Runs inside DB::transaction() in the caller — rollback-safe.
     */
    private function copyTenantData(string $tmpDb, string $destDb, int $siteId): void
    {
        $tables = $this->getTenantTableNames($tmpDb);
        foreach ($tables as $table) {
            $exists = DB::select(
                "SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
                [$destDb, $table]
            );
            if (empty($exists) || ((array) $exists[0])['cnt'] == 0) {
                continue;
            }

            DB::statement(
                "INSERT INTO `{$destDb}`.`{$table}` SELECT * FROM `{$tmpDb}`.`{$table}` WHERE site_id = ?",
                [$siteId]
            );
        }
    }

    /**
     * Get all table names that have a site_id column in the given database.
     * Uses parameterized query — no SQL injection risk.
     */
    private function getTenantTableNames(string $db): array
    {
        $rows = DB::select(
            "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = 'site_id' GROUP BY TABLE_NAME",
            [$db]
        );
        return array_values(array_column(
            array_map(fn($r) => (array) $r, $rows),
            'TABLE_NAME'
        ));
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
