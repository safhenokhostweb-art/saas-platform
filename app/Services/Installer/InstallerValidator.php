<?php
namespace App\Services\Installer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class InstallerValidator
{
    const PHP_MIN_VERSION = '8.1.0';

    const REQUIRED_EXTENSIONS = [
        'pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer',
        'json', 'curl', 'zip', 'gd', 'xml', 'intl', 'bcmath',
        'fileinfo',
    ];

    const RECOMMENDED_EXTENSIONS = ['redis', 'imagick'];

    const MIN_DISK_BYTES = 2 * 1024 * 1024 * 1024; // 2 GB

    // ─── Run All Checks ──────────────────────────────────────────────────────

    public function runAll(): array
    {
        return [
            'php_version' => $this->checkPhpVersion(),
            'extensions'  => $this->checkExtensions(),
            'disk_space'  => $this->checkDiskSpace(),
            'redis'       => $this->checkRedis(),
            'mysql'       => $this->checkMysql(),
            'queue'       => $this->checkQueue(),
            'storage'     => $this->checkStoragePermissions(),
            'ssl'         => $this->checkSsl(),
            'mail'        => $this->checkMail(),
            'scheduler'   => $this->checkScheduler(),
        ];
    }

    // ─── Individual Checks ───────────────────────────────────────────────────

    public function checkPhpVersion(): array
    {
        $current = PHP_VERSION;
        $pass    = version_compare($current, self::PHP_MIN_VERSION, '>=');
        return [
            'label'    => 'نسخه PHP',
            'status'   => $pass ? 'pass' : 'fail',
            'value'    => $current,
            'required' => self::PHP_MIN_VERSION . '+',
            'message'  => $pass
                ? "PHP {$current} ✓"
                : "نیاز به PHP " . self::PHP_MIN_VERSION . "+ (نصب‌شده: {$current})",
        ];
    }

    public function checkExtensions(): array
    {
        $missing     = [];
        $recommended = [];

        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        foreach (self::RECOMMENDED_EXTENSIONS as $ext) {
            if (!extension_loaded($ext)) {
                $recommended[] = $ext;
            }
        }

        $pass = empty($missing);
        return [
            'label'       => 'افزونه‌های PHP',
            'status'      => $pass ? (empty($recommended) ? 'pass' : 'warn') : 'fail',
            'missing'     => $missing,
            'recommended' => $recommended,
            'message'     => $pass
                ? (empty($recommended)
                    ? 'همه افزونه‌های مورد نیاز نصب هستند'
                    : 'افزونه‌های اختیاری مفقود: ' . implode(', ', $recommended))
                : 'افزونه‌های ضروری مفقود: ' . implode(', ', $missing),
        ];
    }

    public function checkDiskSpace(): array
    {
        $free  = disk_free_space(base_path());
        $total = disk_total_space(base_path());

        if ($free === false) {
            return ['label' => 'فضای دیسک', 'status' => 'warn', 'message' => 'اندازه‌گیری فضای دیسک ممکن نیست'];
        }

        $pass      = $free >= self::MIN_DISK_BYTES;
        $freeHuman = $this->formatBytes((int) $free);

        return [
            'label'   => 'فضای دیسک',
            'status'  => $pass ? 'pass' : ($free >= 1 * 1024 * 1024 * 1024 ? 'warn' : 'fail'),
            'value'   => $freeHuman,
            'total'   => $total ? $this->formatBytes((int) $total) : 'نامشخص',
            'message' => $pass
                ? "فضای آزاد: {$freeHuman} ✓"
                : "فضای آزاد ({$freeHuman}) کمتر از ۲ گیگابایت توصیه‌شده است",
        ];
    }

    public function checkRedis(): array
    {
        try {
            Redis::ping();
            return [
                'label'   => 'Redis',
                'status'  => 'pass',
                'message' => 'اتصال Redis برقرار است ✓',
            ];
        } catch (\Throwable $e) {
            return [
                'label'   => 'Redis',
                'status'  => 'fail',
                'message' => 'خطا در اتصال Redis: ' . $e->getMessage(),
            ];
        }
    }

    public function checkMysql(): array
    {
        try {
            DB::select('SELECT 1');
            $version = DB::select('SELECT VERSION() AS v')[0]->v ?? 'نامشخص';
            return [
                'label'   => 'MySQL',
                'status'  => 'pass',
                'value'   => $version,
                'message' => "اتصال MySQL برقرار است — نسخه {$version} ✓",
            ];
        } catch (\Throwable $e) {
            return [
                'label'   => 'MySQL',
                'status'  => 'fail',
                'message' => 'خطا در اتصال MySQL: ' . $e->getMessage(),
            ];
        }
    }

    public function checkQueue(): array
    {
        $driver = config('queue.default', 'sync');
        $good   = in_array($driver, ['redis', 'database']);
        return [
            'label'   => 'صف (Queue)',
            'status'  => $good ? 'pass' : 'warn',
            'value'   => $driver,
            'message' => $good
                ? "درایور صف: {$driver} ✓"
                : "درایور صف: {$driver} — برای production توصیه می‌شود از redis استفاده شود",
        ];
    }

    public function checkStoragePermissions(): array
    {
        $dirs   = [storage_path(), base_path('bootstrap/cache')];
        $failed = array_filter($dirs, fn($d) => !is_writable($d));
        $pass   = empty($failed);
        return [
            'label'   => 'مجوز دسترسی',
            'status'  => $pass ? 'pass' : 'fail',
            'message' => $pass
                ? 'مجوز نوشتن برای همه مسیرها برقرار است ✓'
                : 'مجوز نوشتن ندارید: ' . implode(', ', $failed),
        ];
    }

    public function checkSsl(): array
    {
        $url     = config('app.url', '');
        $isHttps = str_starts_with($url, 'https://');
        return [
            'label'   => 'SSL / HTTPS',
            'status'  => $isHttps ? 'pass' : 'warn',
            'value'   => $url,
            'message' => $isHttps
                ? 'APP_URL با HTTPS پیکربندی شده ✓'
                : 'APP_URL بدون HTTPS است — برای production توصیه می‌شود SSL فعال شود',
        ];
    }

    public function checkMail(): array
    {
        $mailer = config('mail.default', 'smtp');
        $from   = config('mail.from.address', '');
        $host   = config('mail.mailers.smtp.host', '');
        $ok     = !empty($from) && $from !== 'hello@example.com';
        return [
            'label'   => 'تنظیمات ایمیل',
            'status'  => $ok ? 'pass' : 'warn',
            'value'   => $mailer,
            'message' => $ok
                ? "ایمیل از {$from} پیکربندی شده ✓"
                : 'MAIL_FROM_ADDRESS تنظیم نشده — ایمیل‌ها ارسال نخواهند شد',
        ];
    }

    public function checkScheduler(): array
    {
        // Heuristic: check if last_scheduled_at is recent in cache
        $lastRun = cache('scheduler_last_run');
        if ($lastRun && now()->diffInMinutes($lastRun) <= 2) {
            return [
                'label'   => 'زمان‌بند (Cron)',
                'status'  => 'pass',
                'message' => 'زمان‌بند فعال است ✓',
            ];
        }
        return [
            'label'   => 'زمان‌بند (Cron)',
            'status'  => 'warn',
            'message' => 'وضعیت زمان‌بند قابل تأیید نیست — مطمئن شوید Cron هر دقیقه اجرا می‌شود',
        ];
    }

    // ─── Aggregates ──────────────────────────────────────────────────────────

    public function hasCriticalFailures(array $results): bool
    {
        $critical = ['php_version', 'extensions', 'mysql', 'storage'];
        foreach ($critical as $key) {
            if (isset($results[$key]) && ($results[$key]['status'] ?? '') === 'fail') {
                return true;
            }
        }
        return false;
    }

    public function allPassed(array $results): bool
    {
        foreach ($results as $check) {
            if (($check['status'] ?? '') === 'fail') {
                return false;
            }
        }
        return true;
    }

    public function summary(array $results): array
    {
        $pass = $warn = $fail = 0;
        foreach ($results as $r) {
            match ($r['status'] ?? 'warn') {
                'pass'  => $pass++,
                'warn'  => $warn++,
                default => $fail++,
            };
        }
        return ['pass' => $pass, 'warn' => $warn, 'fail' => $fail];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 4) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
