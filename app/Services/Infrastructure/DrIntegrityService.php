<?php
namespace App\Services\Infrastructure;

use App\Models\Infrastructure\InfraBackupRun;

class DrIntegrityService
{
    public function __construct(private ArvanBackupService $arvan) {}

    /**
     * Check platform backup integrity against Arvan.
     *
     * @return array{db: array, storage: array, checked_at: string}
     */
    public function checkPlatform(): array
    {
        return [
            'db'         => $this->checkPlatformDb(),
            'storage'    => $this->checkPlatformStorage(),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Check tenant backup integrity against Arvan.
     *
     * @return array{db: array, storage: array, checked_at: string}
     */
    public function checkTenant(int $siteId): array
    {
        return [
            'db'         => $this->checkTenantDb($siteId),
            'storage'    => $this->checkTenantStorage($siteId),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    // ─── Platform ─────────────────────────────────────────────────────────────

    private function checkPlatformDb(): array
    {
        $latestRun = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'database')
            ->where('status', 'success')
            ->orderByDesc('started_at')
            ->first();

        $arvanFiles = $this->arvan->listFiles('saas-platform/database/');
        $arvanCount = count($arvanFiles);

        $dbCount7d  = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'database')
            ->where('status', 'success')
            ->where('started_at', '>=', now()->subDays(7))
            ->count();

        if (!$latestRun) {
            return [
                'status'       => 'MISSING',
                'message'      => 'هیچ بکاپ موفقی در دیتابیس ثبت نشده',
                'arvan_count'  => $arvanCount,
                'db_count_7d'  => 0,
                'latest_file'  => null,
                'size_match'   => null,
            ];
        }

        $filename    = basename($latestRun->file_path ?? '');
        $remotePath  = "saas-platform/database/{$filename}";
        $filePresent = $filename && $this->arvan->exists($remotePath);

        $remoteSize  = $filePresent ? $this->arvan->size($remotePath) : null;
        $localSizeMb = $latestRun->file_size_mb;
        $sizeMatch   = null;
        if ($remoteSize !== null && $localSizeMb !== null) {
            $remoteMb  = round($remoteSize / 1048576, 2);
            $sizeMatch = abs($remoteMb - $localSizeMb) < 1.0; // 1 MB tolerance
        }

        $status = match(true) {
            !$filePresent               => 'ARVAN_MISSING',
            $sizeMatch === false        => 'SIZE_MISMATCH',
            default                     => 'INTEGRITY_OK',
        };

        return [
            'status'       => $status,
            'message'      => $this->statusMessage($status),
            'arvan_count'  => $arvanCount,
            'db_count_7d'  => $dbCount7d,
            'latest_file'  => $filename ?: null,
            'latest_run'   => $latestRun->completed_at?->toIso8601String(),
            'size_match'   => $sizeMatch,
            'local_size_mb'=> $localSizeMb,
            'remote_size_b'=> $remoteSize,
        ];
    }

    private function checkPlatformStorage(): array
    {
        $latestRun  = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'storage')
            ->where('status', 'success')
            ->orderByDesc('started_at')
            ->first();

        $arvanFiles = $this->arvan->listFiles('saas-platform/storage/');
        $arvanCount = count($arvanFiles);

        if (!$latestRun) {
            return [
                'status'      => 'MISSING',
                'message'     => 'هیچ بکاپ Storage موفقی ثبت نشده',
                'arvan_count' => $arvanCount,
                'latest_file' => null,
            ];
        }

        $filename   = basename($latestRun->file_path ?? '');
        $remotePath = "saas-platform/storage/{$filename}";
        $present    = $filename && $this->arvan->exists($remotePath);
        $status     = $present ? 'INTEGRITY_OK' : 'ARVAN_MISSING';

        return [
            'status'       => $status,
            'message'      => $this->statusMessage($status),
            'arvan_count'  => $arvanCount,
            'latest_file'  => $filename ?: null,
            'latest_run'   => $latestRun->completed_at?->toIso8601String(),
        ];
    }

    // ─── Tenant ───────────────────────────────────────────────────────────────

    private function checkTenantDb(int $siteId): array
    {
        $latestRun  = InfraBackupRun::where('site_id', $siteId)
            ->where('backup_type', 'database')
            ->where('status', 'success')
            ->orderByDesc('started_at')
            ->first();

        $prefix     = "customer-sites/tenant-{$siteId}/database/";
        $arvanFiles = $this->arvan->listFiles($prefix);
        $arvanCount = count($arvanFiles);

        if (!$latestRun) {
            return [
                'status'      => 'MISSING',
                'message'     => 'بکاپ دیتابیس برای این تِنِنت یافت نشد (انتظار می‌رود — بکاپ تِنِنت هنوز پیاده‌سازی نشده)',
                'arvan_count' => $arvanCount,
                'latest_file' => null,
            ];
        }

        $filename    = basename($latestRun->file_path ?? '');
        $remotePath  = $prefix . $filename;
        $filePresent = $filename && $this->arvan->exists($remotePath);
        $status      = $filePresent ? 'INTEGRITY_OK' : 'ARVAN_MISSING';

        return [
            'status'      => $status,
            'message'     => $this->statusMessage($status),
            'arvan_count' => $arvanCount,
            'latest_file' => $filename ?: null,
            'latest_run'  => $latestRun->completed_at?->toIso8601String(),
        ];
    }

    private function checkTenantStorage(int $siteId): array
    {
        $prefix     = "customer-sites/tenant-{$siteId}/storage/";
        $arvanFiles = $this->arvan->listFiles($prefix);

        return [
            'status'      => count($arvanFiles) > 0 ? 'INTEGRITY_OK' : 'MISSING',
            'message'     => count($arvanFiles) > 0 ? 'فایل‌ها در Arvan موجودند' : 'هیچ فایل Storage در Arvan یافت نشد',
            'arvan_count' => count($arvanFiles),
            'latest_file' => $arvanFiles[0]['filename'] ?? null,
        ];
    }

    private function statusMessage(string $status): string
    {
        return match($status) {
            'INTEGRITY_OK'  => 'یکپارچگی تأیید شد',
            'ARVAN_MISSING' => 'فایل در Arvan یافت نشد',
            'SIZE_MISMATCH' => 'اندازه فایل با رکورد دیتابیس مطابقت ندارد',
            'MISSING'       => 'هیچ بکاپ موفقی ثبت نشده',
            default         => $status,
        };
    }
}
