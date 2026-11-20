<?php

namespace App\Services\Infrastructure;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\InfraServer;
use App\Models\Site;
use App\Models\SiteResourceLimit;
use Illuminate\Support\Facades\Cache;

/**
 * Server Capacity & Resource Governance Governor.
 *
 * 2026-11-19 (P1): the guard is real now.
 *  - Figures come from the TARGET server (ServerResourceProbe), falling back
 *    to the registered InfraServer specs. Nothing is invented: unknown specs
 *    fail the allocation closed (except on panel-managed hosting, where the
 *    panel enforces its own quotas).
 *  - Overcommit of RAM, CPU or hard-disk quotas is an ERROR, not a warning.
 *  - Allocated quotas count every site on the server PLUS reservations held
 *    by installations still in flight (pending/running), so two onboardings
 *    can no longer each pass against the same headroom. SiteOnboardingService
 *    serialises validate+create per server with a lock.
 */
class ServerCapacityService
{
    /** Safe buffer percentage: Keep at least 15% physical RAM untouched */
    public const RAM_SAFETY_BUFFER_PERCENT = 15;

    /** Safe buffer percentage: Keep at least 20% physical Disk free */
    public const DISK_SAFETY_BUFFER_PERCENT = 20;

    /** Maximum allowed thin-provisioning overcommit ratio for RAM */
    public const MAX_RAM_OVERCOMMIT_RATIO = 2.0;

    /** Maximum allowed overcommit ratio for CPU cores (time-shared) */
    public const MAX_CPU_OVERCOMMIT_RATIO = 4.0;

    /** Default Soft Disk ratio: 70% of Hard Disk */
    public const DEFAULT_SOFT_DISK_RATIO = 0.70;

    /** Shared hosting whose quotas are enforced by the control panel itself. */
    public const PANEL_MANAGED_TYPES = ['cpanel', 'directadmin'];

    private const DEFAULT_SITE_RAM_MB = 1024;
    private const DEFAULT_SITE_DISK_GB = 5.0;
    private const DEFAULT_SITE_CPU = 1.0;

    public function __construct(
        private readonly ServerResourceProbe $probe,
    ) {}

    /**
     * Inspect live capacity, active site quotas, reservations and available headroom of a target server.
     *
     * @return array<string, mixed>
     */
    public function getCapacity(CustomerRemoteServer $server, bool $fresh = false): array
    {
        $cacheKey = "server_capacity:{$server->id}";
        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 15, function () use ($server) {
            return $this->inspectServerCapacity($server);
        });
    }

    /**
     * Inspect live metrics and calculate pool metrics.
     */
    private function inspectServerCapacity(CustomerRemoteServer $server): array
    {
        $panelManaged = in_array(strtolower((string) $server->type), self::PANEL_MANAGED_TYPES, true);

        $infra = InfraServer::where('ip_address', $server->host)
            ->orWhere('hostname', $server->host)
            ->first();
        $live = $this->probe->probe($server);

        $physicalRamMb  = $live['ram_total_mb'] ?? ($infra?->ram_gb ? (int) ($infra->ram_gb * 1024) : null);
        $physicalCores  = $live['cpu_cores'] ?? ($infra?->cpu_cores ? (float) $infra->cpu_cores : null);
        $physicalDiskGb = $live['disk_total_gb'] ?? ($infra?->disk_gb ? (float) $infra->disk_gb : null);
        $freeRamMb      = $live['ram_available_mb'] ?? null;
        $freeDiskGb     = $live['disk_free_gb'] ?? null;
        $specsKnown     = $physicalRamMb !== null && $physicalCores !== null && $physicalDiskGb !== null;
        $source         = $live !== null ? 'live_probe' : ($infra !== null ? 'registered_specs' : 'unknown');

        // Allocated: every site whose installation targets this server.
        $siteIds = CustomerInstallation::where('remote_server_id', $server->id)
            ->whereNotNull('site_id')
            ->pluck('site_id')
            ->unique()
            ->all();

        $allocatedRamMb = 0;
        $allocatedHardDiskGb = 0.0;
        $allocatedSoftDiskGb = 0.0;
        $allocatedCpuCores = 0.0;
        $limitedSiteIds = [];

        foreach (SiteResourceLimit::whereIn('site_id', $siteIds)->get() as $lim) {
            $limitedSiteIds[] = (int) $lim->site_id;
            $siteHardGb = (float) (($lim->disk_hard_limit_bytes ?: $lim->storage_limit_bytes ?: (int) (self::DEFAULT_SITE_DISK_GB * 1024 ** 3)) / (1024 ** 3));
            $allocatedRamMb      += (int) (($lim->memory_limit_bytes ?: self::DEFAULT_SITE_RAM_MB * 1024 ** 2) / (1024 ** 2));
            $allocatedHardDiskGb += $siteHardGb;
            // Per-site fallback (was: the running total of all sites, which
            // over-counted soft allocation quadratically).
            $allocatedSoftDiskGb += (float) ($lim->disk_soft_limit_bytes
                ? $lim->disk_soft_limit_bytes / (1024 ** 3)
                : $siteHardGb * self::DEFAULT_SOFT_DISK_RATIO);
            $allocatedCpuCores   += (float) ($lim->cpu_cores_limit ?: self::DEFAULT_SITE_CPU);
        }

        // Reserved: installations still in flight on this server whose site
        // has no SiteResourceLimit yet (written late, at PROVISION_LICENSE).
        $reservedRamMb = 0;
        $reservedDiskGb = 0.0;
        $reservedCpu = 0.0;
        $reservations = 0;
        $inFlight = CustomerInstallation::where('remote_server_id', $server->id)
            ->whereIn('status', ['pending', 'running'])
            ->get(['id', 'site_id', 'request_payload']);
        foreach ($inFlight as $inst) {
            if ($inst->site_id !== null && in_array((int) $inst->site_id, $limitedSiteIds, true)) {
                continue; // already counted as allocated
            }
            $payload = (array) $inst->request_payload;
            $reservedRamMb  += (int) ($payload['ram_mb'] ?? self::DEFAULT_SITE_RAM_MB);
            $reservedDiskGb += (float) ($payload['disk_gb'] ?? self::DEFAULT_SITE_DISK_GB);
            $reservedCpu    += (float) ($payload['cpu_cores'] ?? self::DEFAULT_SITE_CPU);
            $reservations++;
        }

        $committedRamMb  = $allocatedRamMb + $reservedRamMb;
        $committedDiskGb = $allocatedHardDiskGb + $reservedDiskGb;
        $committedCpu    = $allocatedCpuCores + $reservedCpu;

        $ramOvercommitRatio = $physicalRamMb ? round($committedRamMb / $physicalRamMb, 2) : null;
        $cpuOvercommitRatio = $physicalCores ? round($committedCpu / $physicalCores, 2) : null;
        $allocatableDiskGb  = $physicalDiskGb !== null ? round($physicalDiskGb * (1 - self::DISK_SAFETY_BUFFER_PERCENT / 100), 2) : null;
        $safeAvailableRamMb = ($freeRamMb !== null && $physicalRamMb !== null)
            ? max(0, (int) ($freeRamMb - ($physicalRamMb * (self::RAM_SAFETY_BUFFER_PERCENT / 100))))
            : null;
        $safeAvailableDiskGb = ($freeDiskGb !== null && $physicalDiskGb !== null)
            ? max(0, round($freeDiskGb - ($physicalDiskGb * (self::DISK_SAFETY_BUFFER_PERCENT / 100)), 2))
            : null;
        $quotaHeadroomDiskGb = $allocatableDiskGb !== null ? max(0, round($allocatableDiskGb - $committedDiskGb, 2)) : null;

        $hasHeadroom = $specsKnown
            && ($safeAvailableRamMb === null || $safeAvailableRamMb > 512)
            && ($safeAvailableDiskGb === null || $safeAvailableDiskGb > 2.0)
            && ($quotaHeadroomDiskGb === null || $quotaHeadroomDiskGb > 2.0)
            && $ramOvercommitRatio <= self::MAX_RAM_OVERCOMMIT_RATIO
            && $cpuOvercommitRatio <= self::MAX_CPU_OVERCOMMIT_RATIO;

        $activeSitesCount = count($siteIds);

        return [
            'server'                 => [
                'id'    => $server->id,
                'label' => $server->label,
                'host'  => $server->host,
                'type'  => $server->type,
            ],
            'server_id'              => $server->id,
            'server_label'           => $server->label,
            'server_host'            => $server->host,
            'server_type'            => $server->type,
            'panel_managed'          => $panelManaged,
            'specs_known'            => $specsKnown,
            'metrics_source'         => $source,
            'has_headroom'           => $hasHeadroom,
            'sites_count'            => $activeSitesCount,
            'active_sites_count'     => $activeSitesCount,
            'reservations_count'     => $reservations,
            'ram'                    => [
                'total_mb'             => $physicalRamMb,
                'free_mb'              => $freeRamMb,
                'allocated_mb'         => $allocatedRamMb,
                'reserved_mb'          => $reservedRamMb,
                'safe_available_mb'    => $safeAvailableRamMb,
                'overcommit_ratio'     => $ramOvercommitRatio,
            ],
            'disk'                   => [
                'total_gb'             => $physicalDiskGb,
                'free_gb'              => $freeDiskGb,
                'allocated_gb'         => round($allocatedHardDiskGb, 2),
                'allocated_soft_gb'    => round($allocatedSoftDiskGb, 2),
                'reserved_gb'          => round($reservedDiskGb, 2),
                'allocatable_gb'       => $allocatableDiskGb,
                'quota_headroom_gb'    => $quotaHeadroomDiskGb,
                'safe_available_gb'    => $safeAvailableDiskGb,
            ],
            'cpu'                    => [
                'total_cores'          => $physicalCores,
                'allocated_cores'      => round($allocatedCpuCores, 2),
                'reserved_cores'       => round($reservedCpu, 2),
                'overcommit_ratio'     => $cpuOvercommitRatio,
            ],
            'physical_ram_mb'        => $physicalRamMb,
            'free_ram_mb'            => $freeRamMb,
            'allocated_ram_mb'       => $allocatedRamMb,
            'reserved_ram_mb'        => $reservedRamMb,
            'safe_available_ram_mb'  => $safeAvailableRamMb,
            'ram_overcommit_ratio'   => $ramOvercommitRatio,
            'physical_disk_gb'       => $physicalDiskGb,
            'free_disk_gb'           => $freeDiskGb,
            'allocated_disk_gb'      => round($allocatedHardDiskGb, 2),
            'allocated_soft_disk_gb' => round($allocatedSoftDiskGb, 2),
            'reserved_disk_gb'       => round($reservedDiskGb, 2),
            'safe_available_disk_gb' => $safeAvailableDiskGb,
            'physical_cpu_cores'     => $physicalCores,
            'allocated_cpu_cores'    => round($allocatedCpuCores, 2),
            'reserved_cpu_cores'     => round($reservedCpu, 2),
            'checked_at'             => now()->toDateTimeString(),
        ];
    }

    /**
     * Validate a proposed resource allocation against the server's real capacity and safe buffers.
     *
     * $fresh=false lets the wizard's live (debounced) check reuse the 15s
     * cached inspection instead of opening a connection per keystroke;
     * the authoritative check at submit time is always fresh.
     *
     * @return array{valid: bool, errors: list<string>, warnings: list<string>, capacity: array<string, mixed>}
     */
    public function validateAllocation(
        CustomerRemoteServer $server,
        int $ramMb,
        float $diskGb,
        ?float $softDiskGb = null,
        float $cpuCores = 1.0,
        bool $fresh = true,
    ): array {
        $capacity = $this->getCapacity($server, fresh: $fresh);
        $errors   = [];
        $warnings = [];
        $fmt = fn (float $n): string => number_format($n, 1);

        $effectiveSoftDisk = $softDiskGb ?? round($diskGb * self::DEFAULT_SOFT_DISK_RATIO, 2);
        if ($effectiveSoftDisk > $diskGb) {
            $errors[] = 'سقف دیسک نرم (فضای کش و موقت) نمی‌تواند از دیسک سخت بیشتر باشد.';
        }

        // Live free disk is a hard limit whatever the hosting model.
        if ($capacity['free_disk_gb'] !== null && $diskGb > $capacity['free_disk_gb']) {
            $errors[] = sprintf('فضای دیسک درخواستی (%s گیگابایت) بیشتر از دیسک آزاد واقعی سرور (%s گیگابایت) است.', $fmt($diskGb), $fmt($capacity['free_disk_gb']));
        }

        if ($capacity['panel_managed']) {
            $warnings[] = 'منابع رم و پردازنده در این هاست اشتراکی توسط کنترل‌پنل مدیریت می‌شود و از اینجا تضمین نمی‌شود.';

            return ['valid' => $errors === [], 'errors' => $errors, 'warnings' => $warnings, 'capacity' => $capacity];
        }

        if (! $capacity['specs_known']) {
            $errors[] = 'مشخصات سخت‌افزار این سرور نه ثبت شده و نه قابل اندازه‌گیری برخط است؛ بدون آن تخصیص منابع تضمین نمی‌شود. مشخصات را در «زیرساخت ← سرورها» ثبت کنید یا اتصال SSH سرور را بررسی کنید.';

            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings, 'capacity' => $capacity];
        }

        if ($capacity['metrics_source'] !== 'live_probe') {
            $warnings[] = 'اندازه‌گیری برخط سرور ناموفق بود؛ بررسی بر اساس مشخصات ثبت‌شده انجام شد.';
        }

        // Hard-disk quotas: committed (allocated + reserved) + requested must
        // fit inside the physical disk minus the safety buffer.
        $projectedDisk = $capacity['allocated_disk_gb'] + $capacity['reserved_disk_gb'] + $diskGb;
        if ($projectedDisk > $capacity['disk']['allocatable_gb']) {
            $errors[] = sprintf(
                'مجموع سهمیه‌های دیسک (تخصیص‌یافته %s + رزرو نصب‌های در جریان %s + درخواستی %s گیگابایت) از ظرفیت قابل تخصیص سرور (%s گیگابایت، با ۲۰٪ بافر) بیشتر می‌شود.',
                $fmt($capacity['allocated_disk_gb']), $fmt($capacity['reserved_disk_gb']), $fmt($diskGb), $fmt($capacity['disk']['allocatable_gb'])
            );
        } elseif ($capacity['safe_available_disk_gb'] !== null && $diskGb > $capacity['safe_available_disk_gb']) {
            $warnings[] = sprintf('دیسک درخواستی بافر ایمن ۲۰٪ دیسک آزاد را کاهش می‌دهد (فضای آزاد با احتساب بافر: %s گیگابایت).', $fmt($capacity['safe_available_disk_gb']));
        }

        // RAM: thin provisioning is allowed up to the ratio; beyond it is an error.
        $projectedRatio = round(($capacity['allocated_ram_mb'] + $capacity['reserved_ram_mb'] + $ramMb) / $capacity['physical_ram_mb'], 2);
        if ($projectedRatio > self::MAX_RAM_OVERCOMMIT_RATIO) {
            $errors[] = sprintf('ضریب تعهد رم این سرور به %s برابر ظرفیت فیزیکی می‌رسد (سقف مجاز: %s برابر).', $projectedRatio, self::MAX_RAM_OVERCOMMIT_RATIO);
        } elseif ($capacity['free_ram_mb'] !== null && $ramMb > $capacity['free_ram_mb']) {
            $warnings[] = sprintf('رم درخواستی (%s مگابایت) از رم آزاد فعلی سرور (%s مگابایت) بیشتر است و اشتراکی تخصیص می‌یابد.', number_format($ramMb), number_format($capacity['free_ram_mb']));
        }

        // CPU: one site can never exceed the machine; the pool may be time-shared up to the ratio.
        if ($cpuCores > $capacity['physical_cpu_cores']) {
            $errors[] = sprintf('تعداد هسته‌های درخواستی (%s) از کل هسته‌های پردازنده سرور (%s) بیشتر است.', $cpuCores, $capacity['physical_cpu_cores']);
        } else {
            $projectedCpu = round(($capacity['allocated_cpu_cores'] + $capacity['reserved_cpu_cores'] + $cpuCores) / $capacity['physical_cpu_cores'], 2);
            if ($projectedCpu > self::MAX_CPU_OVERCOMMIT_RATIO) {
                $errors[] = sprintf('ضریب تعهد پردازنده این سرور به %s برابر هسته‌های فیزیکی می‌رسد (سقف مجاز: %s برابر).', $projectedCpu, self::MAX_CPU_OVERCOMMIT_RATIO);
            }
        }

        return [
            'valid'    => $errors === [],
            'errors'   => $errors,
            'warnings' => $warnings,
            'capacity' => $capacity,
        ];
    }

    /**
     * Identify under-utilized sites on this server (using < 10% of their quota) for Scale Down.
     *
     * @return list<array<string, mixed>>
     */
    public function getUnderutilizedSites(CustomerRemoteServer $server): array
    {
        $siteIds = CustomerInstallation::where('remote_server_id', $server->id)
            ->whereNotNull('site_id')
            ->pluck('site_id')
            ->all();

        $sites = Site::whereIn('id', $siteIds)
            ->with(['resourceLimit', 'resourceUsage'])
            ->get();

        $underutilized = [];
        foreach ($sites as $site) {
            $limit = $site->resourceLimit;
            $usage = $site->resourceUsage;
            if (!$limit || !$usage) {
                continue;
            }

            $storageLimit = (int) ($limit->disk_hard_limit_bytes ?: $limit->storage_limit_bytes ?: 5368709120);
            $storageUsed  = (int) ($usage->disk_usage_bytes ?: $usage->storage_bytes ?: 0);
            $pct = $storageLimit > 0 ? round(($storageUsed / $storageLimit) * 100, 1) : 0;

            if ($pct < 10.0 && $storageLimit >= 5368709120) {
                $underutilized[] = [
                    'site_id'            => $site->id,
                    'title'              => $site->title,
                    'domain'             => $site->domain,
                    'quota_gb'           => round($storageLimit / (1024 ** 3), 1),
                    'used_mb'            => round($storageUsed / (1024 ** 2), 1),
                    'usage_percent'      => $pct,
                    'suggested_quota_gb' => max(2, round(($storageUsed / (1024 ** 3)) * 2, 1)),
                ];
            }
        }

        return $underutilized;
    }

    /**
     * Dynamically scale a site's resources up or down.
     */
    public function scaleSiteResources(Site $site, array $newLimits): SiteResourceLimit
    {
        $limit = SiteResourceLimit::firstOrNew(['site_id' => $site->id]);

        if (isset($newLimits['ram_mb'])) {
            $limit->memory_limit_bytes = (int) $newLimits['ram_mb'] * 1024 * 1024;
        }
        if (isset($newLimits['disk_gb'])) {
            $hardBytes = (int) ($newLimits['disk_gb'] * 1024 * 1024 * 1024);
            $limit->disk_hard_limit_bytes = $hardBytes;
            $limit->storage_limit_bytes   = $hardBytes;

            $softGb = $newLimits['soft_disk_gb'] ?? round($newLimits['disk_gb'] * self::DEFAULT_SOFT_DISK_RATIO, 2);
            $limit->disk_soft_limit_bytes = (int) ($softGb * 1024 * 1024 * 1024);
        }
        if (isset($newLimits['cpu_cores'])) {
            $limit->cpu_cores_limit = (float) $newLimits['cpu_cores'];
        }
        if (isset($newLimits['plan_name'])) {
            $limit->plan_name = $newLimits['plan_name'];
        }

        $limit->save();

        return $limit;
    }
}
