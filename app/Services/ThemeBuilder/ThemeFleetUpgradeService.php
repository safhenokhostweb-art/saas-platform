<?php

namespace App\Services\ThemeBuilder;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationPackage;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\ThemeFleetUpdate;
use App\Models\ThemeLicense;
use App\Models\ThemeRelease;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Log;

class ThemeFleetUpgradeService
{
    public const FORBIDDEN_PLATFORM_PROJECT = 'saas-platform';

    public function __construct(
        private readonly ThemeInstallGateway $gateway,
    ) {}

    /**
     * Absolute Deployment Boundary Assertion:
     * - saas-platform is strictly deployed to platform servers via Deployment Hub.
     * - It must NEVER be treated as an update item in customer fleet management.
     */
    public function assertNotPlatformScope(string $projectKeyOrName): void
    {
        $normalized = strtolower(trim($projectKeyOrName));
        if ($normalized === self::FORBIDDEN_PLATFORM_PROJECT || $normalized === 'platform' || str_contains($normalized, 'saas-platform')) {
            throw new \InvalidArgumentException(
                'خطای انطباق مرز استقرار: مخزن saas-platform منحصراً از طریق Deployment Hub مستقر می‌شود و هرگز نباید به عنوان آیتم آپدیت مشتریان عرضه شود.'
            );
        }
    }

    /**
     * Auto-detect and sync latest customer-site packages from local storage / Hub deployment artifacts.
     */
    public function syncCustomerSitePackagesFromStorage(): void
    {
        $dir = storage_path('app/install-packages');
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/customer-site-v*.zip');
        if (empty($files)) {
            return;
        }

        $latestValid = null;

        foreach ($files as $file) {
            $baseName = basename($file);
            // Strict security guard: never accept a saas-platform package
            if (str_contains(strtolower($baseName), 'saas-platform')) {
                continue;
            }

            if (!preg_match('/customer-site-v(\d+\.\d+\.\d+.*)\.zip$/i', $baseName, $m)) {
                continue;
            }

            $version = $m[1];
            $sha256 = hash_file('sha256', $file);
            $sizeBytes = filesize($file);

            $pkg = CustomerInstallationPackage::where('version', $version)->first();
            if ($pkg) {
                $pkg->update([
                    'local_path' => $file,
                    'sha256'     => $sha256,
                    'size_bytes' => $sizeBytes,
                ]);
            } else {
                $pkg = CustomerInstallationPackage::create([
                    'version'       => $version,
                    'arvan_key'     => '',
                    'local_path'    => $file,
                    'sha256'        => $sha256,
                    'size_bytes'    => $sizeBytes,
                    'released_at'   => now(),
                    'is_active'     => false,
                    'release_notes' => "بسته نرم‌افزاری customer-site نسخه {$version} منتشرشده از Deployment Hub",
                ]);
            }

            if (!$pkg->isStale()) {
                if ($latestValid === null || version_compare($pkg->version, $latestValid->version, '>')) {
                    $latestValid = $pkg;
                }
            }
        }

        $currentActive = CustomerInstallationPackage::active();
        if ($latestValid !== null && ($currentActive === null || version_compare($latestValid->version, $currentActive->version, '>'))) {
            $latestValid->activate();
        }
    }

    /**
     * Discover all updates available across the fleet:
     * - Themes: Sites whose assigned/installed theme release is older than currentRelease
     * - Packages: Sites whose package_version is older than the active CustomerInstallationPackage
     * - Motions: Versioned motion assets with pending updates
     *
     * @return array{
     *     themes: list<array<string, mixed>>,
     *     packages: list<array<string, mixed>>,
     *     motions: list<array<string, mixed>>,
     *     components: array<string, mixed>,
     *     summary: array<string, int>
     * }
     */
    public function discoverAvailableUpdates(): array
    {
        // 0. Auto-sync any newly arrived packages from Hub / storage
        $this->syncCustomerSitePackagesFromStorage();

        $themeUpdates = [];
        $activePackage = CustomerInstallationPackage::active()
            ?? CustomerInstallationPackage::latest('id')->first();
        if (!$activePackage) {
            $coreVer = ComponentVersionRegistry::get('customer_core')['version'] ?? '1.3.0';
            $activePackage = CustomerInstallationPackage::firstOrCreate(
                ['version' => $coreVer],
                [
                    'arvan_key'     => 'packages/customer-site-v' . $coreVer . '.zip',
                    'sha256'        => hash('sha256', 'customer-site-v' . $coreVer),
                    'size_bytes'    => 15000000,
                    'release_notes' => 'ارتقای کتابخانه ویجت‌ها، ماژول‌های هسته، تمپلیت‌های احراز هویت و پایداری موتور کانواس',
                    'is_active'     => true,
                    'released_at'   => now(),
                ]
            );
        }
        $packageUpdates = [];
        $motionUpdates = [];

        // 1. Discover Theme Updates
        $themesWithReleases = Theme::with('currentRelease')->whereNotNull('current_release_id')->get()->keyBy('id');

        $sites = Site::with(['theme', 'themeAssignments.release'])->whereNotNull('domain')->get();

        foreach ($sites as $site) {
            $themeId = $site->theme_id;
            if (!$themeId || !isset($themesWithReleases[$themeId])) {
                continue;
            }

            $theme = $themesWithReleases[$themeId];
            $currentRelease = $theme->currentRelease;
            if (!$currentRelease) {
                continue;
            }

            // Find current installed release for this site
            $latestAssignment = $site->themeAssignments
                ->where('theme_id', $themeId)
                ->where('status', ThemeAssignment::STATUS_ASSIGNED)
                ->sortByDesc('id')
                ->first();

            $installedReleaseId = $latestAssignment?->theme_release_id;
            $installedVersion = $latestAssignment?->release?->version;

            if (!$installedReleaseId) {
                $license = ThemeLicense::where('site_id', $site->id)->where('theme_id', $themeId)->first();
                $installedReleaseId = $license?->installed_release_id;
                if ($installedReleaseId && $installedReleaseId == $currentRelease->id) {
                    $installedVersion = $currentRelease->version;
                }
            }

            $isOutdated = !$installedReleaseId || ($installedReleaseId !== $currentRelease->id);

            if ($isOutdated) {
                $eligibility = $this->checkEligibility($site, $theme, $currentRelease);

                $themeUpdates[] = [
                    'site_id'               => $site->id,
                    'site_title'            => $site->title,
                    'domain'                => $site->domain,
                    'theme_id'              => $theme->id,
                    'theme_name'            => $theme->name,
                    'theme_slug'            => $theme->slug,
                    'distribution_model'    => $theme->distribution_model ?? Theme::DISTRIBUTION_MARKETPLACE,
                    'installed_release_id'  => $installedReleaseId,
                    'installed_version'     => $installedVersion ?? '1.0.0',
                    'available_release_id'  => $currentRelease->id,
                    'available_version'     => $currentRelease->version,
                    'eligible'              => $eligibility['eligible'],
                    'eligibility_reason'    => $eligibility['reason'],
                ];
            }
        }

        // 2. Discover Core Package / Canvas Engine Updates
        if ($activePackage && !$activePackage->isStale()) {
            foreach ($sites as $site) {
                // Determine current version of this site
                $latestPackageUpdate = ThemeFleetUpdate::where('site_id', $site->id)
                    ->where('update_type', ThemeFleetUpdate::TYPE_PACKAGE)
                    ->where('status', ThemeFleetUpdate::STATUS_COMPLETED)
                    ->latest('id')
                    ->first();

                if ($latestPackageUpdate && !empty($latestPackageUpdate->component_versions['customer_core']['to'])) {
                    $currentVer = $latestPackageUpdate->component_versions['customer_core']['to'];
                } else {
                    $inst = CustomerInstallation::where('site_id', $site->id)->latest('id')->first();
                    $currentVer = $inst?->package_version ?? '1.0.0';
                }

                if (version_compare($currentVer, $activePackage->version, '<')) {
                    $packageUpdates[] = [
                        'site_id'           => $site->id,
                        'site_title'        => $site->title,
                        'domain'            => $site->domain,
                        'current_version'   => $currentVer,
                        'available_version' => $activePackage->version,
                        'release_notes'     => $activePackage->release_notes ?: 'ارتقای کتابخانه ویجت‌ها، ماژول‌های هسته، تمپلیت‌های احراز هویت و پایداری موتور کانواس',
                        'size_human'        => $activePackage->sizeHuman(),
                        'sha256'            => $activePackage->sha256 ?? '',
                        'eligible'          => true,
                    ];
                }
            }
        }

        // 3. Discover Motion Graphics Updates
        if (class_exists(\App\Modules\MotionGraphics\Models\MotionSiteAssetGrant::class)
            && \Illuminate\Support\Facades\Schema::hasTable('motion_site_asset_grants')
        ) {
            $activeGrants = \App\Modules\MotionGraphics\Models\MotionSiteAssetGrant::where('status', 'active')
                ->with('site')
                ->get();

            foreach ($activeGrants as $grant) {
                if ($grant->site && !empty($grant->note) && str_contains(strtolower((string) $grant->note), 'update_pending')) {
                    $motionUpdates[] = [
                        'grant_id'    => $grant->id,
                        'site_id'     => $grant->site->id,
                        'site_title'  => $grant->site->title,
                        'domain'      => $grant->site->domain,
                        'asset_key'   => $grant->asset_id,
                        'asset_title' => $grant->asset_id,
                    ];
                }
            }
        }

        return [
            'themes'     => $themeUpdates,
            'packages'   => $packageUpdates,
            'motions'    => $motionUpdates,
            'components' => ComponentVersionRegistry::all(),
            'summary'    => [
                'themes_count'   => count($themeUpdates),
                'packages_count' => count($packageUpdates),
                'motions_count'  => count($motionUpdates),
                'total_pending'  => count($themeUpdates) + count($packageUpdates) + count($motionUpdates),
            ],
        ];
    }

    /**
     * Smart Eligibility Guard:
     * - Site's assigned theme must match the target theme
     * - For private_license or priced themes, valid license must exist
     * - Target release must exist and be newer or valid
     */
    public function checkEligibility(Site $site, Theme $theme, ThemeRelease $targetRelease): array
    {
        // Guard 1: Theme Lineage Match
        if ($site->theme_id && (int) $site->theme_id !== (int) $theme->id) {
            return [
                'eligible' => false,
                'reason'   => 'قالب نصب‌شده روی این سایت با قالب هدف انتخابی همخوانی ندارد.',
            ];
        }

        // Guard 2: License Requirement
        $distModel = $theme->distribution_model ?? Theme::DISTRIBUTION_MARKETPLACE;
        $requiresLicense = ($distModel === Theme::DISTRIBUTION_PRIVATE_LICENSE)
            || ($distModel === Theme::DISTRIBUTION_MARKETPLACE && (int) ($theme->marketplace_price ?? 0) > 0);

        if ($requiresLicense) {
            $hasLicense = ThemeLicense::where('site_id', $site->id)
                ->where('theme_id', $theme->id)
                ->exists();

            if (!$hasLicense) {
                return [
                    'eligible' => false,
                    'reason'   => 'سایت فاقد لایسنس معتبر برای این قالب اختصاصی یا تجاری است.',
                ];
            }
        }

        return [
            'eligible' => true,
            'reason'   => 'واجد شرایط دریافت به‌روزرسانی',
        ];
    }

    /**
     * Execute non-destructive fleet update for a single site.
     */
    public function dispatchUpgrade(Site $site, ThemeRelease $release, ?int $userId = null): ThemeFleetUpdate
    {
        $theme = $release->theme;
        if (!$theme) {
            throw new \InvalidArgumentException('قالب مرتبط با این ریلیز یافت نشد.');
        }

        $eligibility = $this->checkEligibility($site, $theme, $release);
        if (!$eligibility['eligible']) {
            throw new \RuntimeException($eligibility['reason']);
        }

        // Determine current release ID before upgrade
        $latestAssignment = ThemeAssignment::where('site_id', $site->id)
            ->where('theme_id', $theme->id)
            ->where('status', ThemeAssignment::STATUS_ASSIGNED)
            ->latest('id')
            ->first();

        $fromReleaseId = $latestAssignment?->theme_release_id;
        $fromVersion = $latestAssignment?->release?->version;
        if (!$fromReleaseId) {
            $license = ThemeLicense::where('site_id', $site->id)->where('theme_id', $theme->id)->first();
            $fromReleaseId = $license?->installed_release_id;
            $fromVersion = $fromReleaseId ? ThemeRelease::find($fromReleaseId)?->version : '1.0.0';
        }

        $componentVersions = [
            'theme'           => ['from' => $fromVersion ?? '1.0.0', 'to' => $release->version],
            'canvas'          => ['from' => ComponentVersionRegistry::CANVAS_VERSION, 'to' => ComponentVersionRegistry::CANVAS_VERSION],
            'widgets'         => ['from' => ComponentVersionRegistry::WIDGETS_VERSION, 'to' => ComponentVersionRegistry::WIDGETS_VERSION],
            'customer_core'   => ['from' => ComponentVersionRegistry::CUSTOMER_CORE_VERSION, 'to' => ComponentVersionRegistry::CUSTOMER_CORE_VERSION],
            'motion_graphics' => ['from' => ComponentVersionRegistry::MOTION_GRAPHICS_VERSION, 'to' => ComponentVersionRegistry::MOTION_GRAPHICS_VERSION],
        ];

        $changelogSummary = ComponentVersionRegistry::formatChangelogSummary('theme', ['theme' => $fromVersion ?? '1.0.0'], ['theme' => $release->version]);

        // Create audit record
        $fleetUpdate = ThemeFleetUpdate::create([
            'site_id'            => $site->id,
            'update_type'        => ThemeFleetUpdate::TYPE_THEME,
            'theme_id'           => $theme->id,
            'from_release_id'    => $fromReleaseId,
            'to_release_id'      => $release->id,
            'component_versions' => $componentVersions,
            'status'             => ThemeFleetUpdate::STATUS_RUNNING,
            'changelog_summary'  => $changelogSummary,
            'dispatched_by'      => $userId,
            'started_at'         => now(),
        ]);

        // Execute server-to-server 3-way merge upgrade
        $result = $this->gateway->upgrade($site, $release);

        if (!$result->success) {
            $fleetUpdate->update([
                'status'         => ThemeFleetUpdate::STATUS_FAILED,
                'failure_reason' => $result->message,
                'completed_at'   => now(),
            ]);

            Log::error('Fleet update failed for site', [
                'site_id'  => $site->id,
                'theme_id' => $theme->id,
                'error'    => $result->message,
            ]);

            return $fleetUpdate;
        }

        // Success: update ThemeAssignment and ThemeLicense
        $payload = $result->body ?? [];

        ThemeAssignment::updateOrCreate(
            [
                'site_id'          => $site->id,
                'theme_id'         => $theme->id,
                'theme_release_id' => $release->id,
            ],
            [
                'domain'      => $site->domain,
                'source'      => ThemeAssignment::SOURCE_ADMIN,
                'status'      => ThemeAssignment::STATUS_ASSIGNED,
                'assigned_by' => $userId,
            ]
        );

        ThemeLicense::where('site_id', $site->id)
            ->where('theme_id', $theme->id)
            ->update([
                'installed_release_id' => $release->id,
                'licensed_release_id'  => $release->id,
            ]);

        $fleetUpdate->update([
            'status'                 => ThemeFleetUpdate::STATUS_COMPLETED,
            'revision_number'        => $payload['revision_number'] ?? null,
            'reconciliation_summary' => $payload['reconciliation_metrics'] ?? ($payload['metrics'] ?? null),
            'completed_at'           => now(),
        ]);
        $fleetUpdate->refresh();

        ActivityLogger::log('fleet.theme_upgraded', "ارتقای غیرمخرب قالب «{$theme->name}» به نسخه {$release->version} روی سایت {$site->domain}", $site, [
            'update_id'        => $fleetUpdate->id,
            'theme_release_id' => $release->id,
            'revision_number'  => $payload['revision_number'] ?? null,
        ]);

        return $fleetUpdate;
    }

    /**
     * Dispatch Customer Site Package / Canvas Core Upgrade.
     */
    public function dispatchPackageUpgrade(Site $site, CustomerInstallationPackage $package, ?int $userId = null): ThemeFleetUpdate
    {
        $this->assertNotPlatformScope($package->version);

        $inst = CustomerInstallation::where('site_id', $site->id)->first();
        $currentVer = $inst?->package_version ?? '1.0.0';

        $componentVersions = [
            'theme'           => ['from' => $site->theme?->version ?? '1.0.0', 'to' => $site->theme?->version ?? '1.0.0'],
            'canvas'          => ['from' => '2.1.0', 'to' => ComponentVersionRegistry::CANVAS_VERSION],
            'widgets'         => ['from' => '2.4.0', 'to' => ComponentVersionRegistry::WIDGETS_VERSION],
            'customer_core'   => ['from' => $currentVer, 'to' => $package->version],
            'motion_graphics' => ['from' => ComponentVersionRegistry::MOTION_GRAPHICS_VERSION, 'to' => ComponentVersionRegistry::MOTION_GRAPHICS_VERSION],
        ];

        $syncResult = $this->syncSiteFilesAndViews($site);

        $changelog = $package->release_notes ?: "به‌روزرسانی پکیج هسته مشتری، ویجت‌ها و موتور کانواس به نسخه {$package->version}";
        if (!empty($syncResult['synced_files'])) {
            $changelog .= ' | همگام‌سازی فایل‌ها: ' . count($syncResult['synced_files']) . ' فایل ویو و کد به‌روزرسانی شد.';
        }
        if (!empty($syncResult['view_clear_output'])) {
            $changelog .= ' (view:clear اجرا شد)';
        }

        $fleetUpdate = ThemeFleetUpdate::create([
            'site_id'            => $site->id,
            'update_type'        => ThemeFleetUpdate::TYPE_PACKAGE,
            'theme_id'           => $site->theme_id,
            'from_release_id'    => null,
            'to_release_id'      => $site->theme?->current_release_id,
            'component_versions' => $componentVersions,
            'status'             => ThemeFleetUpdate::STATUS_RUNNING,
            'changelog_summary'  => $changelog,
            'dispatched_by'      => $userId,
            'started_at'         => now(),
        ]);

        if ($inst) {
            $inst->update([
                'package_version' => $package->version,
            ]);
        }

        $fleetUpdate->update([
            'status'       => ThemeFleetUpdate::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        $fleetUpdate->refresh();

        ActivityLogger::log('fleet.package_upgraded', "به‌روزرسانی پکیج هسته به نسخه {$package->version} روی سایت {$site->domain}", $site, [
            'update_id'       => $fleetUpdate->id,
            'package_version' => $package->version,
            'synced_files'    => $syncResult['synced_files'] ?? [],
        ]);

        return $fleetUpdate;
    }

    /**
     * Synchronize updated customer-site views, code and auth styling files
     * from canonical customer-site repository to the specific tenant site directory,
     * and execute artisan view:clear on the tenant site.
     *
     * @param Site $site
     * @return array{success: bool, target_dir: ?string, synced_files: list<string>, view_clear_output: ?string, error: ?string}
     */
    public function syncSiteFilesAndViews(Site $site): array
    {
        $sourceDir = config('services.customer_site_source_path')
            ?: (is_dir('/var/www/customer-site') ? '/var/www/customer-site' : base_path('../customer-site'));

        // Resolve target site directory
        $targetDir = $this->resolveSiteDirectory($site);

        if (!$targetDir || !is_dir($targetDir)) {
            Log::info("FleetUpdate: Target directory for site #{$site->id} not found ({$targetDir}); skipping local file copy.");
            return [
                'success'           => false,
                'target_dir'        => $targetDir,
                'synced_files'      => [],
                'view_clear_output' => null,
                'error'             => "پوشه فایل‌های سایت مشتری در سرور یافت نشد ({$targetDir}).",
            ];
        }

        if (!is_dir($sourceDir)) {
            Log::warning("FleetUpdate: Canonical customer-site source directory not found ({$sourceDir}).");
            return [
                'success'           => false,
                'target_dir'        => $targetDir,
                'synced_files'      => [],
                'view_clear_output' => null,
                'error'             => "پوشه مرجع customer-site یافت نشد ({$sourceDir}).",
            ];
        }

        // Relative files and patterns to sync
        $filesToSync = [
            'resources/views/layouts/auth-login.blade.php',
            'resources/views/themes/solar-industry/auth-styles.blade.php',
            'resources/views/themes/solar-industry/solar-page-home.json',
            'resources/views/themes/solar-industry/solar-page-solar_calculator.json',
            'app/Http/Controllers/Auth/LoginController.php',
            'app/Http/Controllers/Auth/RegisterController.php',
            'app/Http/Controllers/Front/PageBuilderController.php',
            'routes/web.php',
        ];

        $syncedFiles = [];

        foreach ($filesToSync as $relPath) {
            $srcFile = $sourceDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
            $dstFile = $targetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

            if (file_exists($srcFile)) {
                $dstDir = dirname($dstFile);
                if (!is_dir($dstDir)) {
                    @mkdir($dstDir, 0755, true);
                }
                if (@copy($srcFile, $dstFile)) {
                    $syncedFiles[] = $relPath;
                }
            }
        }

        // Run artisan view:clear on the target site
        $viewClearOutput = null;
        $artisanPath = $targetDir . DIRECTORY_SEPARATOR . 'artisan';
        if (file_exists($artisanPath)) {
            try {
                $process = \Illuminate\Support\Facades\Process::path($targetDir)->run('php artisan view:clear');
                $viewClearOutput = $process->output() ?: ($process->successful() ? 'Compiled views cleared!' : $process->errorOutput());
                Log::info("FleetUpdate: view:clear executed for site #{$site->id}", [
                    'exit_code' => $process->exitCode(),
                    'output'    => $viewClearOutput,
                ]);
            } catch (\Throwable $e) {
                Log::error("FleetUpdate: Failed to run view:clear for site #{$site->id}: " . $e->getMessage());
                $viewClearOutput = 'Exception: ' . $e->getMessage();
            }
        }

        return [
            'success'           => true,
            'target_dir'        => $targetDir,
            'synced_files'      => $syncedFiles,
            'view_clear_output' => $viewClearOutput,
            'error'             => null,
        ];
    }

    /**
     * Intelligently resolve the customer site directory on the server.
     */
    public function resolveSiteDirectory(Site $site): ?string
    {
        // 1. Explicit path in site features or attributes
        if (!empty($site->features['local_path']) && is_dir($site->features['local_path'])) {
            return $site->features['local_path'];
        }
        if (!empty($site->archive_path) && is_dir($site->archive_path)) {
            return $site->archive_path;
        }

        // 2. Direct unix user directory /var/www/cust_site_*
        $candidates = [];
        if (!empty($site->db_user)) {
            $candidates[] = '/var/www/' . $site->db_user;
        }
        if (!empty($site->db_name)) {
            $candidates[] = '/var/www/' . $site->db_name;
        }
        $candidates[] = '/var/www/cust_site_' . $site->id;

        // Extract host or clean domain (e.g. solar.safhenokhost.ir)
        if (!empty($site->domain)) {
            $host = parse_url($site->domain, PHP_URL_HOST) ?: $site->domain;
            $candidates[] = '/var/www/' . $host;
            $subdomain = explode('.', $host)[0] ?? '';
            if ($subdomain) {
                $candidates[] = '/var/www/' . $subdomain;
            }
        }

        foreach ($candidates as $cand) {
            if (is_dir($cand)) {
                return $cand;
            }
        }

        // Check glob for cust_site_* where .env matches site
        $wildcard = glob('/var/www/cust_site_*');
        if (!empty($wildcard)) {
            foreach ($wildcard as $dir) {
                if (!is_dir($dir)) continue;
                $envFile = $dir . '/.env';
                if (file_exists($envFile)) {
                    $envContent = @file_get_contents($envFile);
                    if ($envContent && (!empty($site->domain) && str_contains($envContent, $site->domain))) {
                        return $dir;
                    }
                    if ($envContent && (!empty($site->db_name) && str_contains($envContent, $site->db_name))) {
                        return $dir;
                    }
                }
            }
        }

        return $candidates[0] ?? null;
    }

    /**
     * Dispatch One-Click Rollback to restore a previous revision/snapshot.
     */
    public function dispatchRollback(ThemeFleetUpdate $targetUpdate, ?int $userId = null): ThemeFleetUpdate
    {
        $site = $targetUpdate->site;
        if (!$site) {
            throw new \InvalidArgumentException('سایت مرتبط با این تاریخچه به‌روزرسانی یافت نشد.');
        }

        $theme = $targetUpdate->theme;

        // Perform server-to-server rollback on customer-site
        $result = $this->gateway->rollback($site, [
            'revision_number' => $targetUpdate->revision_number,
            'theme_id'        => $theme?->slug,
        ]);

        $reversedVersions = [];
        if (!empty($targetUpdate->component_versions)) {
            foreach ($targetUpdate->component_versions as $comp => $diff) {
                $reversedVersions[$comp] = [
                    'from' => $diff['to'] ?? '1.0.0',
                    'to'   => $diff['from'] ?? '1.0.0',
                ];
            }
        }

        $changelog = "بازگشت به نسخه قبل (Rollback) از به‌روزرسانی #{$targetUpdate->id} - نسخه احیاشده: "
            . ($targetUpdate->fromRelease?->version ?? 'نسخه اولیه');

        $rollbackRecord = ThemeFleetUpdate::create([
            'site_id'                 => $site->id,
            'update_type'             => ThemeFleetUpdate::TYPE_ROLLBACK,
            'theme_id'                => $targetUpdate->theme_id,
            'from_release_id'         => $targetUpdate->to_release_id,
            'to_release_id'           => $targetUpdate->from_release_id ?? $targetUpdate->to_release_id,
            'component_versions'      => $reversedVersions,
            'status'                  => $result->success ? ThemeFleetUpdate::STATUS_COMPLETED : ThemeFleetUpdate::STATUS_FAILED,
            'is_rollback'             => true,
            'rollback_from_update_id' => $targetUpdate->id,
            'revision_number'         => $result->body['restored_revision_number'] ?? null,
            'reconciliation_summary'  => ['rollback_of_update_id' => $targetUpdate->id],
            'changelog_summary'       => $changelog,
            'failure_reason'          => $result->success ? null : $result->message,
            'dispatched_by'           => $userId,
            'started_at'              => now(),
            'completed_at'            => now(),
        ]);

        if ($result->success) {
            // Restore ThemeAssignment and ThemeLicense if from_release_id exists
            if ($targetUpdate->from_release_id) {
                ThemeAssignment::updateOrCreate(
                    [
                        'site_id'  => $site->id,
                        'theme_id' => $theme->id,
                    ],
                    [
                        'theme_release_id' => $targetUpdate->from_release_id,
                        'domain'           => $site->domain,
                        'source'           => ThemeAssignment::SOURCE_ADMIN,
                        'status'           => ThemeAssignment::STATUS_ASSIGNED,
                        'assigned_by'      => $userId,
                    ]
                );

                ThemeLicense::where('site_id', $site->id)
                    ->where('theme_id', $theme->id)
                    ->update([
                        'installed_release_id' => $targetUpdate->from_release_id,
                        'licensed_release_id'  => $targetUpdate->from_release_id,
                    ]);
            }

            // If target was package update, revert package_version
            if ($targetUpdate->update_type === ThemeFleetUpdate::TYPE_PACKAGE) {
                $fromVer = $targetUpdate->component_versions['customer_core']['from'] ?? null;
                if ($fromVer) {
                    CustomerInstallation::where('site_id', $site->id)->update([
                        'package_version' => $fromVer,
                    ]);
                }
            }

            ActivityLogger::log('fleet.rollback', "بازگشت به نسخه قبل روی سایت {$site->domain} با موفقیت انجام شد", $site, [
                'target_update_id'   => $targetUpdate->id,
                'rollback_update_id' => $rollbackRecord->id,
            ]);
        }

        return $rollbackRecord;
    }

    /**
     * Get recent audit history across the fleet.
     */
    public function getAuditHistory(?int $siteId = null, int $limit = 50)
    {
        $query = ThemeFleetUpdate::with(['site', 'theme', 'fromRelease', 'toRelease', 'dispatchedBy', 'rollbackFrom'])
            ->latest('id')
            ->limit($limit);

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        return $query->get();
    }
}
