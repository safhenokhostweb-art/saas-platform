<?php
namespace App\Services\Installer;

use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Support\Facades\Artisan;

/**
 * Adapter for MODE 3 (Upgrade Platform) and Installation Baseline management.
 *
 * Baseline invariants:
 * - INITIAL_INSTALLATION_BASELINE is created once and never overwritten.
 * - Subsequent baselines are named INSTALLATION_BASELINE_YYYYMMDD_HHMMSS.
 * - All baseline directories are made read-only after finalization.
 * - manifest.json contains SHA-256 checksums for every file.
 */
class UpgradeAdapter
{
    const BASELINES_ROOT = 'upgrade_snapshots';

    public function __construct(
        private InfrastructureInstaller  $infra,
        private HealthCheckRunner        $health,
        private SlackAlertService        $slack,
        private BaselineManifestService  $manifest,
    ) {}

    // ─── Snapshot (Upgrade Mode) ──────────────────────────────────────────────

    public function createSnapshot(): array
    {
        $timestamp = now()->format('Ymd_His');
        $snapDir   = storage_path(self::BASELINES_ROOT . "/{$timestamp}");
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
        $db      = config('database.connections.mysql');
        $outFile = "{$snapDir}/database.sql.gz";

        $dbNameEsc = escapeshellarg($db['database']);
        $userEsc   = escapeshellarg($db['username']);
        $hostEsc   = escapeshellarg($db['host']);
        $outEsc    = escapeshellarg($outFile);
        $passFlag  = !empty($db['password']) ? '-p' . escapeshellarg($db['password']) : '';

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

    // ─── Upgrade ──────────────────────────────────────────────────────────────

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

    // ─── Installation Baseline — Phase 1 (Core files) ────────────────────────

    /**
     * Phase 1: Creates the baseline core (database.sql.gz + .env.backup + baseline_meta.json + manifest.json).
     *
     * INITIAL_INSTALLATION_BASELINE is NEVER overwritten.
     * Subsequent calls produce INSTALLATION_BASELINE_YYYYMMDD_HHMMSS.
     *
     * Returns the baseline_path, manifest_checksum, and success status.
     * Phase 2 (addReportsToBaseline) must be called after HTML/PDF reports are generated.
     */
    public function createInstallationBaseline(string $installationUuid = ''): array
    {
        $basePath    = storage_path(self::BASELINES_ROOT);
        @mkdir($basePath, 0750, true);

        // Resolve name — never overwrites INITIAL
        $baselineDir = $this->manifest->resolveBaselineName($basePath);
        $label       = basename($baselineDir);

        @mkdir($baselineDir, 0750, true);

        $results = [];

        // 1. Database snapshot
        $results['database'] = $this->snapshotDatabase($baselineDir);

        // 2. .env backup (secrets stay in backup, never exposed in manifest values)
        $results['env'] = $this->snapshotEnv($baselineDir);

        // 3. Baseline metadata (no secrets)
        $meta = [
            'label'            => $label,
            'installation_uuid'=> $installationUuid ?: \Illuminate\Support\Str::uuid()->toString(),
            'created_at'       => now()->toISOString(),
            'version'          => config('app.version', '1.0.0'),
            'mode'             => 'fresh',
            'description'      => $label === 'INITIAL_INSTALLATION_BASELINE'
                ? 'Permanent initial installation baseline — primary rollback point for all future upgrades'
                : 'Supplemental installation baseline',
            'components'       => ['database', 'env', 'baseline_meta'],
            'platform'         => config('app.name'),
        ];
        file_put_contents(
            $baselineDir . '/baseline_meta.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        $results['meta'] = ['success' => true, 'path' => $baselineDir . '/baseline_meta.json'];

        // 4. Create manifest (checksums for database + .env.backup + baseline_meta.json)
        $manifestResult = $this->manifest->createManifest($baselineDir, [
            'installation_uuid' => $meta['installation_uuid'],
            'label'             => $label,
        ]);
        $results['manifest'] = $manifestResult;

        $allSuccess = $results['database']['success'] && $results['env']['success'] && $manifestResult['success'];

        if (!$allSuccess) {
            $this->slack->warning("Installer: Baseline {$label} created with failures");
        }

        return [
            'success'           => $allSuccess,
            'baseline_path'     => $baselineDir,
            'baseline_label'    => $label,
            'installation_uuid' => $meta['installation_uuid'],
            'manifest_checksum' => $manifestResult['manifest_checksum'] ?? null,
            'results'           => $results,
            'message'           => $allSuccess
                ? "{$label} created successfully"
                : "Baseline created with failures",
        ];
    }

    // ─── Installation Baseline — Phase 2 (Finalize with reports) ─────────────

    /**
     * Phase 2: Copies installation reports into the baseline, updates the manifest
     * with their checksums, then locks the directory as read-only.
     *
     * Must be called after createInstallationBaseline() and after HTML/PDF are generated.
     */
    public function addReportsToBaseline(string $baselineDir, ?string $htmlPath, ?string $pdfPath): array
    {
        if (!is_dir($baselineDir)) {
            return ['success' => false, 'message' => 'Baseline directory not found'];
        }

        $copied = [];

        foreach (array_filter([$htmlPath, $pdfPath]) as $rp) {
            if (file_exists($rp)) {
                $dest = $baselineDir . '/' . basename($rp);
                $ok   = copy($rp, $dest);
                $ext  = pathinfo($rp, PATHINFO_EXTENSION);
                $copied["report_{$ext}"] = [
                    'success' => $ok,
                    'path'    => $ok ? $dest : null,
                    'message' => $ok ? "Report ({$ext}) copied to baseline" : "Failed to copy {$ext} report",
                ];
            }
        }

        // Recompute manifest to include the newly added reports
        $manifestResult = $this->manifest->updateManifest($baselineDir);

        // Lock the directory — immutable from this point
        $this->manifest->makeReadOnly($baselineDir);

        $this->slack->info('Installer: ' . basename($baselineDir) . ' finalized and locked as read-only');

        return [
            'success'           => $manifestResult['success'],
            'baseline_path'     => $baselineDir,
            'manifest_checksum' => $manifestResult['manifest_checksum'] ?? null,
            'copied'            => $copied,
            'message'           => 'Baseline finalized with reports',
        ];
    }

    // ─── Rollback ─────────────────────────────────────────────────────────────

    public function rollback(string $snapshotPath): array
    {
        if (!is_dir($snapshotPath)) {
            return ['success' => false, 'message' => "مسیر اسنپ‌شات یافت نشد: {$snapshotPath}"];
        }

        $results = [];

        $dbFile = "{$snapshotPath}/database.sql.gz";
        if (file_exists($dbFile)) {
            $results['database'] = $this->restoreDatabase($dbFile);
        }

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
        $db       = config('database.connections.mysql');
        $fileEsc  = escapeshellarg($gzipFile);
        $dbEsc    = escapeshellarg($db['database']);
        $userEsc  = escapeshellarg($db['username']);
        $hostEsc  = escapeshellarg($db['host']);
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

    // ─── Baselines Root ───────────────────────────────────────────────────────

    public function getBaselinesRootPath(): string
    {
        return storage_path(self::BASELINES_ROOT);
    }
}
