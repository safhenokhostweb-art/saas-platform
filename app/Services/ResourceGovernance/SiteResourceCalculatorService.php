<?php

namespace App\Services\ResourceGovernance;

use App\Models\Site;
use App\Models\SiteResourceUsage;
use App\Models\SiteStorageSnapshot;
use App\Models\SiteBandwidthUsage;
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
        $metrics = [
            'storage_bytes'  => 0,
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
            $metrics['storage_bytes'] = $this->getStorageBytes($site);
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

    /**
     * Sum sizes of files uploaded to the customer site (tracked via DB records).
     */
    public function getStorageBytes(Site $site): int
    {
        if (empty($site->db_name)) return 0;

        try {
            $conn = $this->openSiteConnection($site);
            $db   = DB::connection($conn);
            $total = 0;

            // Sum image/file columns across key tables where customers upload files
            $tableSources = [
                ['table' => 'posts',     'columns' => ['image']],
                ['table' => 'products',  'columns' => ['image', 'gallery']],
                ['table' => 'courses',   'columns' => ['image', 'video_url']],
                ['table' => 'site_uploads', 'columns' => ['path']],
            ];

            foreach ($tableSources as $source) {
                try {
                    if (!$db->getSchemaBuilder()->hasTable($source['table'])) continue;
                    $count = $db->table($source['table'])->count();
                    // Estimate average sizes per content type
                    $avgBytes = match ($source['table']) {
                        'posts'        => 150_000,
                        'products'     => 300_000,
                        'courses'      => 500_000,
                        'site_uploads' => 200_000,
                        default        => 100_000,
                    };
                    $total += $count * $avgBytes;
                } catch (\Throwable) {}
            }

            $this->closeSiteConnection($conn);
            return $total;
        } catch (\Throwable $e) {
            Log::debug("ResourceCalculator: getStorageBytes failed for site {$site->id}: " . $e->getMessage());
            return 0;
        }
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

        $data = array_merge($metrics, ['calculated_at' => now()]);

        if ($existing) {
            // Reset alert flags if storage dropped (e.g., customer deleted files)
            $limit = \App\Models\SiteResourceLimit::defaultForSite($site);
            $pct   = $limit->storage_limit_bytes > 0
                ? ($metrics['storage_bytes'] / $limit->storage_limit_bytes) * 100
                : 0;
            if ($pct < config('resource_governance.alert_warning_percent', 80)) {
                $data['alerted_80'] = false;
                $data['alerted_95'] = false;
            }
            $existing->update($data);
        } else {
            SiteResourceUsage::create(array_merge(['site_id' => $site->id], $data));
        }

        SiteStorageSnapshot::upsertToday($site->id, [
            'storage_bytes' => $metrics['storage_bytes'],
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
            $pct   = $limit->storage_limit_bytes > 0
                ? round(($metrics['storage_bytes'] / $limit->storage_limit_bytes) * 100, 1)
                : 0;

            $summary = json_encode([
                'storage_pct'    => $pct,
                'storage_bytes'  => $metrics['storage_bytes'],
                'storage_limit'  => (int) $limit->storage_limit_bytes,
                'db_bytes'       => $metrics['db_bytes'],
                'archive_bytes'  => $metrics['archive_bytes'],
                'calculated_at'  => now()->toDateTimeString(),
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

    private function openSiteConnection(Site $site): string
    {
        $key = 'resource_calc_' . $site->id;
        config([
            "database.connections.{$key}" => [
                'driver'    => 'mysql',
                'host'      => $site->db_host     ?: env('DB_HOST', '127.0.0.1'),
                'port'      => env('DB_PORT', 3306),
                'database'  => $site->db_name,
                'username'  => $site->db_user     ?: env('DB_USERNAME'),
                'password'  => $site->db_password ?: env('DB_PASSWORD'),
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
