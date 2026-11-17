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
use App\Support\IranianMobileNumber;
use App\UseCases\Customer\CreateCustomerUseCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    public function __construct(
        private readonly OnboardingDomainPolicy $domains,
        private readonly InstallationTargetGuard $guard,
        private readonly CreateCustomerUseCase $createCustomer,
    ) {}

    /** Everything the wizard renders, computed from platform state. */
    public function wizardOptions(): array
    {
        $kits = $this->kits();
        $themes = $this->eligibleThemes();

        $profiles = [];
        foreach (BusinessVerticals::LABELS as $vertical => $label) {
            $themeSlug = OnboardingProfiles::themeSlugFor($vertical);
            $theme = $themeSlug ? $themes->firstWhere('slug', $themeSlug) : null;
            $diagnosis = ($themeSlug !== null && $theme === null) ? $this->themeUnavailability($themeSlug) : null;
            $profiles[$vertical] = [
                'label'        => $label,
                'kit_ids'      => $this->kitIdsForVertical($vertical, $kits),
                'theme_id'     => $theme?->id,
                'theme_slug'   => $themeSlug,
                'theme_status' => $themeSlug === null ? null : ($theme ? 'available' : 'unavailable'),
                'theme_reason' => $diagnosis['reason'] ?? null,
                'theme_fix'    => $diagnosis['fix'] ?? [],
            ];
        }

        return [
            'root_domain'     => $this->domains->platformRootDomain(),
            'servers'         => CustomerRemoteServer::query()->orderBy('label')->get()->map(fn (CustomerRemoteServer $s) => [
                'id'          => $s->id,
                'label'       => $s->label,
                'host'        => $s->host,
                'type'        => $s->type,
                'implemented' => $this->guard->driverError($s) === null,
            ])->values()->all(),
            'themes'          => $themes->map(fn (Theme $t) => [
                'id'          => $t->id,
                'slug'        => $t->slug,
                'name'        => $t->name,
                'description' => $t->description,
                'category'    => $t->category,
                'image'       => $t->preview_image ?: $t->thumbnail,
                'pages'       => count($t->getPageList()),
            ])->values()->all(),
            'kits'            => $kits->map(fn (Module $m) => [
                'id'          => $m->id,
                'slug'        => $m->slug,
                'title'       => $m->title,
                'description' => $m->short_description ?? null,
                'vertical'    => $this->requiredVerticalForKit($m->slug),
            ])->values()->all(),
            'profiles'        => $profiles,
            'license_types'   => self::LICENSE_TYPES,
            'base_modules'    => self::BASE_MODULES,
            'backup_policies' => self::BACKUP_POLICIES,
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

        // Before anything is created: the domain must reach this server
        // (installation 50 only failed at ISSUE_SSL, after 18 stages).
        $dnsError = $this->guard->dnsPreflightError($domain, $server, (bool) ($data['ssl_enabled'] ?? true));
        if ($dnsError !== null) {
            throw ValidationException::withMessages(['domain' => $dnsError]);
        }

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

        $existing = Customer::withTrashed()->where('mobile', $mobile)->first();
        if ($existing?->trashed()) {
            throw ValidationException::withMessages(['mobile' => "این موبایل متعلق به مشتری حذف‌شدهٔ «{$existing->name}» است؛ ابتدا او را از فهرست مشتریان بازیابی کنید."]);
        }
        if ($existing === null && trim((string) ($data['customer_name'] ?? '')) === '') {
            throw ValidationException::withMessages(['customer_name' => 'نام مشتری جدید الزامی است.']);
        }

        $installation = DB::transaction(function () use ($data, $mobile, $domain, $server, $theme, $vertical, $kitIds, $actor) {
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
                'theme_id'          => $theme?->id,
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
        });

        ActivityLogger::log('onboarding.started', "راه‌اندازی سایت {$domain}", $installation, [
            'installation_id'   => $installation->id,
            'customer_id'       => $installation->request_payload['customer_id'],
            'theme_id'          => $theme?->id,
            'business_vertical' => $vertical,
            'kit_module_ids'    => $kitIds,
        ]);

        CustomerInstallationPipelineJob::dispatch($installation->id)
            ->onConnection('customer-install')
            ->onQueue('customer-install');

        return $installation;
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

    /** @return Collection<int, Module> active license-only modules (kits) */
    public function kits(): Collection
    {
        return Module::assignableToSite()->where('is_active', true)->orderBy('title')->get()
            ->filter(fn (Module $m) => $m->isLicenseOnly())
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
