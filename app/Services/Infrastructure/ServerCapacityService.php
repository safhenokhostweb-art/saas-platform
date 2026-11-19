<?php

namespace App\Services\Infrastructure;

use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\InfraServer;
use App\Models\Site;
use App\Models\SiteResourceLimit;
use App\Models\SiteResourceUsage;
use App\Services\CustomerInstallation\RemoteConnectionService;
use App\Services\DevOps\ServerMetricsCollector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Server Capacity & Resource Governance Governor.
 *
 * Provides real-time capacity inspection, safe overcommit tracking,
 * live pre-flight allocation validation, and dynamic scaling (Scale Up / Scale Down)
 * across servers and tenant sites.
 */
class ServerCapacityService
{
    /** Safe buffer percentage: Keep at least 15% physical RAM untouched */
    public const RAM_SAFETY_BUFFER_PERCENT = 15;

    /** Safe buffer percentage: Keep at least 20% physical Disk free */
    public const DISK_SAFETY_BUFFER_PERCENT = 20;

    /** Maximum allowed thin-provisioning overcommit ratio for RAM */
    public const MAX_RAM_OVERCOMMIT_RATIO = 2.0;

    /** Default Soft Disk ratio: 70% of Hard Disk */
    public const DEFAULT_SOFT_DISK_RATIO = 0.70;

    public function __construct(
        private readonly RemoteConnectionService $connections,
        private readonly ServerMetricsCollector $collector,
    ) {}

    /**
     * Inspect live capacity, active site quotas, and available headroom of a target server.
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
        // 1. Resolve physical specs from InfraServer if registered, or probe via OS metrics / defaults
        $infra = InfraServer::where('ip_address', $server->host)
            ->orWhere('hostname', $server->host)
            ->first();

        $physicalRamMb   = $infra?->ram_gb ? (int) ($infra->ram_gb * 1024) : 16384; // default 16GB
        $physicalDiskGb  = $infra?->disk_gb ? (float) $infra->disk_gb : 250.0;     // default 250GB
        $physicalCores   = $infra?->cpu_cores ? (float) $infra->cpu_cores : 8.0;   // default 8 cores

        // 2. Probe live free disk via remote driver
        $freeDiskGb = $physicalDiskGb * 0.6; // fallback estimate
        try {
            $driver = $this->connections->buildDriver($server);
            $driver->connect();
            $freeBytes = $driver->diskFree('/var/www');
            if ($freeBytes > 0) {
                $freeDiskGb = round($freeBytes / (1024 ** 3), 2);
            }
            $driver->disconnect();
        } catch (\Throwable $e) {
            Log::debug("ServerCapacityService: Remote probe skipped for server #{$server->id}: " . $e->getMessage());
        }

        // Live host metrics probe if local/panel host
        $liveMetrics = $this->collector->collect();
        $freeRamMb = $physicalRamMb * 0.5; // fallback
        if (!empty($liveMetrics['memory']['available_bytes'])) {
            $freeRamMb = round($liveMetrics['memory']['available_bytes'] / (1024 ** 2));
        }

        // 3. Aggregate currently allocated quotas from SiteResourceLimit
        // Sites hosted on this remote server
        $siteIds = \App\Models\CustomerInstallation::where('remote_server_id', $server->id)
            ->whereNotNull('site_id')
            ->pluck('site_id')
            ->unique()
            ->all();

        $activeSitesCount = count($siteIds);

        $limits = SiteResourceLimit::whereIn('site_id', $siteIds)->get();
        $allocatedRamMb = 0;
        $allocatedHardDiskGb = 0.0;
        $allocatedSoftDiskGb = 0.0;
        $allocatedCpuCores = 0.0;

        foreach ($limits as $lim) {
            $allocatedRamMb += (int) (($lim->memory_limit_bytes ?: 1073741824) / (1024 ** 2));
            $allocatedHardDiskGb += (float) (($lim->disk_hard_limit_bytes ?: $lim->storage_limit_bytes ?: 5368709120) / (1024 ** 3));
            $allocatedSoftDiskGb += (float) (($lim->disk_soft_limit_bytes ?: ($allocatedHardDiskGb * self::DEFAULT_SOFT_DISK_RATIO * 1024 ** 3)) / (1024 ** 3));
            $allocatedCpuCores += (float) ($lim->cpu_cores_limit ?: 1.0);
        }

        $ramOvercommitRatio = $physicalRamMb > 0 ? round($allocatedRamMb / $physicalRamMb, 2) : 1.0;
        $safeAvailableRamMb = max(0, (int) ($freeRamMb - ($physicalRamMb * (self::RAM_SAFETY_BUFFER_PERCENT / 100))));
        $safeAvailableDiskGb = max(0, round($freeDiskGb - ($physicalDiskGb * (self::DISK_SAFETY_BUFFER_PERCENT / 100)), 2));

        $hasHeadroom = ($safeAvailableRamMb > 512) && ($safeAvailableDiskGb > 2.0) && ($ramOvercommitRatio <= self::MAX_RAM_OVERCOMMIT_RATIO);

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
            'has_headroom'           => $hasHeadroom,
            'sites_count'            => $activeSitesCount,
            'active_sites_count'     => $activeSitesCount,
            'ram'                    => [
                'total_mb'             => $physicalRamMb,
                'free_mb'              => $freeRamMb,
                'allocated_mb'         => $allocatedRamMb,
                'safe_available_mb'    => $safeAvailableRamMb,
                'overcommit_ratio'     => $ramOvercommitRatio,
            ],
            'disk'                   => [
                'total_gb'             => $physicalDiskGb,
                'free_gb'              => $freeDiskGb,
                'allocated_gb'         => round($allocatedHardDiskGb, 2),
                'allocated_soft_gb'    => round($allocatedSoftDiskGb, 2),
                'safe_available_gb'    => $safeAvailableDiskGb,
            ],
            'physical_ram_mb'        => $physicalRamMb,
            'free_ram_mb'            => $freeRamMb,
            'allocated_ram_mb'       => $allocatedRamMb,
            'safe_available_ram_mb'  => $safeAvailableRamMb,
            'ram_overcommit_ratio'   => $ramOvercommitRatio,
            'physical_disk_gb'       => $physicalDiskGb,
            'free_disk_gb'           => $freeDiskGb,
            'allocated_disk_gb'      => round($allocatedHardDiskGb, 2),
            'allocated_soft_disk_gb' => round($allocatedSoftDiskGb, 2),
            'safe_available_disk_gb' => $safeAvailableDiskGb,
            'physical_cpu_cores'     => $physicalCores,
            'allocated_cpu_cores'    => round($allocatedCpuCores, 2),
            'cpu_load_1m'            => $liveMetrics['cpu']['load_1m'] ?? null,
            'checked_at'             => now()->toDateTimeString(),
        ];
    }

    /**
     * Validate a proposed resource allocation against the server's real capacity and safe buffers.
     *
     * @return array{valid: bool, errors: list<string>, warnings: list<string>, capacity: array<string, mixed>}
     */
    public function validateAllocation(
        CustomerRemoteServer $server,
        int $ramMb,
        float $diskGb,
        ?float $softDiskGb = null,
        float $cpuCores = 1.0
    ): array {
        $capacity = $this->getCapacity($server, fresh: true);
        $errors   = [];
        $warnings = [];

        // Check Hard Disk against physical free space
        if ($diskGb > $capacity['free_disk_gb']) {
            $errors[] = sprintf(
                'فضای دیسک درخواستی (%s گیگابایت) بیشتر از کل دیسک فیزیکی آزاد سرور (%s گیگابایت) است.',
                number_format($diskGb, 1),
                number_format($capacity['free_disk_gb'], 1)
            );
        } elseif ($diskGb > $capacity['safe_available_disk_gb']) {
            $warnings[] = sprintf(
                'فضای دیسک انتخابی (%s گیگابایت) بافر ایمن ۲۰٪ سرور را کاهش می‌دهد. (فضای آزاد با احتساب بافر: %s گیگابایت)',
                number_format($diskGb, 1),
                number_format($capacity['safe_available_disk_gb'], 1)
            );
        }

        // Soft Disk default (70% of Hard Disk) and check
        $effectiveSoftDisk = $softDiskGb ?? round($diskGb * self::DEFAULT_SOFT_DISK_RATIO, 2);
        if ($effectiveSoftDisk > $diskGb) {
            $errors[] = 'سقف دیسک نرم (فضای کش و موقت) نمی‌تواند از دیسک سخت بیشتر باشد.';
        }

        // Check RAM against physical capacity & overcommit limit
        if ($ramMb > $capacity['free_ram_mb']) {
            $warnings[] = sprintf(
                'رم درخواستی (%s مگابایت) از رم فیزیکی آزاد فعلی سرور (%s مگابایت) بیشتر است و با استفاده اشتراکی (Thin Provisioning) تخصیص می‌یابد.',
                number_format($ramMb),
                number_format($capacity['free_ram_mb'])
            );
        }

        $projectedRamAllocated = $capacity['allocated_ram_mb'] + $ramMb;
        $projectedRatio = $capacity['physical_ram_mb'] > 0
            ? round($projectedRamAllocated / $capacity['physical_ram_mb'], 2)
            : 1.0;

        if ($projectedRatio > self::MAX_RAM_OVERCOMMIT_RATIO) {
            $warnings[] = sprintf(
                'ضریب اشتراک رم روی این سرور به %s برابر ظرفیت فیزیکی می‌رسد (سقف مجاز امن: %s برابر).',
                $projectedRatio,
                self::MAX_RAM_OVERCOMMIT_RATIO
            );
        }

        // CPU cores sanity check
        if ($cpuCores > $capacity['physical_cpu_cores']) {
            $errors[] = sprintf(
                'تعداد هسته‌های درخواستی (%s) از کل هسته‌های پردازنده سرور (%s) بیشتر است.',
                $cpuCores,
                $capacity['physical_cpu_cores']
            );
        }

        return [
            'valid'    => empty($errors),
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
        $siteIds = \App\Models\CustomerInstallation::where('remote_server_id', $server->id)
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
