<?php

namespace App\Services\Onboarding;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Module;
use App\Models\Theme;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CustomerInstallation\InstallationTargetGuard;
use App\Services\Infrastructure\ServerCapacityService;
use App\Support\IranianMobileNumber;
use App\UseCases\Customer\CreateCustomerUseCase;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessCatalog;
use SaaSPlatform\ThemeRuntime\Access\BusinessVerticals;

/**
 * ADR-088 — the single entry point that onboards a customer site end to end.
 *
 * One submit resolves the customer by mobile (one mobile owns any number of
 * sites), validates the domain, server, theme, vertical and kits, and starts
 * the canonical CustomerInstallationOrchestrator pipeline. The pipeline then
 * provisions the isolated install (web root, nginx vhost on the detected
 * PHP-FPM socket, DNS, SSL, www-data permissions, database, license), applies
 * the vertical and kits (APPLY_SITE_ENTITLEMENTS), assigns the theme
 * (INSTALL_THEME), clears caches (CLEAR_APPLICATION_CACHE), and after
 * CUSTOMER_READY pushes the theme's pages onto the live site
 * (SiteTemplateBinder). No step needs an administrator afterwards.
 *
 * This service never provisions anything itself: it only decides and
 * dispatches, so the pipeline remains the one provisioning authority.
 */
class SiteOnboardingService
{
    public const LICENSE_TYPES = [
        'basic'        => 'پایه',
        'professional' => 'حرفه‌ای',
        'enterprise'   => 'سازمانی',
    ];

    /** customer-site feature flags written to its .env by GENERATE_ENVIRONMENT. */
    public const BASE_MODULES = [
        'forms'     => 'فرم‌ساز',
        'tickets'   => 'تیکت پشتیبانی',
        'education' => 'مرکز آموزش',
    ];

    public const BACKUP_POLICIES = [
        'daily'  => 'روزانه',
        'weekly' => 'هفتگی',
        'none'   => 'بدون پشتیبان‌گیری خودکار',
    ];

    /** Standard Hardware Resource Presets */
    public const RESOURCE_PLANS = [
        'basic' => [
            'id'           => 'basic',
            'label'        => 'پلن پایه (استارتاپی / شخصی)',
            'description'  => 'مناسب سایت‌های شخصی، وبلاگ‌ها و کسب‌وکارهای نوپا',
            'ram_mb'       => 1024,
            'disk_gb'      => 5.0,
            'soft_disk_gb' => 3.5,
            'cpu_cores'    => 1.0,
            'bandwidth_gb' => 10.0,
        ],
        'pro' => [
            'id'           => 'pro',
            'label'        => 'پلن حرفه‌ای (شرکتی / خدماتی)',
            'description'  => 'مناسب شرکت‌ها، معرفی خدمات، کاتالوگ و سایت‌های خدماتی',
            'ram_mb'       => 2048,
            'disk_gb'      => 20.0,
            'soft_disk_gb' => 14.0,
            'cpu_cores'    => 2.0,
            'bandwidth_gb' => 50.0,
        ],
        'enterprise' => [
            'id'           => 'enterprise',
            'label'        => 'پلن سازمانی (فروشگاهی / آموزشی پربازدید)',
            'description'  => 'مناسب فروشگاه‌های آنلاین فعال، مراکز آموزش و پورتال‌های بزرگ',
            'ram_mb'       => 4096,
            'disk_gb'      => 50.0,
            'soft_disk_gb' => 35.0,
            'cpu_cores'    => 4.0,
            'bandwidth_gb' => 200.0,
        ],
    ];

    /** Functional categorisation for wizard module selection */
    public const MODULE_CATEGORIES = [
        'store' => [
            'id'    => 'store',
            'label' => 'فروشگاهی و پرداخت',
            'slugs' => ['shop', 'bank_gateway', 'coupon', 'invoice', 'installments', 'referral', 'subscription'],
        ],
        'content' => [
            'id'    => 'content',
            'label' => 'محتوا، وبلاگ و آموزش',
            'slugs' => ['blog', 'courses', 'media', 'homepage_videos', 'translations', 'reviews'],
        ],
        'communication' => [
            'id'    => 'communication',
            'label' => 'ارتباطات و اطلاع‌رسانی',
            'slugs' => ['sms', 'whatsapp', 'notifications', 'support', 'social_links', 'business_card'],
        ],
        'tools' => [
            'id'    => 'tools',
            'label' => 'سئو، ابزارها و امنیت',
            'slugs' => ['forms', 'seo-advanced', 'advanced_search', 'smart_image', 'access_lists', 'crm-suite', 'captcha'],
        ],
        'specialized' => [
            'id'    => 'specialized',
            'label' => 'کیت‌های اختصاصی صنعت',
            'slugs' => ['solar_industry_kit', 'solar_calculator'],
        ],
    ];

    /** Business Vertical to default recommended module slugs */
    public const VERTICAL_DEFAULT_MODULES = [
        'solar_energy' => ['solar_industry_kit', 'solar_calculator', 'forms', 'blog', 'reviews', 'sms', 'social_links'],
        'retail'       => ['shop', 'bank_gateway', 'coupon', 'invoice', 'reviews', 'sms', 'installments', 'forms'],
        'education'    => ['courses', 'forms', 'blog', 'reviews', 'support', 'sms'],
        'corporate'    => ['blog', 'forms', 'business_card', 'social_links', 'sms', 'support'],
        'healthcare'   => ['forms', 'blog', 'social_links', 'sms', 'support', 'reviews'],
        'hospitality'  => ['forms', 'blog', 'social_links', 'reviews', 'coupon', 'sms'],
        'construction' => ['forms', 'blog', 'business_card', 'social_links', 'reviews'],
        'general'      => ['forms', 'blog', 'social_links', 'sms'],
    ];

    private readonly ServerCapacityService $capacityService;

    public function __construct(
        private readonly OnboardingDomainPolicy $domains,
        private readonly InstallationTargetGuard $guard,
        private readonly CreateCustomerUseCase $createCustomer,
        ?ServerCapacityService $capacityService = null,
    ) {
        $this->capacityService = $capacityService ?? app(ServerCapacityService::class);
    }

    /** Everything the wizard renders, computed from platform state. */
    public function wizardOptions(): array
    {
        $kits = $this->kits();
        $themes = $this->eligibleThemes();
        $allAssignable = Module::assignableToSite()->where('is_active', true)->orderBy('title')->get();

        // Categorize modules for rich interactive wizard
        $categoryMapping = [];
        foreach (self::MODULE_CATEGORIES as $catKey => $catInfo) {
            foreach ($catInfo['slugs'] as $slug) {
                $categoryMapping[$slug] = $catKey;
            }
        }

        $categorizedModules = $allAssignable->map(fn (Module $m) => [
            'id'          => $m->id,
            'slug'        => $m->slug,
            'title'       => $m->title,
            'description' => $m->description ?: $m->short_description,
            'category'    => $categoryMapping[$m->slug] ?? 'tools',
            'is_kit'      => $m->isSiteLicensableKit(),
            'is_paid'     => (bool) ($m->price_irr > 0),
            'price_irr'   => $m->price_irr,
            'selectable'  => self::isWizardSelectable($m),
            'unselectable_reason' => self::isWizardSelectable($m) ? null : self::unselectableReason($m),
        ])->values()->all();

        // Map business vertical to default module IDs (only modules the
        // wizard can actually activate; kits come from the kits section).
        $selectableAssignable = $allAssignable->filter(fn (Module $m) => self::isWizardSelectable($m));
        $verticalDefaultModules = [];
        foreach (self::VERTICAL_DEFAULT_MODULES as $vertKey => $slugs) {
            $verticalDefaultModules[$vertKey] = $selectableAssignable->whereIn('slug', $slugs)->pluck('id')->values()->all();
        }

        $profiles = [];
        foreach (BusinessVerticals::LABELS as $vertical => $label) {
            $themeSlug = OnboardingProfiles::themeSlugFor($vertical);
            $theme = $themeSlug ? $themes->firstWhere('slug', $themeSlug) : null;
            $diagnosis = ($themeSlug !== null && $theme === null) ? $this->themeUnavailability($themeSlug) : null;
            $profiles[$vertical] = [
                'label'               => $label,
                'kit_ids'             => $this->kitIdsForVertical($vertical, $kits),
                'default_module_ids'  => $verticalDefaultModules[$vertical] ?? [],
                'theme_id'            => $theme?->id,
                'theme_slug'          => $themeSlug,
                'theme_status'        => $themeSlug === null ? null : ($theme ? 'available' : 'unavailable'),
                'theme_reason'        => $diagnosis['reason'] ?? null,
                'theme_fix'           => $diagnosis['fix'] ?? [],
            ];
        }

        return [
            'root_domain'             => $this->domains->platformRootDomain(),
            'servers'                 => CustomerRemoteServer::query()->orderBy('label')->get()->map(fn (CustomerRemoteServer $s) => [
                'id'          => $s->id,
                'label'       => $s->label,
                'host'        => $s->host,
                'type'        => $s->type,
                'implemented' => $this->guard->driverError($s) === null,
            ])->values()->all(),
            'themes'                  => $themes->map(fn (Theme $t) => [
                'id'          => $t->id,
                'slug'        => $t->slug,
                'name'        => $t->name,
                'description' => $t->description,
                'category'    => $t->category,
                'image'       => $t->preview_image ?: $t->thumbnail,
                'pages'       => count($t->getPageList()),
            ])->values()->all(),
            'modules'                 => $categorizedModules,
            'module_categories'       => self::MODULE_CATEGORIES,
            'vertical_modules'        => $verticalDefaultModules,
            'resource_plans'          => self::RESOURCE_PLANS,
            'default_soft_disk_ratio' => ServerCapacityService::DEFAULT_SOFT_DISK_RATIO,
            'kits'                    => $kits->map(fn (Module $m) => [
                'id'          => $m->id,
                'slug'        => $m->slug,
                'title'       => $m->title,
                'description' => $m->short_description ?? null,
                'vertical'    => $this->requiredVerticalForKit($m->slug),
            ])->values()->all(),
            'profiles'                => $profiles,
            'license_types'           => self::LICENSE_TYPES,
            'base_modules'            => self::BASE_MODULES,
            'backup_policies'         => self::BACKUP_POLICIES,
        ];
    }

    /**
     * Live lookup for the customer step: the canonical mobile and, when the
     * mobile already has an owner, that customer and every site they manage.
     *
     * @return array{valid: bool, mobile: ?string, customer: ?array}
     */
    public function lookupCustomer(string $mobile): array
    {
        $normalized = IranianMobileNumber::normalize($mobile);
        if ($normalized === null) {
            return ['valid' => false, 'mobile' => null, 'customer' => null];
        }

        $customer = Customer::withTrashed()->with('sites')->where('mobile', $normalized)->first();

        return [
            'valid'    => true,
            'mobile'   => $normalized,
            'customer' => $customer ? [
                'trashed'      => $customer->trashed(),
                'id'           => $customer->id,
                'name'         => $customer->name,
                'company_name' => $customer->company_name,
                'sites'        => $customer->sites->map(fn ($site) => [
                    'id'        => $site->id,
                    'title'     => $site->title,
                    'domain'    => $site->domain,
                    'is_active' => (bool) $site->is_active,
                ])->values()->all(),
            ] : null,
        ];
    }

    /**
     * Validate the wizard submission authoritatively and start the pipeline.
     * $data is the FormRequest's validated array (shape: StartOnboardingRequest).
     *
     * @throws ValidationException
     */
    public function start(array $data, User $actor): CustomerInstallation
    {
        $mobile = IranianMobileNumber::normalize($data['mobile'] ?? '');
        if ($mobile === null) {
            throw ValidationException::withMessages(['mobile' => 'شماره موبایل معتبر نیست.']);
        }

        // DNS is checked below against the chosen server (dnsPreflightError).
        $domainCheck = $this->domains->check($data['domain_mode'], (string) $data['domain'], withDns: false);
        if (!$domainCheck['ok']) {
            throw ValidationException::withMessages(['domain' => $domainCheck['error']]);
        }
        $domain = $domainCheck['domain'];

        $server = CustomerRemoteServer::query()->findOrFail($data['remote_server_id']);
        if (($driverError = $this->guard->driverError($server)) !== null) {
            throw ValidationException::withMessages(['remote_server_id' => $driverError]);
        }

        if (($packageError = $this->guard->packageError()) !== null) {
            throw ValidationException::withMessages(['package' => $packageError]);
        }

        // Before anything is created: the domain must reach this server
        // (installation 50 only failed at ISSUE_SSL, after 18 stages).
        $dnsError = $this->guard->dnsPreflightError($domain, $server, (bool) ($data['ssl_enabled'] ?? true));
        if ($dnsError !== null) {
            throw ValidationException::withMessages(['domain' => $dnsError]);
        }

        // 1. Hardware Resource Validation against Live Server Capacity
        $ramMb = (int) ($data['ram_mb'] ?? 1024);
        $diskGb = (float) ($data['disk_gb'] ?? 5.0);
        $softDiskGb = isset($data['soft_disk_gb']) ? (float) $data['soft_disk_gb'] : round($diskGb * ServerCapacityService::DEFAULT_SOFT_DISK_RATIO, 2);
        $cpuCores = (float) ($data['cpu_cores'] ?? 1.0);
        $bandwidthGb = (float) ($data['bandwidth_gb'] ?? 10.0);

        // The authoritative capacity check runs below, under a per-server lock
        // together with the INSERT (see createReservedInstallation()).

        $theme = null;
        if (!empty($data['theme_id'])) {
            $theme = $this->eligibleThemes()->firstWhere('id', (int) $data['theme_id']);
            if ($theme === null) {
                throw ValidationException::withMessages(['theme_id' => 'این قالب منتشرشده و واجد شرایط تجاری نیست و قابل اختصاص به سایت نیست.']);
            }
        }

        $vertical = ($data['business_vertical'] ?? null) ?: null;
        if ($vertical !== null && !BusinessVerticals::isKnown($vertical)) {
            throw ValidationException::withMessages(['business_vertical' => 'حوزهٔ کسب‌وکار نامعتبر است.']);
        }

        $kitIds = $this->validatedKitIds((array) ($data['kit_module_ids'] ?? []), $vertical);
        $activeModuleIds = $this->validatedActiveModuleIds((array) ($data['active_module_ids'] ?? []));

        $existing = Customer::withTrashed()->where('mobile', $mobile)->first();
        if ($existing?->trashed()) {
            throw ValidationException::withMessages(['mobile' => "این موبایل متعلق به مشتری حذف‌شدهٔ «{$existing->name}» است؛ ابتدا او را از فهرست مشتریان بازیابی کنید."]);
        }
        if ($existing === null && trim((string) ($data['customer_name'] ?? '')) === '') {
            throw ValidationException::withMessages(['customer_name' => 'نام مشتری جدید الزامی است.']);
        }

        $installation = $this->createReservedInstallation($server, $ramMb, $diskGb, $softDiskGb, $cpuCores, fn () => DB::transaction(function () use ($data, $mobile, $domain, $server, $theme, $vertical, $kitIds, $activeModuleIds, $ramMb, $diskGb, $softDiskGb, $cpuCores, $bandwidthGb, $actor) {
            $customer = Customer::query()->where('mobile', $mobile)->lockForUpdate()->first()
                ?? $this->createCustomer->execute([
                    'name'         => trim((string) $data['customer_name']),
                    'mobile'       => $mobile,
                    'company_name' => ($data['company_name'] ?? null) ?: null,
                ]);

            $payload = [
                'company'           => trim((string) $data['site_title']),
                'owner_name'        => $customer->name,
                'email'             => '',
                'phone'             => $mobile,
                'language'          => 'fa',
                'timezone'          => 'Asia/Tehran',
                'currency'          => 'IRR',
                'domain'            => $domain,
                'license_type'      => $data['license_type'],
                'theme_slug'        => $theme?->slug ?? '',
                'modules'           => array_values(array_intersect((array) ($data['base_modules'] ?? []), array_keys(self::BASE_MODULES))),
                'backup_policy'     => $data['backup_policy'] ?? 'daily',
                'smtp'              => [],
                'ssl_enabled'       => (bool) ($data['ssl_enabled'] ?? true),
                'remote_server_id'  => $server->id,
                'install_mode'      => 'MODE_FRESH',
                'customer_id'       => $customer->id,
                'business_vertical' => $vertical,
                'kit_module_ids'    => $kitIds,
                'active_module_ids' => $activeModuleIds,
                'theme_id'          => $theme?->id,
                'web_root_path'     => $data['web_root_path'] ?? null,
                'db_name'           => $data['db_name'] ?? null,
                'db_user'           => $data['db_user'] ?? null,
                'db_host'           => $data['db_host'] ?? '127.0.0.1',
                'db_password'       => $data['db_password'] ?? null,
                'resource_plan'     => $data['resource_plan'] ?? 'basic',
                'ram_mb'            => $ramMb,
                'disk_gb'           => $diskGb,
                'soft_disk_gb'      => $softDiskGb,
                'cpu_cores'         => $cpuCores,
                'bandwidth_gb'      => $bandwidthGb,
            ];

            return CustomerInstallation::create([
                'remote_server_id'  => $server->id,
                'installer_user_id' => $actor->id,
                'install_mode'      => 'MODE_FRESH',
                'status'            => 'pending',
                'customer_company'  => $payload['company'],
                'customer_email'    => '',
                'customer_domain'   => $domain,
                'request_payload'   => $payload,
            ]);
        }));

        ActivityLogger::log('onboarding.started', "راه‌اندازی سایت {$domain}", $installation, [
            'installation_id'   => $installation->id,
            'customer_id'       => $installation->request_payload['customer_id'],
            'theme_id'          => $theme?->id,
            'business_vertical' => $vertical,
            'kit_module_ids'    => $kitIds,
            'active_module_ids' => $activeModuleIds,
            'resource_plan'     => $data['resource_plan'] ?? 'basic',
        ]);

        CustomerInstallationPipelineJob::dispatch($installation->id)
            ->onConnection('customer-install')
            ->onQueue('customer-install');

        return $installation;
    }

    /**
     * Validate capacity and create the installation atomically per server.
     * The new pending installation's request_payload IS the reservation:
     * ServerCapacityService counts pending/running installations, so a
     * second submission waiting on this lock sees the first one's quota.
     *
     * @param  \Closure(): CustomerInstallation  $create
     *
     * @throws ValidationException
     */
    private function createReservedInstallation(CustomerRemoteServer $server, int $ramMb, float $diskGb, float $softDiskGb, float $cpuCores, \Closure $create): CustomerInstallation
    {
        try {
            return Cache::lock("onboarding:server-capacity:{$server->id}", 60)->block(15, function () use ($server, $ramMb, $diskGb, $softDiskGb, $cpuCores, $create) {
                $capacityCheck = $this->capacityService->validateAllocation($server, $ramMb, $diskGb, $softDiskGb, $cpuCores, fresh: true);
                if (! $capacityCheck['valid']) {
                    throw ValidationException::withMessages(['ram_mb' => implode(' | ', $capacityCheck['errors'])]);
                }

                return $create();
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['remote_server_id' => 'نصب دیگری هم‌زمان روی این سرور در حال ثبت است؛ چند ثانیه بعد دوباره تلاش کنید.']);
        }
    }

    /**
     * Compute suggested webroot, db name, db user, and strong password for a server + domain.
     *
     * @return array{web_root_path: string, db_name: string, db_user: string, db_host: string, db_password: string}
     */
    public function suggestInfrastructure(CustomerRemoteServer $server, string $domain, string $siteTitle = ''): array
    {
        $domainParts = explode('.', $domain);
        $sub = strtolower($domainParts[0] ?? 'site');
        $sub = preg_replace('/[^a-z0-9]/', '', $sub);
        if (strlen($sub) < 3) {
            $sub = 'site' . strtolower(Str::random(3));
        }

        $rand = strtolower(Str::random(4));
        $prefix = preg_replace('/[^a-z0-9]/', '', strtolower($server->username ?? ''));

        $isShared = in_array(strtolower($server->type ?? ''), ['cpanel', 'directadmin'], true);

        if ($isShared && !empty($prefix)) {
            $dbName  = substr("{$prefix}_{$sub}_{$rand}", 0, 32);
            $dbUser  = substr("{$prefix}_{$sub}", 0, 32);
            $webRoot = "/home/{$prefix}/public_html/{$domain}";
        } else {
            $dbName  = "cust_{$sub}_{$rand}";
            $dbUser  = "u_{$sub}_{$rand}";
            $webRoot = "/var/www/{$domain}";
        }

        // 32-char password satisfying MySQL validate_password (digits, letters, ._-)
        $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789._-';
        $dbPass = '';
        $len = strlen($charset);
        for ($i = 0; $i < 32; $i++) {
            $dbPass .= $charset[random_int(0, $len - 1)];
        }

        return [
            'web_root_path' => $webRoot,
            'db_name'       => $dbName,
            'db_user'       => $dbUser,
            'db_host'       => '127.0.0.1',
            'db_password'   => $dbPass,
        ];
    }

    /** @return Collection<int, Theme> published, platform-owned, commercially eligible */
    public function eligibleThemes(): Collection
    {
        return Theme::published()->whereNull('site_id')
            ->with('currentRelease.qualityReports')
            ->orderBy('name')->get()
            ->filter(fn (Theme $theme) => $theme->isCommerciallyEligible())
            ->values();
    }

    /**
     * Why a profile's canonical theme is not offered, and the commands that
     * fix it. The admin sees this instead of a bare "unavailable". Reasons are
     * `not_installed` (no master theme row: production starts with an empty
     * catalog after the clean-slate purge), `not_published`, or one of
     * Theme::commercialIneligibilityReasons().
     *
     * @return array{reason: string, fix: list<string>}
     */
    public function themeUnavailability(string $slug): array
    {
        $theme = Theme::whereNull('site_id')->where('slug', $slug)->first();
        $install = "php artisan theme-catalog:rebuild --slug={$slug}";
        $release = "php artisan theme-release:backfill --slug={$slug}";

        if ($theme === null) {
            return ['reason' => 'not_installed', 'fix' => [$install, $release]];
        }
        if ($theme->status !== Theme::STATUS_PUBLISHED) {
            return ['reason' => 'not_published', 'fix' => [$install, $release]];
        }

        $reason = $theme->commercialIneligibilityReasons()[0] ?? 'unknown';
        $fix = match ($reason) {
            'no_current_release' => $theme->releases()->exists() ? [] : [$release],
            'no_passing_quality_report' => ['php artisan theme-release:link-quality-reports'],
            default => [],
        };

        return ['reason' => $reason, 'fix' => $fix];
    }

    /**
     * Active kits a site can actually be licensed for (Module::isSiteLicensableKit:
     * paid + license-only). Core modules with license_required used to be
     * listed too (56 «کیت‌های لایسنس» on production instead of 3), and
     * APPLY_SITE_ENTITLEMENTS then failed on the first of them.
     *
     * @return Collection<int, Module>
     */
    public function kits(): Collection
    {
        return Module::assignableToSite()->where('is_active', true)->orderBy('title')->get()
            ->filter(fn (Module $m) => $m->isSiteLicensableKit())
            ->values();
    }

    /** The vertical an entitlement key's asset family requires, if any. */
    public function requiredVerticalForKit(string $slug): ?string
    {
        foreach (AssetAccessCatalog::families() as $family) {
            if (in_array($slug, (array) ($family['entitlement_keys'] ?? []), true) && !empty($family['required_vertical'])) {
                return (string) $family['required_vertical'];
            }
        }

        return null;
    }

    /** @return list<int> */
    private function kitIdsForVertical(string $vertical, Collection $kits): array
    {
        $slugs = [];
        foreach (AssetAccessCatalog::families() as $family) {
            if (($family['required_vertical'] ?? null) === $vertical) {
                array_push($slugs, ...(array) ($family['entitlement_keys'] ?? []));
            }
        }

        return $kits->whereIn('slug', $slugs)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * A general module the wizard may activate at onboarding: assignable to a
     * site, active, and free to assign. Paid-marketplace modules are sold in
     * the tenant store, license-only modules need a license first, and kits
     * are licensed from the kits section — ModuleAssignmentService::
     * enableForSite() refuses all three, so offering them only produced a
     * failed or silently skipped activation.
     */
    public static function isWizardSelectable(Module $module): bool
    {
        return (bool) $module->is_active
            && ! $module->isSiteLicensableKit()
            && $module->isFreeAssignable();
    }

    private static function unselectableReason(Module $module): string
    {
        return match (true) {
            $module->isSiteLicensableKit() => 'کیت لایسنسی است؛ از بخش «کیت‌ها» انتخاب کنید.',
            $module->isPaidMarketplace()   => 'ماژول پولی است و از فروشگاه ماژول خریداری می‌شود.',
            $module->isLicenseOnly()       => 'نیاز به لایسنس دارد؛ پس از صدور لایسنس از صفحهٔ ماژول‌های سایت فعال کنید.',
            default                        => 'در حال حاضر قابل فعال‌سازی نیست.',
        };
    }

    /** @return list<int> */
    private function validatedActiveModuleIds(array $requested): array
    {
        $requested = array_values(array_unique(array_map('intval', $requested)));
        if ($requested === []) {
            return [];
        }

        $modules = Module::assignableToSite()->whereIn('id', $requested)->get()->keyBy('id');
        foreach ($requested as $id) {
            $module = $modules->get($id);
            if ($module === null) {
                throw ValidationException::withMessages(['active_module_ids' => 'یکی از ماژول‌های انتخاب‌شده وجود ندارد یا قابل اختصاص به سایت نیست.']);
            }
            if (! self::isWizardSelectable($module)) {
                throw ValidationException::withMessages(['active_module_ids' => "ماژول «{$module->title}» در ویزارد قابل فعال‌سازی نیست: " . self::unselectableReason($module)]);
            }
        }

        return $requested;
    }

    /** @return list<int> */
    private function validatedKitIds(array $requested, ?string $vertical): array
    {
        $requested = array_values(array_unique(array_map('intval', $requested)));
        if ($requested === []) {
            return [];
        }

        $kits = $this->kits()->keyBy('id');
        foreach ($requested as $id) {
            $kit = $kits->get($id);
            if ($kit === null) {
                throw ValidationException::withMessages(['kit_module_ids' => 'یکی از کیت‌های انتخاب‌شده کیت لایسنس فعال نیست.']);
            }
            $needs = $this->requiredVerticalForKit($kit->slug);
            if ($needs !== null && $needs !== $vertical) {
                throw ValidationException::withMessages([
                    'kit_module_ids' => "کیت «{$kit->title}» فقط برای حوزهٔ «" . BusinessVerticals::label($needs) . '» کار می‌کند؛ همان حوزه را انتخاب کنید.',
                ]);
            }
        }

        return $requested;
    }
}
