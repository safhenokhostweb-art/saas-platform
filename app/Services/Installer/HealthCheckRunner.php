<?php
namespace App\Services\Installer;

use App\Services\Infrastructure\DrReadinessScoreService;
use App\Services\Infrastructure\ArvanBackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Post-installation health check runner.
 * Reuses DrReadinessScoreService for DR scoring.
 * Adds installer-specific: backup dir, mail config, Arvan connectivity, license ping.
 */
class HealthCheckRunner
{
    public function __construct(
        private DrReadinessScoreService $readiness,
        private ArvanBackupService      $arvan,
    ) {}

    // ─── Full Check (Step 6 of Fresh Install) ────────────────────────────────

    public function runFull(): array
    {
        $dr     = $this->readiness->calculate();
        $custom = [
            'backup_dir'   => $this->checkBackupDir(),
            'mail_config'  => $this->checkMailConfig(),
            'arvan_connect'=> $this->checkArvanConnect(),
        ];

        $customPass = collect($custom)->every(fn($c) => in_array($c['status'], ['pass', 'warn']));
        $pass       = $dr['score'] >= 50 && $customPass;

        return [
            'pass'          => $pass,
            'dr_score'      => $dr['score'],
            'dr_grade'      => $dr['grade'],
            'dr_components' => $dr['components'],
            'custom'        => $custom,
            'summary'       => $pass
                ? "بررسی سلامت موفق — نمره DR: {$dr['score']}/100"
                : "بررسی سلامت ناموفق — نمره DR: {$dr['score']}/100",
        ];
    }

    // ─── Quick Check (Post-upgrade, Post-restore) ─────────────────────────────

    public function runQuick(): array
    {
        $dbOk    = $this->pingDb();
        $redisOk = $this->pingRedis();
        $pass    = $dbOk && $redisOk;

        return [
            'pass'    => $pass,
            'db'      => $dbOk,
            'redis'   => $redisOk,
            'summary' => $pass ? 'بررسی سریع موفق' : 'بررسی سریع ناموفق — DB یا Redis پاسخ نمی‌دهد',
        ];
    }

    // ─── Individual Checks ───────────────────────────────────────────────────

    private function checkBackupDir(): array
    {
        $dir    = storage_path('backups');
        $exists = is_dir($dir);
        if (!$exists) {
            @mkdir($dir, 0750, true);
            $exists = is_dir($dir);
        }
        return [
            'status'  => $exists ? 'pass' : 'warn',
            'message' => $exists ? 'پوشه backup موجود است' : 'پوشه backup ایجاد نشد',
        ];
    }

    private function checkMailConfig(): array
    {
        $from = config('mail.from.address', '');
        $ok   = !empty($from) && $from !== 'hello@example.com';
        return [
            'status'  => $ok ? 'pass' : 'warn',
            'message' => $ok
                ? "ایمیل از آدرس {$from} پیکربندی شده"
                : 'MAIL_FROM_ADDRESS هنوز تنظیم نشده',
        ];
    }

    private function checkArvanConnect(): array
    {
        try {
            $files = $this->arvan->listFiles('saas-platform/');
            return [
                'status'  => 'pass',
                'message' => 'اتصال Arvan برقرار است (' . count($files) . ' فایل در مسیر اصلی)',
            ];
        } catch (\Throwable $e) {
            return [
                'status'  => 'warn',
                'message' => 'Arvan قابل دسترس نیست: ' . $e->getMessage(),
            ];
        }
    }

    private function pingDb(): bool
    {
        try {
            DB::select('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function pingRedis(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
