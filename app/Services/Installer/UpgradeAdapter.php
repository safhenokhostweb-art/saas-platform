<?php
namespace App\Services\Installer;

use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Support\Facades\Artisan;

/**
 * Adapter for MODE 3 (Upgrade Platform).
 * Protocol: snapshot → maintenance → migrate → optimize → health check → unlock.
 * On failure: automatic rollback from snapshot.
 */
class UpgradeAdapter
{
    public function __construct(
        private InfrastructureInstaller $infra,
        private HealthCheckRunner       $health,
        private SlackAlertService       $slack,
    ) {}

    // ─── Snapshot ────────────────────────────────────────────────────────────

    public function createSnapshot(): array
    {
        $timestamp = now()->format('Ymd_His');
        $snapDir   = storage_path("upgrade_snapshots/{$timestamp}");
        @mkdir($snapDir, 0750, true);

        $results = [];

        $results['database'] = $this->snapshotDatabase($snapDir);
        $results['env']      = $this->snapshotEnv($snapDir);
        $results['storage']  = [
            'success' => true,
            'message' => 'فایل‌های storage از Arvan قابل بازیابی هستند',
        ];

        $allSuccess = collect($results)->every(fn($r) => $r['success'] ?? false);

        return [
            'success'       => $allSuccess,
            'snapshot_path' => $snapDir,
            'timestamp'     => $timestamp,
            'results'       => $results,
            'message'       => $allSuccess
                ? "اسنپ‌شات در {$snapDir} ذخیره شد"
                : 'برخی اسنپ‌شات‌ها ناموفق — ارتقاء متوقف شد',
        ];
    }

    private function snapshotDatabase(string $snapDir): array
    {
        $db       = config('database.connections.mysql');
        $outFile  = "{$snapDir}/database.sql.gz";

        $dbNameEsc = escapeshellarg($db['database']);
        $userEsc   = escapeshellarg($db['username']);
        $hostEsc   = escapeshellarg($db['host']);
        $outEsc    = escapeshellarg($outFile);
        $passFlag  = !empty($db['password'])
            ? '-p' . escapeshellarg($db['password'])
            : '';

        $cmd        = "mysqldump -h {$hostEsc} -u {$userEsc} {$passFlag} {$dbNameEsc} | gzip > {$outEsc} 2>&1";
        $output     = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        return [
            'success' => $returnCode === 0,
            'path'    => $outFile,
            'message' => $returnCode === 0
                ? 'اسنپ‌شات پایگاه داده ایجاد شد'
                : 'خطا در اسنپ‌شات: ' . implode(', ', $output),
        ];
    }

    private function snapshotEnv(string $snapDir): array
    {
        $src = base_path('.env');
        $dst = "{$snapDir}/.env.backup";

        if (!file_exists($src)) {
            return ['success' => false, 'message' => '.env یافت نشد'];
        }

        $ok = copy($src, $dst);
        return [
            'success' => $ok,
            'path'    => $dst,
            'message' => $ok ? '.env پشتیبان گرفته شد' : 'کپی .env ناموفق',
        ];
    }

    // ─── Upgrade ─────────────────────────────────────────────────────────────

    public function runUpgrade(string $snapshotPath): array
    {
        $steps = [];

        // 1. Maintenance mode on
        try {
            Artisan::call('down');
            $steps[] = ['step' => 'maintenance_on', 'success' => true, 'message' => 'حالت نگهداری فعال شد'];
        } catch (\Throwable $e) {
            $steps[] = ['step' => 'maintenance_on', 'success' => false, 'message' => $e->getMessage()];
        }

        // 2. Run migrations
        $migrateResult = $this->infra->runMigrations(true);
        $steps[] = array_merge($migrateResult, ['step' => 'migrations']);

        if (!$migrateResult['success']) {
            $this->infra->disableMaintenanceMode();
            $steps[] = ['step' => 'maintenance_off', 'success' => true, 'message' => 'حالت نگهداری خاموش شد (ناموفق)'];
            return [
                'success'        => false,
                'steps'          => $steps,
                'needs_rollback' => true,
                'message'        => 'Migration ناموفق — بازگشت توصیه می‌شود',
            ];
        }

        // 3. Optimize
        $cacheResult = $this->infra->clearCacheAndOptimize();
        $steps[] = array_merge(['step' => 'optimize'], $cacheResult);

        // 4. Quick health check
        $healthResult = $this->health->runQuick();
        $steps[] = [
            'step'    => 'health_check',
            'success' => $healthResult['pass'],
            'message' => $healthResult['summary'],
        ];

        // 5. Maintenance mode off
        try {
            Artisan::call('up');
            $steps[] = ['step' => 'maintenance_off', 'success' => true, 'message' => 'حالت نگهداری خاموش شد'];
        } catch (\Throwable $e) {
            $steps[] = ['step' => 'maintenance_off', 'success' => false, 'message' => $e->getMessage()];
        }

        $allSuccess = collect($steps)->every(fn($s) => $s['success'] ?? false);

        if ($allSuccess) {
            $this->slack->info('Installer: Platform upgrade completed successfully');
        } else {
            $this->slack->warning('Installer: Platform upgrade completed with some failures');
        }

        return [
            'success'        => $allSuccess,
            'steps'          => $steps,
            'needs_rollback' => !$allSuccess,
            'message'        => $allSuccess ? 'ارتقاء با موفقیت انجام شد' : 'ارتقاء با خطا مواجه شد',
        ];
    }

    // ─── Installation Baseline ───────────────────────────────────────────────

    public function createInstallationBaseline(?string $reportHtmlPath = null, ?string $reportPdfPath = null): array
    {
        $baselineDir = storage_path('upgrade_snapshots/INITIAL_INSTALLATION_BASELINE');

        if (is_dir($baselineDir)) {
            return [
                'success'        => true,
                'baseline_path'  => $baselineDir,
                'message'        => 'Baseline already exists — skipped',
                'already_exists' => true,
            ];
        }

        @mkdir($baselineDir, 0750, true);

        $results = [];

        // 1. Database snapshot
        $results['database'] = $this->snapshotDatabase($baselineDir);

        // 2. .env backup
        $results['env'] = $this->snapshotEnv($baselineDir);

        // 3. Copy installation reports
        foreach (array_filter([$reportHtmlPath, $reportPdfPath]) as $rp) {
            if (file_exists($rp)) {
                $dest  = $baselineDir . '/' . basename($rp);
                $ok    = copy($rp, $dest);
                $results['report_' . pathinfo($rp, PATHINFO_EXTENSION)] = [
                    'success' => $ok,
                    'path'    => $ok ? $dest : null,
                    'message' => $ok ? 'Report copied' : 'Failed to copy report',
                ];
            }
        }

        // 4. Baseline metadata
        $meta = [
            'label'       => 'INITIAL_INSTALLATION_BASELINE',
            'created_at'  => now()->toISOString(),
            'version'     => config('app.version', '1.0.0'),
            'mode'        => 'fresh',
            'description' => 'Permanent initial installation baseline — primary rollback point for all future upgrades',
            'components'  => array_keys($results),
            'platform'    => config('app.name'),
        ];
        file_put_contents(
            $baselineDir . '/baseline_meta.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $allSuccess = collect($results)->every(fn($r) => $r['success'] ?? false);

        $this->slack->info('Installer: INITIAL_INSTALLATION_BASELINE created at ' . $baselineDir);

        return [
            'success'       => $allSuccess,
            'baseline_path' => $baselineDir,
            'results'       => $results,
            'message'       => $allSuccess
                ? 'INITIAL_INSTALLATION_BASELINE created successfully'
                : 'Baseline created with some failures',
        ];
    }

    // ─── Rollback ────────────────────────────────────────────────────────────

    public function rollback(string $snapshotPath): array
    {
        if (!is_dir($snapshotPath)) {
            return ['success' => false, 'message' => "مسیر اسنپ‌شات یافت نشد: {$snapshotPath}"];
        }

        $results = [];

        // Restore database
        $dbFile = "{$snapshotPath}/database.sql.gz";
        if (file_exists($dbFile)) {
            $results['database'] = $this->restoreDatabase($dbFile);
        }

        // Restore .env
        $envFile = "{$snapshotPath}/.env.backup";
        if (file_exists($envFile)) {
            $ok = copy($envFile, base_path('.env'));
            $results['env'] = [
                'success' => $ok,
                'message' => $ok ? '.env بازیابی شد' : 'بازیابی .env ناموفق',
            ];
        }

        $this->infra->disableMaintenanceMode();
        $this->slack->critical('Installer: Rollback executed from snapshot ' . basename($snapshotPath));

        $allSuccess = collect($results)->every(fn($r) => $r['success'] ?? false);
        return [
            'success' => $allSuccess,
            'results' => $results,
            'message' => $allSuccess ? 'Rollback با موفقیت انجام شد' : 'Rollback با خطا انجام شد',
        ];
    }

    private function restoreDatabase(string $gzipFile): array
    {
        $db      = config('database.connections.mysql');
        $fileEsc = escapeshellarg($gzipFile);
        $dbEsc   = escapeshellarg($db['database']);
        $userEsc = escapeshellarg($db['username']);
        $hostEsc = escapeshellarg($db['host']);
        $passFlag = !empty($db['password']) ? '-p' . escapeshellarg($db['password']) : '';

        $cmd    = "gunzip < {$fileEsc} | mysql -h {$hostEsc} -u {$userEsc} {$passFlag} {$dbEsc} 2>&1";
        $output = [];
        $code   = 0;
        exec($cmd, $output, $code);

        return [
            'success' => $code === 0,
            'message' => $code === 0 ? 'پایگاه داده بازیابی شد' : implode(', ', $output),
        ];
    }
}
