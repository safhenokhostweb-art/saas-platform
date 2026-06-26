<?php
namespace App\Services\Infrastructure;

use App\Models\Infrastructure\DrSimulation;
use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Infrastructure\InfraSchedulerRun;
use App\Models\Infrastructure\RestoreLog;
use App\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

/**
 * Calculates a weighted Recovery Readiness Score (0–100) from 10 components.
 *
 * Weights:
 *   platform_db_backup       20
 *   platform_storage_backup  10
 *   tenant_backups           10
 *   arvan_sync               15
 *   restore_logs             10
 *   queue                    10
 *   scheduler                10
 *   redis                     5
 *   disk_space                5
 *   latest_simulation         5
 *   ──────────────────────  100
 */
class DrReadinessScoreService
{
    public function __construct(private ArvanBackupService $arvan) {}

    public function calculate(): array
    {
        $components = [
            'platform_db_backup'      => $this->evalPlatformDb(),
            'platform_storage_backup' => $this->evalPlatformStorage(),
            'tenant_backups'          => $this->evalTenantBackups(),
            'arvan_sync'              => $this->evalArvanSync(),
            'restore_logs'            => $this->evalRestoreLogs(),
            'queue'                   => $this->evalQueue(),
            'scheduler'               => $this->evalScheduler(),
            'redis'                   => $this->evalRedis(),
            'disk_space'              => $this->evalDiskSpace(),
            'latest_simulation'       => $this->evalLatestSimulation(),
        ];

        $score = min(100, array_sum(array_column($components, 'score')));

        return [
            'score'      => $score,
            'components' => $components,
            'grade'      => $this->grade($score),
            'color'      => $this->gradeColor($score),
            'issues'     => $this->extractIssues($components),
        ];
    }

    // ─── Component Evaluations ─────────────────────────────────────────────────

    private function evalPlatformDb(): array
    {
        $run = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'database')
            ->where('status', 'success')
            ->orderByDesc('started_at')
            ->first();

        if (!$run?->completed_at) {
            return $this->comp('بکاپ DB پلتفرم', 0, 20, 'MISSING', 'هیچ بکاپ موفقی یافت نشد');
        }

        $hours = $run->completed_at->diffInHours(now());

        if ($hours < 24) {
            return $this->comp('بکاپ DB پلتفرم', 20, 20, 'OK', "آخرین بکاپ {$hours} ساعت پیش");
        }
        if ($hours < 48) {
            return $this->comp('بکاپ DB پلتفرم', 15, 20, 'WARN', "آخرین بکاپ {$hours} ساعت پیش");
        }
        if ($hours < 168) {
            return $this->comp('بکاپ DB پلتفرم', 5, 20, 'STALE', 'آخرین بکاپ ' . round($hours / 24) . ' روز پیش');
        }

        return $this->comp('بکاپ DB پلتفرم', 0, 20, 'CRITICAL', 'آخرین بکاپ بیش از ۷ روز پیش');
    }

    private function evalPlatformStorage(): array
    {
        $run = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'storage')
            ->where('status', 'success')
            ->orderByDesc('started_at')
            ->first();

        if (!$run?->completed_at) {
            return $this->comp('بکاپ Storage پلتفرم', 0, 10, 'MISSING', 'هیچ بکاپ موفقی یافت نشد');
        }

        $days = $run->completed_at->diffInDays(now());

        if ($days < 7) {
            return $this->comp('بکاپ Storage پلتفرم', 10, 10, 'OK', "آخرین بکاپ {$days} روز پیش");
        }

        return $this->comp('بکاپ Storage پلتفرم', 0, 10, 'STALE', "آخرین بکاپ {$days} روز پیش");
    }

    private function evalTenantBackups(): array
    {
        $totalSites = Site::where('lifecycle_status', 'active')
            ->orWhereNull('lifecycle_status')
            ->count();

        if ($totalSites === 0) {
            return $this->comp('بکاپ تِنِنت‌ها', 10, 10, 'OK', 'هیچ تِنِنتی وجود ندارد');
        }

        $sitesWithBackup = InfraBackupRun::select('site_id')
            ->whereNotNull('site_id')
            ->where('backup_type', 'database')
            ->where('status', 'success')
            ->where('started_at', '>=', now()->subDays(7))
            ->distinct()
            ->count('site_id');

        $pct = $sitesWithBackup / $totalSites;

        if ($pct >= 0.8) {
            return $this->comp('بکاپ تِنِنت‌ها', 10, 10, 'OK', "{$sitesWithBackup} از {$totalSites} تِنِنت");
        }
        if ($pct >= 0.5) {
            return $this->comp('بکاپ تِنِنت‌ها', 6, 10, 'WARN', "{$sitesWithBackup} از {$totalSites} تِنِنت");
        }

        return $this->comp('بکاپ تِنِنت‌ها', 2, 10, 'CRITICAL', "{$sitesWithBackup} از {$totalSites} تِنِنت");
    }

    private function evalArvanSync(): array
    {
        $dbRuns7d = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'database')
            ->where('status', 'success')
            ->where('started_at', '>=', now()->subDays(7))
            ->count();

        $arvanFiles  = Cache::remember('dr_arvan_platform_db', 300, fn() =>
            $this->arvan->listFiles('saas-platform/database/'));

        $arvanLast7d = collect($arvanFiles)
            ->filter(fn($f) => ($f['last_modified'] ?? 0) >= now()->subDays(7)->timestamp)
            ->count();

        if ($dbRuns7d === 0) {
            return $this->comp('Arvan Sync', 5, 15, 'WARN', 'هیچ بکاپ DB در ۷ روز اخیر ثبت نشده');
        }

        $pct = $arvanLast7d / $dbRuns7d;

        if ($pct >= 1.0) {
            return $this->comp('Arvan Sync', 15, 15, 'OK', "همه {$dbRuns7d} بکاپ در Arvan موجودند");
        }
        if ($pct >= 0.8) {
            return $this->comp('Arvan Sync', 10, 15, 'WARN', "{$arvanLast7d} از {$dbRuns7d} بکاپ در Arvan");
        }

        return $this->comp('Arvan Sync', 5, 15, 'CRITICAL', "{$arvanLast7d} از {$dbRuns7d} بکاپ در Arvan");
    }

    private function evalRestoreLogs(): array
    {
        $hasRestore = RestoreLog::where('created_at', '>=', now()->subDays(30))
            ->where(fn($q) => $q->where('status', 'success')->orWhere('status', 'dry_run_complete'))
            ->exists();

        if ($hasRestore) {
            return $this->comp('لاگ بازیابی', 10, 10, 'OK', 'حداقل یک بازیابی موفق در ۳۰ روز اخیر');
        }

        return $this->comp('لاگ بازیابی', 0, 10, 'MISSING', 'هیچ بازیابی در ۳۰ روز اخیر انجام نشده');
    }

    private function evalQueue(): array
    {
        // If a backup completed with a recorded duration in the last 2 days, queue is operational
        $hasCompleted = InfraBackupRun::whereNotNull('duration_seconds')
            ->where('started_at', '>=', now()->subDays(2))
            ->exists();

        if ($hasCompleted) {
            return $this->comp('Queue', 10, 10, 'OK', 'Job های بکاپ با موفقیت کامل شده‌اند');
        }

        return $this->comp('Queue', 5, 10, 'UNKNOWN', 'نمی‌توان وضعیت Queue را از بکاپ‌های اخیر تأیید کرد');
    }

    private function evalScheduler(): array
    {
        // Check InfraSchedulerRun if table exists
        if (Schema::hasTable('infra_scheduler_runs')) {
            $lastRun = InfraSchedulerRun::where('status', 'success')
                ->orderByDesc('started_at')
                ->first();

            if ($lastRun?->started_at?->gte(now()->subHours(25))) {
                return $this->comp('Scheduler', 10, 10, 'OK',
                    'آخرین اجرا ' . $lastRun->started_at->diffForHumans());
            }
        }

        // Fallback: a successful backup in last 25h implies scheduler ran
        $hasRecentBackup = InfraBackupRun::whereNull('site_id')
            ->where('status', 'success')
            ->where('started_at', '>=', now()->subHours(25))
            ->exists();

        if ($hasRecentBackup) {
            return $this->comp('Scheduler', 10, 10, 'OK', 'بکاپ اخیر نشان‌دهنده اجرای Scheduler');
        }

        return $this->comp('Scheduler', 5, 10, 'UNKNOWN', 'نمی‌توان وضعیت Scheduler را تأیید کرد');
    }

    private function evalRedis(): array
    {
        try {
            Redis::ping();
            return $this->comp('Redis', 5, 5, 'OK', 'Redis در دسترس است');
        } catch (\Throwable) {
            return $this->comp('Redis', 0, 5, 'CRITICAL', 'Redis در دسترس نیست');
        }
    }

    private function evalDiskSpace(): array
    {
        try {
            $path  = storage_path();
            $total = disk_total_space($path);
            $free  = disk_free_space($path);

            if ($total <= 0) {
                throw new \RuntimeException('invalid disk info');
            }

            $usedPct = (($total - $free) / $total) * 100;
            $label   = round($usedPct, 1) . '% استفاده شده';

            if ($usedPct < 80) {
                return $this->comp('فضای دیسک', 5, 5, 'OK', $label);
            }
            if ($usedPct < 90) {
                return $this->comp('فضای دیسک', 3, 5, 'WARN', $label);
            }

            return $this->comp('فضای دیسک', 0, 5, 'CRITICAL', $label);
        } catch (\Throwable) {
            return $this->comp('فضای دیسک', 3, 5, 'UNKNOWN', 'اطلاعات دیسک در دسترس نیست');
        }
    }

    private function evalLatestSimulation(): array
    {
        if (!Schema::hasTable('dr_simulations')) {
            return $this->comp('شبیه‌سازی بازیابی', 0, 5, 'MISSING', 'جدول شبیه‌سازی ایجاد نشده');
        }

        $last = DrSimulation::orderByDesc('created_at')->first();

        if (!$last) {
            return $this->comp('شبیه‌سازی بازیابی', 0, 5, 'MISSING', 'هیچ شبیه‌سازی‌ای انجام نشده');
        }

        $days = $last->created_at->diffInDays(now());

        if ($days <= 30) {
            return $this->comp('شبیه‌سازی بازیابی', 5, 5, 'OK',
                "آخرین شبیه‌سازی {$days} روز پیش: {$last->result}");
        }
        if ($days <= 90) {
            return $this->comp('شبیه‌سازی بازیابی', 2, 5, 'WARN',
                "آخرین شبیه‌سازی {$days} روز پیش");
        }

        return $this->comp('شبیه‌سازی بازیابی', 0, 5, 'CRITICAL',
            "آخرین شبیه‌سازی {$days} روز پیش — شبیه‌سازی مجدد توصیه می‌شود");
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function comp(string $label, int $score, int $max, string $status, string $detail): array
    {
        return compact('label', 'score', 'max', 'status', 'detail');
    }

    private function grade(int $score): string
    {
        return match(true) {
            $score >= 90 => 'عالی',
            $score >= 75 => 'خوب',
            $score >= 50 => 'متوسط',
            default      => 'بحرانی',
        };
    }

    private function gradeColor(int $score): string
    {
        return match(true) {
            $score >= 90 => 'green',
            $score >= 75 => 'blue',
            $score >= 50 => 'amber',
            default      => 'red',
        };
    }

    private function extractIssues(array $components): array
    {
        $issues = [];
        foreach ($components as $comp) {
            if (in_array($comp['status'], ['MISSING', 'CRITICAL', 'STALE'], true)) {
                $issues[] = $comp['label'] . ': ' . $comp['detail'];
            }
        }
        return $issues;
    }
}
