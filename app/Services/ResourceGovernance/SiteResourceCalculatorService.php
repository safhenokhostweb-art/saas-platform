<?php

namespace App\Services\ResourceGovernance;

use App\Models\Site;
use App\Models\SiteResourceUsage;
use App\Models\SiteStorageSnapshot;
use App\Models\SiteBandwidthUsage;
use App\Services\DevOps\TenantResourceGovernor;
use App\Services\Infrastructure\Monitoring\InfrastructureErrorCollector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SiteResourceCalculatorService
{
    public function __construct(
        private InfrastructureErrorCollector $errorCollector
    ) {}

    /**
     * Calculate all resource metrics for a site and persist them.
     */
    public function calculate(Site $site, bool $dryRun = false): array
    {
        // 2026-11-19 (P1): storage is the web root's REAL disk usage as
        // measured by TenantResourceGovernor (du over the site's own
        // connection). It used to be row counts x fixed averages (150 KB per
        // post...), so every 70/80/95% alert fired on invented numbers.
        // When no fresh measurement exists, storage_bytes is null and no
        // threshold alert is raised.
        $measured = $this->measuredDiskUsage($site, $dryRun);

        $metrics = [
            'storage_bytes'  => $measured['bytes'],
            'storage_measured'    => $measured['bytes'] !== null,
            'storage_measured_at' => $measured['at']?->toDateTimeString(),
            'db_bytes'       => 0,
            'backup_bytes'   => 0,
            'archive_bytes'  => 0,
            'file_count'     => 0,
            'bandwidth_bytes_monthly' => 0,
            'request_count_monthly'   => 0,
        ];

        try {
            $metrics['db_bytes']      = $this->getDatabaseBytes($site);
            $metrics['archive_bytes'] = $this->getArchiveBytes($site);
            $metrics['backup_bytes']  = $this->getBackupBytes($site);
            $metrics['file_count']    = $this->getFileCount($site);

            $bw = SiteBandwidthUsage::currentMonthForSite($site->id);
            $metrics['bandwidth_bytes_monthly'] = $bw ? (int) $bw->bytes : 0;
            $metrics['request_count_monthly']   = $bw ? (int) $bw->request_count : 0;
        } catch (\Throwable $e) {
            Log::warning("ResourceCalculator: failed for site {$site->id}", ['error' => $e->getMessage()]);
        }

        if (!$dryRun) {
            $this->persist($site, $metrics);
        }

        return $metrics;
    }

    /**
     * Query MySQL information_schema for the customer site's database size.
     */
    public function getDatabaseBytes(Site $site): int
    {
        if (empty($site->db_name)) return 0;

        try {
            $conn = $this->openSiteConnection($site);
            $rows = DB::connection($conn)->select("
                SELECT COALESCE(SUM(data_length + index_length), 0) AS total_bytes
                FROM information_schema.tables
                WHERE table_schema = ?
            ", [$site->db_name]);

            $bytes = isset($rows[0]) ? (int) $rows[0]->total_bytes : 0;
            $this->closeSiteConnection($conn);
            return $bytes;
        } catch (\Throwable $e) {
            Log::debug("ResourceCalculator: getDatabaseBytes failed for site {$site->id}: " . $e->getMessage());
            return 0;
        }
    }

    /** Measurements older than this are not trusted for alerts (governor runs every 6h). */
    public const MEASUREMENT_MAX_AGE_HOURS = 26;

    /**
     * The latest real web-root measurement: the governor's if fresh, else a
     * one-off audit now. Never an estimate.
     *
     * @return array{bytes: ?int, at: ?\Illuminate\Support\Carbon}
     */
    public function measuredDiskUsage(Site $site, bool $dryRun = false): array
    {
        $usage = SiteResourceUsage::where('site_id', $site->id)->first();
        if ($usage?->disk_usage_bytes !== null
            && $usage->disk_audited_at !== null
            && $usage->disk_audited_at->gt(now()->subHours(self::MEASUREMENT_MAX_AGE_HOURS))) {
            return ['bytes' => (int) $usage->disk_usage_bytes, 'at' => $usage->disk_audited_at];
        }

        try {
            $audit = app(TenantResourceGovernor::class)->audit($site, persist: ! $dryRun);
            if (($audit['ok'] ?? false) && $audit['disk_usage_bytes'] !== null) {
                return ['bytes' => (int) $audit['disk_usage_bytes'], 'at' => now()];
            }
        } catch (\Throwable $e) {
            Log::info("ResourceCalculator: disk audit failed for site {$site->id}: " . $e->getMessage());
        }

        return ['bytes' => null, 'at' => $usage?->disk_audited_at];
    }

    /**
     * Get the actual file size of the site's archive ZIP if it exists.
     */
    public function getArchiveBytes(Site $site): int
    {
        if (empty($site->archive_path)) return 0;

        try {
            // archive_path is relative to storage/app/archives/
            $relativePath = ltrim(str_replace('storage/app/', '', $site->archive_path), '/');
            if (Storage::disk('local')->exists($relativePath)) {
                return (int) Storage::disk('local')->size($relativePath);
            }
            // Try treating as absolute path segment
            if (Storage::exists($site->archive_path)) {
                return (int) Storage::size($site->archive_path);
            }
        } catch (\Throwable $e) {
            Log::debug("ResourceCalculator: getArchiveBytes failed for site {$site->id}: " . $e->getMessage());
        }

        return 0;
    }

    /**
     * Sum backup file sizes from infra_backup_runs for this site.
     */
    public function getBackupBytes(Site $site): int
    {
        try {
            return (int) DB::table('infra_backup_runs')
                ->where('site_id', $site->id)
                ->whereNotNull('file_size_mb')
                ->sum(DB::raw('CAST(file_size_mb * 1048576 AS UNSIGNED)'));
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Count total file records in key customer-site tables.
     */
    public function getFileCount(Site $site): int
    {
        if (empty($site->db_name)) return 0;

        try {
            $conn  = $this->openSiteConnection($site);
            $db    = DB::connection($conn);
            $total = 0;

            foreach (['posts', 'products', 'courses', 'site_uploads'] as $table) {
                try {
                    if ($db->getSchemaBuilder()->hasTable($table)) {
                        $total += (int) $db->table($table)->count();
                    }
                } catch (\Throwable) {}
            }

            $this->closeSiteConnection($conn);
            return $total;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Persist metrics to site_resource_usage and a daily snapshot.
     */
    private function persist(Site $site, array $metrics): void
    {
        $existing = SiteResourceUsage::where('site_id', $site->id)->first();

        $data = array_merge(
            array_diff_key($metrics, array_flip(['storage_measured', 'storage_measured_at'])),
            ['calculated_at' => now()],
        );
        if ($metrics['storage_bytes'] === null) {
            unset($data['storage_bytes']); // unmeasured: keep the last real figure, never write an estimate
        }

        if ($existing) {
            // Reset hard-threshold flags only on a real measurement below 80%.
            $limit = \App\Models\SiteResourceLimit::defaultForSite($site);
            $hard  = (int) ($limit->disk_hard_limit_bytes ?: $limit->storage_limit_bytes);
            if ($metrics['storage_bytes'] !== null && $hard > 0
                && ($metrics['storage_bytes'] / $hard) * 100 < config('resource_governance.alert_warning_percent', 80)) {
                $data['alerted_80'] = false;
                $data['alerted_95'] = false;
            }
            $existing->update($data);
        } else {
            SiteResourceUsage::create(array_merge(['site_id' => $site->id, 'storage_bytes' => 0], $data));
        }

        SiteStorageSnapshot::upsertToday($site->id, [
            'storage_bytes' => $metrics['storage_bytes'] ?? (int) ($existing?->storage_bytes ?? 0),
            'db_bytes'      => $metrics['db_bytes'],
            'backup_bytes'  => $metrics['backup_bytes'],
            'archive_bytes' => $metrics['archive_bytes'],
            'file_count'    => $metrics['file_count'],
        ]);
    }

    /**
     * Release active resource usage when a site is archived.
     * Keeps archive_bytes; zeros out everything else.
     */
    public function releaseActiveResources(Site $site): void
    {
        $archiveBytes = $this->getArchiveBytes($site);

        SiteResourceUsage::updateOrCreate(
            ['site_id' => $site->id],
            [
                'storage_bytes'           => 0,
                'db_bytes'                => 0,
                'backup_bytes'            => 0,
                'archive_bytes'           => $archiveBytes,
                'file_count'              => 0,
                'bandwidth_bytes_monthly' => 0,
                'request_count_monthly'   => 0,
                'alerted_80'              => false,
                'alerted_95'              => false,
                'calculated_at'           => now(),
            ]
        );
    }

    /**
     * Push a compact resource summary to the customer-site settings table.
     * Same pattern as SiteLifecycleService::pushStatusToCustomerSite().
     */
    public function pushSummaryToCustomerSite(Site $site, array $metrics, ?SiteResourceUsage $usage = null): void
    {
        if (empty($site->db_name)) return;

        try {
            $limit = \App\Models\SiteResourceLimit::defaultForSite($site);
            $hardLimit = (int) ($limit->disk_hard_limit_bytes ?: $limit->storage_limit_bytes ?: 5368709120);
            $softLimit = (int) ($limit->disk_soft_limit_bytes ?: ($hardLimit * 0.70));
            $ramLimit  = (int) ($limit->memory_limit_bytes ?: 1073741824);
            $cpuLimit  = (float) ($limit->cpu_cores_limit ?: 1.0);

            $usage = $usage ?? SiteResourceUsage::where('site_id', $site->id)->first();
            $measuredBytes = $metrics['storage_bytes'] ?? null;
            $usedBytes = (int) ($measuredBytes ?? 0);
            $pct = ($measuredBytes !== null && $hardLimit > 0) ? round(($usedBytes / $hardLimit) * 100, 1) : null;
            $softPct = ($measuredBytes !== null && $softLimit > 0) ? round(($usedBytes / $softLimit) * 100, 1) : null;
            $graceDays = (int) config('resource_governance.soft_disk_grace_days', 7);
            $softSince = $usage?->soft_exceeded_at;

            $summary = json_encode([
                'storage_measured'      => $measuredBytes !== null,
                'measured_at'           => $metrics['storage_measured_at'] ?? null,
                'warning_percent'       => (int) config('resource_governance.alert_warning_percent', 80),
                'critical_percent'      => (int) config('resource_governance.alert_critical_percent', 95),
                'soft_exceeded_at'      => $softSince?->toIso8601String(),
                'soft_grace_days'       => $graceDays,
                'soft_grace_ends_at'    => $softSince?->copy()->addDays($graceDays)->toIso8601String(),
                'soft_grace_expired'    => $usage?->soft_grace_escalated_at !== null,
                'storage_pct'           => $pct,
                'storage_bytes'         => $usedBytes,
                'storage_limit'         => $hardLimit,
                'disk_hard_limit_bytes' => $hardLimit,
                'disk_soft_limit_bytes' => $softLimit,
                'disk_soft_pct'         => $softPct,
                'memory_limit_bytes'    => $ramLimit,
                'memory_limit_mb'       => (int) ($ramLimit / (1024 * 1024)),
                'cpu_cores_limit'       => $cpuLimit,
                'db_bytes'              => $metrics['db_bytes'] ?? 0,
                'archive_bytes'         => $metrics['archive_bytes'] ?? 0,
                'calculated_at'         => now()->toDateTimeString(),
            ]);

            $conn = $this->openSiteConnection($site);
            $db   = DB::connection($conn);

            $exists = $db->table('settings')->where('key', 'site_resource_summary')->exists();
            if ($exists) {
                $db->table('settings')->where('key', 'site_resource_summary')
                    ->update(['value' => $summary, 'updated_at' => now()]);
            } else {
                $db->table('settings')->insert([
                    'key'        => 'site_resource_summary',
                    'value'      => $summary,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Threshold notifications/activity on the customer site are written
            // per state transition by SiteResourceAlertService (recordCustomerSiteEvent),
            // not on every daily push from an estimate as before.

            // Clear settings cache
            try {
                $db->table('cache')->where('key', 'settings')->delete();
            } catch (\Throwable) {}

            $this->closeSiteConnection($conn);
        } catch (\Throwable $e) {
            // GAP-M08: re-throw so caller can fire platform admin notification
            throw $e;
        }
    }

    /**
     * Two-way logging: record a resource event on the customer site itself
     * (its admin inbox and its admin activity log), over the same connection
     * the summary push uses. Returns false when the site DB is unreachable;
     * the platform-side log is written by the caller regardless.
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordCustomerSiteEvent(Site $site, string $type, string $title, string $body, array $properties = []): bool
    {
        if (empty($site->db_name)) {
            return false;
        }

        try {
            $conn = $this->openSiteConnection($site);
            $db = DB::connection($conn);
            try {
                $db->table('admin_notifications')->insert([
                    'type' => mb_substr($type, 0, 40), 'title' => $title, 'body' => $body,
                    'link' => '/account/resources', 'is_read' => false,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (\Throwable) {
            }
            try {
                $db->table('admin_activity_log')->insert([
                    'action' => $type, 'description' => $title,
                    'properties' => json_encode($properties, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (\Throwable) {
            }
            $this->closeSiteConnection($conn);

            return true;
        } catch (\Throwable $e) {
            Log::warning("ResourceCalculator: customer-site event '{$type}' not recorded for site {$site->id}: " . $e->getMessage());

            return false;
        }
    }

    private function openSiteConnection(Site $site): string
    {
        $key = 'resource_calc_' . $site->id;
        config([
            "database.connections.{$key}" => [
                'driver'    => 'mysql',
                'host'      => $site->db_host     ?: config('database.connections.mysql.host', '127.0.0.1'),
                'port'      => config('database.connections.mysql.port', 3306),
                'database'  => $site->db_name,
                'username'  => $site->db_user     ?: config('database.connections.mysql.username'),
                'password'  => $site->getDecryptedDbPassword() ?: config('database.connections.mysql.password'),
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix'    => '',
                'strict'    => false,
            ],
        ]);
        return $key;
    }

    private function closeSiteConnection(string $key): void
    {
        DB::purge($key);
    }
}
