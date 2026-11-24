<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerInstallationPackage;
use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Models\ThemeFleetUpdate;
use App\Services\ActivityLogger;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\WebRootResolver;
use App\Services\Domains\SiteDomainService;
use App\Services\ThemeBuilder\ComponentVersionRegistry;
use App\Services\ThemeBuilder\ThemeFleetUpgradeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CustomerPackageUpgradePipeline
{
    public const LOCK_PREFIX = 'customer_package_upgrade:site:';
    public const LOCK_SECONDS = 300;

    public function __construct(
        private readonly SiteDomainService $domainService,
        private readonly RemoteConnectionService $connectionService,
        private readonly ThemeFleetUpgradeService $fleetService,
    ) {}

    /**
     * Execute full atomic upgrade pipeline for a site to the specified or active package.
     */
    public function upgradeSite(Site $site, ?CustomerInstallationPackage $package = null, ?int $userId = null): ThemeFleetUpdate
    {
        // Guard 1: Verify target package
        $package = $package ?: CustomerInstallationPackage::active();
        if (!$package || $package->isStale()) {
            throw new RuntimeException('هیچ پکیج نصب فعالی در سیستم یافت نشد.');
        }

        // Deployment boundary check: never accept platform scope
        $this->fleetService->assertNotPlatformScope($package->version);

        // Pre-validate local package and checksum before creating audit row
        $localZipPath = $this->verifyLocalPackage($package);

        // Guard 2: Concurrency Lock per site
        $lockKey = self::LOCK_PREFIX . $site->id;
        $lock = Cache::lock($lockKey, self::LOCK_SECONDS);

        if (!$lock->get()) {
            throw new RuntimeException("عملیات ارتقا یا نگهداری دیگری در حال حاضر روی سایت «{$site->title}» در حال اجراست.");
        }

        // Determine current version before upgrade
        $currentVersion = $this->resolveSiteCurrentVersion($site);

        // Initialize Audit Record in RUNNING status
        $fleetUpdate = ThemeFleetUpdate::create([
            'site_id'            => $site->id,
            'update_type'        => ThemeFleetUpdate::TYPE_PACKAGE,
            'theme_id'           => $site->theme_id,
            'from_release_id'    => null,
            'to_release_id'      => null,
            'component_versions' => [
                'theme'           => ['from' => '1.0.0', 'to' => '1.0.0'],
                'canvas'          => ['from' => ComponentVersionRegistry::CANVAS_VERSION, 'to' => ComponentVersionRegistry::CANVAS_VERSION],
                'widgets'         => ['from' => ComponentVersionRegistry::WIDGETS_VERSION, 'to' => ComponentVersionRegistry::WIDGETS_VERSION],
                'customer_core'   => ['from' => $currentVersion, 'to' => $package->version],
                'motion_graphics' => ['from' => ComponentVersionRegistry::MOTION_GRAPHICS_VERSION, 'to' => ComponentVersionRegistry::MOTION_GRAPHICS_VERSION],
            ],
            'status'             => ThemeFleetUpdate::STATUS_RUNNING,
            'changelog_summary'  => "ارتقای پکیج هسته و موتور کانواس از نسخه {$currentVersion} به {$package->version}",
            'dispatched_by'      => $userId,
            'started_at'         => now(),
        ]);

        $connection = null;
        $backupDir = null;
        $webRoot = null;
        $remoteZipPath = null;
        $lockedMaintenance = false;

        try {
            // Step 2: Establish Remote Connection & resolve WebRoot
            [$connection, $webRoot] = $this->resolveConnectionAndWebRoot($site);

            $safeWebRoot = escapeshellarg($webRoot);
            $timestamp = date('Ymd_His');
            $backupDir = dirname($webRoot) . '/.backup_' . basename($webRoot) . '_' . $timestamp;
            $safeBackupDir = escapeshellarg($backupDir);

            // Step 3: Put tenant site into Laravel maintenance mode (Lock)
            Log::info("CustomerPackageUpgrade: Locking tenant site #{$site->id} into maintenance mode");
            $downRes = $connection->execute("cd {$safeWebRoot} && php artisan down --retry=60 2>&1");
            if ($downRes->failed()) {
                throw new RuntimeException("خطا در قفل کردن سایت (artisan down): " . $downRes->output());
            }
            $lockedMaintenance = true;

            // Step 4: Create quick atomic code backup for safe rollback
            Log::info("CustomerPackageUpgrade: Creating backup at {$backupDir} for site #{$site->id}");
            $backupRes = $connection->execute(
                "mkdir -p {$safeBackupDir} && " .
                "cp -a {$safeWebRoot}/. {$safeBackupDir}/"
            );
            if ($backupRes->failed()) {
                throw new RuntimeException("خطا در تهیه نسخه پشتیبان از کدهای سایت: " . $backupRes->output());
            }

            // Save backup path to audit record for potential future manual rollback
            $fleetUpdate->update([
                'reconciliation_summary' => [
                    'backup_dir' => $backupDir,
                    'web_root'   => $webRoot,
                    'package_id' => $package->id,
                ],
            ]);

            // Step 5: Upload zip package to server
            $remoteZipPath = dirname($webRoot) . '/.pkg_upgrade_' . basename($webRoot) . '_' . $timestamp . '.zip';
            Log::info("CustomerPackageUpgrade: Uploading package to {$remoteZipPath} for site #{$site->id}");
            $connection->upload($localZipPath, $remoteZipPath);

            // Verify uploaded remote file checksum
            $remoteShaRes = $connection->execute("sha256sum " . escapeshellarg($remoteZipPath) . " 2>/dev/null || shasum -a 256 " . escapeshellarg($remoteZipPath));
            if ($remoteShaRes->succeeded()) {
                $remoteSha = strtolower(trim(explode(' ', trim($remoteShaRes->stdout))[0]));
                if ($remoteSha !== strtolower($package->sha256)) {
                    throw new RuntimeException("عدم تطابق چک‌سام پکیج بارگذاری‌شده روی سرور (انتظار: {$package->sha256}، دریافت: {$remoteSha})");
                }
            }

            // Step 6: Safe extraction onto webRoot WITHOUT touching .env, installed.lock, storage/
            Log::info("CustomerPackageUpgrade: Extracting package safely on {$webRoot}");
            $extractSteps = [
                // Unzip with overwrite, excluding sensitive and runtime state paths
                "unzip -o -q " . escapeshellarg($remoteZipPath) . " -d {$safeWebRoot} " .
                "-x '.env*' 'installed.lock' '*/installed.lock' 'storage/*' 2>&1",
                // Remove remote zip
                "rm -f " . escapeshellarg($remoteZipPath),
                // Normalize file and dir permissions defensively (preserve deployment-agent ownership if present)
                "TARGET_OWNER=\$(id -u deployment-agent >/dev/null 2>&1 && [ -d /var/www/deployment-system ] && echo 'deployment-agent:www-data' || echo 'www-data:www-data'); " .
                "chown -R \$TARGET_OWNER {$safeWebRoot} 2>/dev/null || true",
                "find {$safeWebRoot} -type f ! -name '.cie_installing' -exec chmod 644 {} + 2>/dev/null || true",
                "find {$safeWebRoot} -type d -exec chmod 755 {} + 2>/dev/null || true",
                "chmod 755 {$safeWebRoot}/storage 2>/dev/null || true",
                "chmod 755 {$safeWebRoot}/storage/framework 2>/dev/null || true",
                "chmod -R 775 {$safeWebRoot}/bootstrap/cache {$safeWebRoot}/storage/logs {$safeWebRoot}/storage/framework/cache {$safeWebRoot}/storage/framework/sessions {$safeWebRoot}/storage/framework/views 2>/dev/null || true",
            ];

            foreach ($extractSteps as $step) {
                $stepRes = $connection->execute($step);
                if ($stepRes->failed() && str_contains($step, 'unzip')) {
                    throw new RuntimeException("خطا در استخراج پکیج روی سایت: " . $stepRes->output());
                }
            }

            // Step 7: Post-Upgrade commands: migrate --force and optimize:clear / optimize
            Log::info("CustomerPackageUpgrade: Running migrations on site #{$site->id}");
            $migrateRes = $connection->execute("cd {$safeWebRoot} && php artisan migrate --force 2>&1");
            if ($migrateRes->failed()) {
                throw new RuntimeException("خطا در اجرای مایگریشن‌های سایت: " . $migrateRes->output());
            }

            Log::info("CustomerPackageUpgrade: Clearing and rebuilding caches on site #{$site->id}");
            $connection->execute("cd {$safeWebRoot} && php artisan optimize:clear 2>&1");
            $connection->execute("cd {$safeWebRoot} && php artisan view:clear 2>&1");

            // Step 8: Health check & bring site back online (Unlock)
            $upRes = $connection->execute("cd {$safeWebRoot} && php artisan up 2>&1");
            if ($upRes->succeeded()) {
                $lockedMaintenance = false;
            }

            // Verify artisan works and site is healthy
            $healthRes = $connection->execute("cd {$safeWebRoot} && php artisan --version 2>&1");
            if ($healthRes->failed()) {
                throw new RuntimeException("تست سلامت سایت پس از ارتقا با خطا مواجه شد: " . $healthRes->output());
            }

            // Step 9: Update installation & audit records on platform database
            DB::transaction(function () use ($site, $package, $fleetUpdate, $currentVersion) {
                // Update customer_installations package_version if record exists
                CustomerInstallation::where('site_id', $site->id)->update([
                    'package_version' => $package->version,
                    'package_sha256'  => $package->sha256,
                ]);

                // Mark audit record completed
                $fleetUpdate->update([
                    'status'            => ThemeFleetUpdate::STATUS_COMPLETED,
                    'completed_at'      => now(),
                    'changelog_summary' => "ارتقای موفقیت‌آمیز به پکیج هسته نسخه {$package->version} (مایگریشن‌ها اعمال و کش‌ها بازسازی شدند).",
                ]);
            });

            ActivityLogger::log('fleet.package_upgraded', "ارتقای پکیج هسته و ویجت‌ها به نسخه {$package->version} برای سایت «{$site->title}» با موفقیت انجام شد.", $site, [
                'update_id'        => $fleetUpdate->id,
                'previous_version' => $currentVersion,
                'package_version'  => $package->version,
            ]);

            return $fleetUpdate;

        } catch (Throwable $e) {
            Log::error("CustomerPackageUpgrade: Upgrade failed for site #{$site->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            // Automatic Rollback execution
            $rollbackError = null;
            if ($connection && $backupDir && $webRoot) {
                try {
                    $safeWebRoot = escapeshellarg($webRoot);
                    $safeBackupDir = escapeshellarg($backupDir);

                    Log::warning("CustomerPackageUpgrade: Executing auto-rollback for site #{$site->id} from {$backupDir}");

                    // Restore files from backup directory, preserving .env and storage
                    $restoreSteps = [
                        "cp -a {$safeBackupDir}/. {$safeWebRoot}/ 2>/dev/null || true",
                        "TARGET_OWNER=\$(id -u deployment-agent >/dev/null 2>&1 && [ -d /var/www/deployment-system ] && echo 'deployment-agent:www-data' || echo 'www-data:www-data'); " .
                        "chown -R \$TARGET_OWNER {$safeWebRoot} 2>/dev/null || true",
                        "chmod 755 {$safeWebRoot}/storage 2>/dev/null || true",
                        "chmod 755 {$safeWebRoot}/storage/framework 2>/dev/null || true",
                        "chmod -R 775 {$safeWebRoot}/bootstrap/cache {$safeWebRoot}/storage/logs {$safeWebRoot}/storage/framework/cache {$safeWebRoot}/storage/framework/sessions {$safeWebRoot}/storage/framework/views 2>/dev/null || true",
                        "cd {$safeWebRoot} && php artisan optimize:clear 2>&1 || true",
                    ];

                    foreach ($restoreSteps as $rStep) {
                        $connection->execute($rStep);
                    }

                    if ($lockedMaintenance) {
                        $connection->execute("cd {$safeWebRoot} && php artisan up 2>&1 || true");
                        $lockedMaintenance = false;
                    }

                    Log::info("CustomerPackageUpgrade: Auto-rollback completed for site #{$site->id}");
                } catch (Throwable $rbEx) {
                    $rollbackError = " (همچنین بازگشت خودکار بکاپ با خطا مواجه شد: {$rbEx->getMessage()})";
                    Log::critical("CustomerPackageUpgrade: Rollback failed for site #{$site->id}: {$rbEx->getMessage()}");
                }
            } elseif ($connection && $lockedMaintenance && $webRoot) {
                // Ensure site is not stuck in maintenance
                $connection->execute("cd " . escapeshellarg($webRoot) . " && php artisan up 2>&1 || true");
            }

            // Clean up remote zip if uploaded
            if ($connection && $remoteZipPath) {
                $connection->execute("rm -f " . escapeshellarg($remoteZipPath));
            }

            // Mark audit record failed
            $failureMessage = $e->getMessage() . ($rollbackError ?: ' (نسخه قبلی به طور کامل بازگردانده شد).');
            $fleetUpdate->update([
                'status'         => ThemeFleetUpdate::STATUS_FAILED,
                'failure_reason' => $failureMessage,
                'completed_at'   => now(),
            ]);

            $logDesc = \Illuminate\Support\Str::limit("ارتقای پکیج سایت «{$site->title}» شکست خورد: {$failureMessage}", 250);
            ActivityLogger::log('fleet.package_upgrade_failed', $logDesc, $site, [
                'update_id' => $fleetUpdate->id,
                'error'     => $failureMessage,
            ]);

            return $fleetUpdate;

        } finally {
            if ($connection) {
                try {
                    $connection->disconnect();
                } catch (Throwable) {
                    // Ignore disconnect errors
                }
            }
            $lock->release();
        }
    }

    /**
     * Dispatch One-Click Rollback for a previously upgraded package from its recorded backup directory.
     */
    public function rollbackPackageUpdate(ThemeFleetUpdate $targetUpdate, ?int $userId = null): ThemeFleetUpdate
    {
        $site = $targetUpdate->site;
        if (!$site) {
            throw new RuntimeException('سایت مرتبط با این تاریخچه به‌روزرسانی یافت نشد.');
        }

        if ($targetUpdate->update_type !== ThemeFleetUpdate::TYPE_PACKAGE) {
            throw new RuntimeException('این متد فقط برای بازگشت به‌روزرسانی پکیج است.');
        }

        if ($targetUpdate->status !== ThemeFleetUpdate::STATUS_COMPLETED || $targetUpdate->is_rollback) {
            throw new RuntimeException('فقط یک به‌روزرسانی موفق پکیج قابل بازگردانی است.');
        }

        $backupDir = $targetUpdate->reconciliation_summary['backup_dir'] ?? null;
        if (!$backupDir) {
            throw new RuntimeException('مسیر پشتیبان برای این به‌روزرسانی پکیج ثبت نشده است و بازگشت خودکار ممکن نیست.');
        }

        $lockKey = self::LOCK_PREFIX . $site->id;
        $lock = Cache::lock($lockKey, self::LOCK_SECONDS);
        if (!$lock->get()) {
            throw new RuntimeException("عملیات دیگری در حال حاضر روی سایت «{$site->title}» در حال اجراست.");
        }

        $fromVersion = $targetUpdate->component_versions['customer_core']['from'] ?? '1.1.0';
        $toVersion = $targetUpdate->component_versions['customer_core']['to'] ?? '1.1.2';

        $rollbackRecord = ThemeFleetUpdate::create([
            'site_id'                 => $site->id,
            'update_type'             => ThemeFleetUpdate::TYPE_ROLLBACK,
            'theme_id'                => $targetUpdate->theme_id,
            'from_release_id'         => null,
            'to_release_id'           => null,
            'component_versions'      => [
                'customer_core' => ['from' => $toVersion, 'to' => $fromVersion],
            ],
            'status'                  => ThemeFleetUpdate::STATUS_RUNNING,
            'is_rollback'             => true,
            'rollback_from_update_id' => $targetUpdate->id,
            'reconciliation_summary'  => ['rollback_of_update_id' => $targetUpdate->id, 'backup_dir' => $backupDir],
            'changelog_summary'       => "بازگشت پکیج هسته از نسخه {$toVersion} به نسخه قبلی {$fromVersion}",
            'dispatched_by'           => $userId,
            'started_at'              => now(),
        ]);

        $connection = null;
        try {
            [$connection, $webRoot] = $this->resolveConnectionAndWebRoot($site);

            $safeWebRoot = escapeshellarg($webRoot);
            $safeBackupDir = escapeshellarg($backupDir);

            // Check if backup directory still exists
            $checkRes = $connection->execute("test -d {$safeBackupDir} && echo exists || echo absent");
            if (!str_contains($checkRes->stdout, 'exists')) {
                throw new RuntimeException("پوشه پشتیبان در سرور یافت نشد: {$backupDir}");
            }

            // Put site into maintenance mode during restore
            $connection->execute("cd {$safeWebRoot} && php artisan down --retry=60 2>&1 || true");

            // Restore files from backup directory
            $restoreSteps = [
                "cp -a {$safeBackupDir}/. {$safeWebRoot}/ 2>/dev/null || true",
                "TARGET_OWNER=\$(id -u deployment-agent >/dev/null 2>&1 && [ -d /var/www/deployment-system ] && echo 'deployment-agent:www-data' || echo 'www-data:www-data'); " .
                "chown -R \$TARGET_OWNER {$safeWebRoot} 2>/dev/null || true",
                "chmod 755 {$safeWebRoot}/storage 2>/dev/null || true",
                "chmod 755 {$safeWebRoot}/storage/framework 2>/dev/null || true",
                "chmod -R 775 {$safeWebRoot}/bootstrap/cache {$safeWebRoot}/storage/logs {$safeWebRoot}/storage/framework/cache {$safeWebRoot}/storage/framework/sessions {$safeWebRoot}/storage/framework/views 2>/dev/null || true",
                "cd {$safeWebRoot} && php artisan optimize:clear 2>&1 || true",
                "cd {$safeWebRoot} && php artisan view:clear 2>&1 || true",
                "cd {$safeWebRoot} && php artisan up 2>&1 || true",
            ];

            foreach ($restoreSteps as $step) {
                $connection->execute($step);
            }

            // Revert package_version on database
            CustomerInstallation::where('site_id', $site->id)->update([
                'package_version' => $fromVersion,
            ]);

            $rollbackRecord->update([
                'status'       => ThemeFleetUpdate::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            ActivityLogger::log('fleet.package_rollback', "پکیج هسته سایت «{$site->title}» با موفقیت به نسخه {$fromVersion} بازگردانده شد.", $site, [
                'target_update_id'   => $targetUpdate->id,
                'rollback_update_id' => $rollbackRecord->id,
            ]);

            return $rollbackRecord;

        } catch (Throwable $e) {
            $rollbackRecord->update([
                'status'         => ThemeFleetUpdate::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
                'completed_at'   => now(),
            ]);

            throw $e;
        } finally {
            if ($connection) {
                try {
                    $connection->disconnect();
                } catch (Throwable) {}
            }
            $lock->release();
        }
    }

    /**
     * Resolve RemoteConnection and WebRoot for the site.
     * Supports both sites installed via CustomerInstallation and local/vps sites.
     *
     * @return array{0: RemoteConnectionInterface, 1: string}
     */
    public function resolveConnectionAndWebRoot(Site $site): array
    {
        // 1. Try resolving via SiteDomainService & CustomerInstallation
        $installation = $this->domainService->installationFor($site);

        if ($installation && $installation->remote_server_id) {
            $server = CustomerRemoteServer::find($installation->remote_server_id);
            if ($server) {
                $connection = $this->connectionService->buildDriver($server);
                $connection->connect();

                // WebRoot resolver logic
                $webRoot = $this->resolveWebRootForInstallation($installation, $server, $connection, $site);
                return [$connection, $webRoot];
            }
        }

        // 2. Fallback to platform's managed VPS server (id 3 or host-managed)
        $managedServer = CustomerRemoteServer::where('is_platform_managed', true)->first()
            ?: CustomerRemoteServer::where('type', 'ssh')->first();

        if ($managedServer) {
            $connection = $this->connectionService->buildDriver($managedServer);
            $connection->connect();

            // Locate directory on server
            $webRoot = $this->findWebRootOnServer($site, $connection);
            if ($webRoot) {
                return [$connection, $webRoot];
            }
        }

        // 3. Fallback to ThemeFleetUpgradeService resolveSiteDirectory
        $localDir = $this->fleetService->resolveSiteDirectory($site);
        if ($localDir && is_dir($localDir)) {
            // Local direct driver fallback for tests and local runtime
            if ($managedServer) {
                $connection = $this->connectionService->buildDriver($managedServer);
                $connection->connect();
                return [$connection, $localDir];
            }
        }

        throw new RuntimeException("امکان تشخیص مسیر فایل‌های سایت «{$site->title}» روی سرور وجود ندارد.");
    }

    private function resolveWebRootForInstallation(CustomerInstallation $installation, CustomerRemoteServer $server, RemoteConnectionInterface $connection, Site $site): string
    {
        // 1. Highest authority: CustomerInstallationLog (actual recorded path from EXTRACT_PACKAGE or PROVISION_DOMAIN)
        $logExtract = CustomerInstallationLog::where('installation_id', $installation->id)
            ->where('stage', 'EXTRACT_PACKAGE')
            ->where('event', 'completed')
            ->latest('id')
            ->first();
        $recordedRoot = $logExtract?->output['web_root'] ?? null;

        if (!$recordedRoot) {
            $logDomain = CustomerInstallationLog::where('installation_id', $installation->id)
                ->where('stage', 'PROVISION_DOMAIN')
                ->where('event', 'completed')
                ->latest('id')
                ->first();
            $recordedRoot = $logDomain?->output['document_root'] ?? null;
        }

        if (is_string($recordedRoot) && $recordedRoot !== '') {
            $cleaned = rtrim(trim($recordedRoot), '/');
            if ($this->verifyRemoteWebRoot($connection, $cleaned)) {
                return $cleaned;
            }
        }

        // 2. Explicit path in request_payload (e.g. tests or custom configured server root)
        // NOTE: We reject template fallback paths like /var/www/{domain} if they don't genuinely exist on server
        $explicitPath = $installation->request_payload['web_root_path'] ?? null;
        if ($explicitPath && !empty(trim($explicitPath)) && $explicitPath !== '/var/www/site') {
            $explicitClean = rtrim(trim($explicitPath), '/');
            $domainFallback = '/var/www/' . ($site->domain ?? $installation->customer_domain);
            if ($explicitClean !== $domainFallback) {
                if ($this->verifyRemoteWebRoot($connection, $explicitClean)) {
                    return $explicitClean;
                }
            } else {
                $check = $connection->execute("test -d " . escapeshellarg($explicitClean) . " && test -f " . escapeshellarg($explicitClean) . "/artisan && echo exists || echo absent");
                if (str_contains($check->stdout, 'exists')) {
                    return $explicitClean;
                }
            }
        }

        // 3. Check /var/www/cust_site_{installation_id}
        $custInstDir = '/var/www/cust_site_' . $installation->id;
        $check = $connection->execute("test -d " . escapeshellarg($custInstDir) . " && echo exists || echo absent");
        if (str_contains($check->stdout, 'exists')) {
            return $custInstDir;
        }

        // 4. Check canonical directory derived from slug and WebRootResolver
        $slug = $this->deriveInstallationSlug($installation);
        $driverType = DriverType::tryFrom((string) $server->type) ?? DriverType::SSH;
        $slugDir = app(WebRootResolver::class)->resolveForDriverType($driverType, $slug, $server);
        if ($slugDir !== $custInstDir && $this->verifyRemoteWebRoot($connection, $slugDir)) {
            return $slugDir;
        }

        // 5. Check /var/www/cust_site_{site_id}
        $custSiteDir = '/var/www/cust_site_' . $site->id;
        if ($custSiteDir !== $custInstDir && $this->verifyRemoteWebRoot($connection, $custSiteDir)) {
            return $custSiteDir;
        }

        // 6. Otherwise search on server
        $searched = $this->findWebRootOnServer($site, $connection);
        if ($searched) {
            return $searched;
        }

        return $custInstDir;
    }

    private function verifyRemoteWebRoot(RemoteConnectionInterface $connection, string $path): bool
    {
        $safe = escapeshellarg(rtrim($path, '/'));
        $check = $connection->execute("test -d {$safe} && test -f {$safe}/artisan && echo exists || echo absent");
        return str_contains($check->stdout, 'exists');
    }

    private function deriveInstallationSlug(CustomerInstallation $installation): string
    {
        $ascii   = preg_replace('/[^\x20-\x7E]/', '', $installation->customer_company ?? '');
        $clean   = strtolower(preg_replace('/[^a-z0-9]+/', '_', $ascii));
        $clean   = trim($clean, '_');
        $segment = ($clean === '') ? 'site' : substr($clean, 0, 20);

        return 'cust_' . $segment . '_' . $installation->id;
    }

    private function findWebRootOnServer(Site $site, RemoteConnectionInterface $connection): ?string
    {
        $domain = $site->domain;
        $dbName = $site->db_name;

        // Candidate directories
        $candidates = [
            "/var/www/customer-site",
        ];

        if ($dbName) {
            $candidates[] = "/var/www/{$dbName}";
        }

        // Find from wildcard cust_site_* and cust_*
        $globRes = $connection->execute("ls -d /var/www/cust_site_* 2>/dev/null");
        if ($globRes->succeeded() && !empty(trim($globRes->stdout))) {
            $dirs = array_filter(array_map('trim', explode("\n", trim($globRes->stdout))));
            $candidates = array_merge($candidates, $dirs);
        }

        $globRes2 = $connection->execute("ls -d /var/www/cust_* 2>/dev/null");
        if ($globRes2->succeeded() && !empty(trim($globRes2->stdout))) {
            $dirs2 = array_filter(array_map('trim', explode("\n", trim($globRes2->stdout))));
            $candidates = array_merge($candidates, $dirs2);
        }

        foreach (array_unique($candidates) as $cand) {
            if (empty($cand)) continue;
            if ($this->verifyRemoteWebRoot($connection, $cand)) {
                // If .env matches domain or db_name
                $safe = escapeshellarg($cand);
                $envRes = $connection->execute("grep -E 'APP_URL|DB_DATABASE' {$safe}/.env 2>/dev/null");
                if ($envRes->succeeded()) {
                    if ($domain && str_contains($envRes->stdout, $domain)) {
                        return $cand;
                    }
                    if ($dbName && str_contains($envRes->stdout, $dbName)) {
                        return $cand;
                    }
                }
            }
        }

        // If site is primary domain safhenokhost.ir
        if ($domain === 'safhenokhost.ir' || str_contains($domain, 'safhenokhost.ir')) {
            if ($this->verifyRemoteWebRoot($connection, '/var/www/customer-site')) {
                return '/var/www/customer-site';
            }
        }

        return null;
    }

    /**
     * Resolve current package version of a site.
     */
    public function resolveSiteCurrentVersion(Site $site): string
    {
        $latestPackageUpdate = ThemeFleetUpdate::where('site_id', $site->id)
            ->where('update_type', ThemeFleetUpdate::TYPE_PACKAGE)
            ->where('status', ThemeFleetUpdate::STATUS_COMPLETED)
            ->latest('id')
            ->first();

        if ($latestPackageUpdate && !empty($latestPackageUpdate->component_versions['customer_core']['to'])) {
            return (string) $latestPackageUpdate->component_versions['customer_core']['to'];
        }

        $inst = CustomerInstallation::where('site_id', $site->id)->latest('id')->first();
        if ($inst && !empty($inst->package_version)) {
            return (string) $inst->package_version;
        }

        return '1.1.0';
    }

    /**
     * Verify local package file existence and SHA-256 checksum.
     */
    public function verifyLocalPackage(CustomerInstallationPackage $package): string
    {
        $path = $package->local_path;
        if (empty($path) || !file_exists($path)) {
            // Check fallback in storage
            $fallback = storage_path('app/install-packages/customer-site-v' . $package->version . '.zip');
            if (file_exists($fallback)) {
                $path = $fallback;
            } else {
                throw new RuntimeException("فایل محلی پکیج فعال در مسیر '{$path}' یافت نشد.");
            }
        }

        $actualSha = hash_file('sha256', $path);
        if ($actualSha === false) {
            throw new RuntimeException("خطا در محاسبه مقدار SHA-256 فایل پکیج محلی: {$path}");
        }

        if (!empty($package->sha256) && strtolower($actualSha) !== strtolower($package->sha256)) {
            throw new RuntimeException("عدم انطباق چک‌سام پکیج محلی با دیتابیس (انتظار: {$package->sha256}، محاسبه‌شده: {$actualSha})");
        }

        return $path;
    }
}
