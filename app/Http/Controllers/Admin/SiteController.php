<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\Jalali;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use App\Models\Site;
use App\Models\Customer;
use App\Models\Module;
use App\Models\SiteSetting;
use App\Models\PaymentGateway;
use App\Helpers\CustomerMenu;
use App\Helpers\PlatformModules;
use App\Services\WelcomeNotificationService;
use App\Services\Sms\SmsGatewayResolver;
use App\Services\ExpiryReminderService;
use App\Helpers\PlatformSettings;
use App\Models\PlatformSiteNotification;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\ModuleAssignmentService;
use App\Services\PublishedThemeAssignmentService;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class SiteController extends Controller
{
    public function __construct(
        private readonly CredentialVault $credentialVault,
        private readonly ModuleAssignmentService $moduleAssignmentService,
        private readonly PublishedThemeAssignmentService $themeAssignments,
    ) {}

    /**
     * لیست سایت‌ها
     */
    public function index(Request $request)
    {
        $query = Site::with(['customer', 'modules']);
        $customerId = $request->customer_id;
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        $moduleSlug = $request->module;
        if ($moduleSlug) {
            $module = Module::where('slug', $moduleSlug)->first();
            if ($module) {
                $query->whereHas('modules', function ($q) use ($module) {
                    $q->where('modules.id', $module->id)->where('site_modules.is_active', true);
                });
            }
        }
        $sites = $query->latest()->get();
        $filterCustomer = $customerId ? Customer::find($customerId) : null;
        $filterModule = $moduleSlug ? Module::where('slug', $moduleSlug)->first() : null;
        return view('admin.sites.index', compact('sites', 'filterCustomer', 'filterModule'));
    }

    /**
     * فرم ایجاد سایت جدید
     */
    public function create()
    {
        $customers = Customer::all();
        // Phase 17-D — Part 5: was Module::where('is_active', true)->get(),
        // which included TYPE_INTERNAL (platform-only) modules in this
        // checklist. modulesForManualAssign() is the same assignable-only
        // filter SiteModuleController's dedicated page already used.
        $modules = $this->moduleAssignmentService->modulesForManualAssign();
        $paymentGateways = Schema::hasTable('payment_gateways') ? PaymentGateway::where('is_active', true)->orderBy('sort_order')->get() : collect();
        return view('admin.sites.create', compact('customers', 'modules', 'paymentGateways'));
    }

    /**
     * Wizard چند مرحله‌ای برای ایجاد سایت جدید
     */
    public function wizard()
    {
        $customers = Customer::orderBy('name')->get(['id', 'name', 'mobile']);
        $modules   = $this->moduleAssignmentService->modulesForManualAssign();
        $modulesByCategory = $modules->groupBy('category');
        return view('admin.sites.wizard', compact('customers', 'modules', 'modulesByCategory'));
    }

    /**
     * ذخیره سایت جدید
     */
    public function store(Request $request)
    {
        $rules = [
            'customer_id' => 'required|exists:customers,id',
            'title'       => 'required|string|max:255',
            'domain'      => 'nullable|string|max:255|unique:sites,domain',
        ];
        if (Schema::hasTable('payment_gateways') && Schema::hasColumn('sites', 'payment_gateway_id')) {
            $rules['payment_gateway_id'] = 'nullable|exists:payment_gateways,id';
        }
        $request->validate($rules);

        $data = $request->only([
            'customer_id',
            'title',
            'domain',
            'hosting_company',
            'db_name',
            'db_user',
            'db_host',
            'support_expires_at',
            'host_expires_at',
            'domain_expires_at',
            'support_starts_at',
            'support_level',
            'is_active',
        ]);
        $data['support_expires_at'] = $this->normalizeDate($request->support_expires_at);
        $data['host_expires_at'] = $this->normalizeDate($request->host_expires_at);
        $data['domain_expires_at'] = $this->normalizeDate($request->domain_expires_at);
        $data['support_starts_at'] = $this->normalizeDate($request->support_starts_at);
        $data['support_level'] = in_array($request->support_level ?? '', ['basic', 'pro', 'enterprise'], true)
            ? $request->support_level
            : 'basic';
        $data['is_active'] = $request->boolean('is_active');
        if (Schema::hasColumn('sites', 'payment_gateway_id')) {
            $data['payment_gateway_id'] = $request->filled('payment_gateway_id') ? $request->payment_gateway_id : null;
        }
        if (Schema::hasColumn('sites', 'customer_menu_keys')) {
            $data['customer_menu_keys'] = $request->input('customer_menu_keys', array_keys(CustomerMenu::MENU_KEYS));
        }

        // Phase 13-O12a — db_password is never written to the plaintext
        // column anymore; it's only ever encrypted into db_password_encrypted
        // (Site::getDecryptedDbPassword() reads it back out).
        $dbPasswordInput = $request->input('db_password');

        // Auto-fill customer-site DB credentials from platform env if not provided
        if (empty($data['db_host']) && config('services.customer_db.host')) {
            $data['db_host']  = config('services.customer_db.host');
            $data['db_user']  = $data['db_user'] ?: config('services.customer_db.user');
            $dbPasswordInput  = $dbPasswordInput ?: config('services.customer_db.password');
            $data['db_name']  = $data['db_name'] ?: config('services.customer_db.name');
        }

        if (!empty($dbPasswordInput)) {
            $data['db_password_encrypted'] = $this->credentialVault->encrypt($dbPasswordInput);
        }

        $site = Site::create($data);

        // Phase 17-D — Part 5: module selection at creation time is
        // intentionally NOT processed here anymore. A brand-new site has no
        // completed CustomerInstallation yet, so ModuleDeploymentRequestService
        // can never resolve a remote server to deploy to at this point —
        // calling enableForSite() here would just fail every time. The
        // previous code used $site->modules()->sync($request->modules)
        // directly (a plain array of IDs, unfiltered — including
        // TYPE_INTERNAL modules, bypassing every eligibility/deployment
        // guard). Module assignment now happens exclusively via the
        // dedicated "ماژول‌های این سایت" page (SiteModuleController), after
        // the site's initial deployment has actually completed.

        // ارسال خودکار اعلان‌های خوش‌آمدگویی (در صورت فعال بودن ماژول اعلان)
        $welcomeCount = 0;
        if (PlatformModules::isEnabled('notifications') && Schema::hasTable('platform_notification_templates')) {
            $welcomeCount = app(WelcomeNotificationService::class)->sendToSite($site);
        }

        ActivityLogger::log('site.created', 'ایجاد سایت: ' . $site->title, $site);

        // GAP-M09 — Send welcome SMS to customer after site creation
        $this->sendSiteCreatedSms($site);

        $message = 'سایت با موفقیت ایجاد شد';
        if ($welcomeCount > 0) {
            $message .= " و {$welcomeCount} اعلان خوش‌آمدگویی ارسال شد.";
        }

        // GAP-L09: Redirect to edit page with checklist so admin knows next steps
        return redirect()->route('admin.sites.edit', $site)
            ->with('success', $message)
            ->with('just_created', true);
    }

    /**
     * نمایش سایت — ریدایرکت به ویرایش
     */
    public function show(Site $site)
    {
        return redirect()->route('admin.sites.edit', $site);
    }

    /**
     * فرم ویرایش سایت
     */
    public function edit(Site $site)
    {
        $customers = Customer::all();
        // Phase 17-D — Part 5: same assignable-only filter as create()/wizard().
        $modules = $this->moduleAssignmentService->modulesForManualAssign();
        $paymentGateways = Schema::hasTable('payment_gateways') ? PaymentGateway::where('is_active', true)->orderBy('sort_order')->get() : collect();
        $site->load('modules');
        $customerMenuKeys = Schema::hasColumn('sites', 'customer_menu_keys') ? ($site->customer_menu_keys ?? array_keys(CustomerMenu::MENU_KEYS)) : array_keys(CustomerMenu::MENU_KEYS);
        $siteDesign = Schema::hasTable('site_settings') ? \App\Models\SiteSetting::allForSite($site->id) : [];
        $themes = Schema::hasTable('themes') && Schema::hasColumn('sites', 'theme_id')
            ? Theme::published()->with('currentRelease.qualityReports')->orderBy('name')->get()
                ->filter(fn (Theme $theme) => $theme->isCommerciallyEligible())
            : collect();
        return view('admin.sites.edit', compact('site', 'customers', 'modules', 'paymentGateways', 'customerMenuKeys', 'siteDesign', 'themes'));
    }

    /**
     * بروزرسانی سایت
     */
    public function update(Request $request, Site $site)
    {
        $rules = [
            'customer_id' => 'required|exists:customers,id',
            'title'       => 'required|string|max:255',
            'domain'      => 'nullable|string|max:255|unique:sites,domain,' . $site->id,
        ];
        if (Schema::hasTable('payment_gateways') && Schema::hasColumn('sites', 'payment_gateway_id')) {
            $rules['payment_gateway_id'] = 'nullable|exists:payment_gateways,id';
        }
        if (Schema::hasTable('themes') && Schema::hasColumn('sites', 'theme_id')) {
            $rules['theme_id'] = 'nullable|exists:themes,id';
        }
        $request->validate($rules);

        $data = $request->only([
            'customer_id',
            'title',
            'domain',
            'hosting_company',
            'db_name',
            'db_user',
            'db_host',
            'support_expires_at',
            'host_expires_at',
            'domain_expires_at',
            'support_starts_at',
            'support_level',
        ]);
        $data['support_expires_at'] = $this->normalizeDate($request->support_expires_at);
        $data['host_expires_at'] = $this->normalizeDate($request->host_expires_at);
        $data['domain_expires_at'] = $this->normalizeDate($request->domain_expires_at);
        $data['support_starts_at'] = $this->normalizeDate($request->support_starts_at);
        $data['support_level'] = in_array($request->support_level ?? '', ['basic', 'pro', 'enterprise'], true)
            ? $request->support_level
            : ($site->support_level ?? 'basic');
        $data['is_active'] = $request->boolean('is_active');
        if (Schema::hasColumn('sites', 'payment_gateway_id')) {
            $data['payment_gateway_id'] = $request->filled('payment_gateway_id') ? $request->payment_gateway_id : null;
        }
        $requestedThemeId = Schema::hasColumn('sites', 'theme_id') && $request->filled('theme_id')
            ? (int) $request->theme_id
            : null;
        $previousThemeId = $site->theme_id ? (int) $site->theme_id : null;
        if (Schema::hasColumn('sites', 'customer_menu_keys')) {
            $data['customer_menu_keys'] = $request->input('customer_menu_keys', []);
        }

        // Phase 13-O12a — db_password is never written to the plaintext
        // column anymore; it's only ever encrypted into db_password_encrypted.
        $dbPasswordInput = $request->input('db_password');
        if (!empty($dbPasswordInput)) {
            $data['db_password_encrypted'] = $this->credentialVault->encrypt($dbPasswordInput);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($site, $data, $requestedThemeId, $previousThemeId, $request) {
                $site->update($data);

                if ($requestedThemeId !== null) {
                    $this->themeAssignments->assign(
                        $site,
                        Theme::findOrFail($requestedThemeId),
                        ThemeAssignment::SOURCE_ADMIN,
                        assignedBy: $request->user()?->id,
                    );
                } elseif ($previousThemeId !== null && ($previous = Theme::find($previousThemeId))) {
                    $this->themeAssignments->unassign($site, $previous);
                }
            });
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['theme_id' => $exception->getMessage()]);
        }

        // sync expiry notifications after renewal
        $this->syncExpiryNotifications($site);

        // Phase 17-D — Part 5: بروزرسانی ماژول‌ها — دیگر sync() مستقیم روی
        // همهٔ ماژول‌های فعال پلتفرم (شامل internal) نیست. فقط ماژول‌های
        // قابل اختصاص دستی (همان فهرست فیلترشدهٔ نمایش‌داده‌شده در فرم) لمس
        // می‌شوند؛ ماژول‌های PAID خریداری‌شده که در این فرم اصلاً نمایش داده
        // نمی‌شوند دست‌نخورده باقی می‌مانند. مطابق معنای همین فرم پیش از این
        // فاز، «غایب از input» یعنی «کاربر نمی‌خواهدش فعال باشد» (checkbox
        // بدون ورودی مخفی fallback) — نه «بدون تغییر» (که معنای فرم دیگر،
        // SiteModuleController::update()، است؛ دو فرم عمداً دو معنای متفاوت
        // برای «غایب» دارند و این تفاوت همان‌طور که پیش از این فاز بود حفظ شد).
        $assignableIds = $this->moduleAssignmentService->modulesForManualAssign()->pluck('id')->all();
        $requestedActiveIds = array_map('intval', $request->input('modules', []));
        $moduleResults = $this->moduleAssignmentService->syncManualAssignmentsForSite(
            $site,
            $assignableIds,
            $requestedActiveIds,
            Auth::id()
        );

        // ذخیره تنظیمات ظاهر اختصاصی سایت (فقط اگر فیلد در فرم وجود داشته باشد)
        if (Schema::hasTable('site_settings') && $request->has('site_design_section')) {
            foreach (array_merge(SiteSetting::DESIGN_KEYS, [SiteSetting::SITE_LOGO_KEY]) as $key) {
                $val = $request->input('site_design_' . $key);
                if ($val !== null && (string) $val !== '') {
                    SiteSetting::setForSite($site->id, $key, $val);
                } else {
                    SiteSetting::where('site_id', $site->id)->where('key', $key)->delete();
                }
            }
        }

        ActivityLogger::log('site.updated', 'ویرایش سایت: ' . $site->title, $site);

        $failedModules = array_filter($moduleResults, fn ($r) => $r['result'] === 'failed');
        $message = 'سایت بروزرسانی شد';
        if (count($failedModules)) {
            $reasons = implode('؛ ', array_map(
                fn ($r) => $r['title'] . ': ' . ($r['error'] ?? 'نامشخص'),
                $failedModules
            ));
            $message .= ' — اما ' . count($failedModules) . " ماژول با خطا مواجه شد ({$reasons})";
        }

        return redirect()->route('admin.sites.index')
            ->with(count($failedModules) ? 'error' : 'success', $message);
    }

    /**
     * حذف سرتاسری سایت — تمام رکوردهای مرتبط پاک می‌شوند، فقط لاگ باقی می‌ماند.
     */
    public function destroy(Site $site)
    {
        $title      = $site->title;
        $id         = $site->id;
        $domain     = $site->domain;
        $customerId = $site->customer_id;

        // لاگ قبل از حذف ثبت می‌شود تا در صورت خطا هم ردپا باقی بماند
        ActivityLogger::log('site.deleted', "حذف سرتاسری سایت: {$title}", null, [
            'deleted_site_id' => $id,
            'domain'          => $domain,
            'customer_id'     => $customerId,
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
            // customer_installations فاقد FK به sites است — باید دستی مدیریت شود
            // وضعیت 'deleted' بجای حذف فیزیکی → audit trail باقی می‌ماند
            \App\Models\CustomerInstallation::where('site_id', $id)
                ->whereNotIn('status', ['deleted'])
                ->update(['status' => 'deleted', 'completed_at' => now()]);

            // حذف سایت — FK cascadeOnDelete بقیه جداول را خودکار پاک می‌کند
            \App\Models\Site::find($id)?->delete();
        });

        return redirect()->route('admin.sites.index')
            ->with('success', "سایت «{$title}» و تمام داده‌های مرتبط حذف شد.");
    }

    /**
     * نرمال‌سازی تاریخ: شمسی یا میلادی به Y-m-d برای ذخیره در DB
     */
    /**
     * GAP-M09 — ارسال SMS خوش‌آمدگویی به مشتری پس از ایجاد سایت.
     */
    private function sendSiteCreatedSms(Site $site): void
    {
        try {
            $gateway = SmsGatewayResolver::resolve();
            if (! $gateway) {
                return;
            }

            $customer = $site->customer;
            $mobile   = $customer?->mobile ?? '';
            if ($mobile === '') {
                return;
            }

            $platformName = PlatformSettings::platformName();
            $domain       = $site->domain ? "آدرس: {$site->domain}\n" : '';
            $message      = "کاربر گرامی،\n"
                          . "سایت «{$site->title}» با موفقیت در {$platformName} ایجاد شد.\n"
                          . $domain
                          . 'برای دسترسی به پنل مدیریت وارد شوید.';

            $gateway->send($mobile, $message);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('GAP-M09: site created SMS failed', [
                'site_id' => $site->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * پس از تمدید/تغییر تاریخ انقضا، notification متناظر را در DB به‌روز یا غیرفعال می‌کند.
     * اگر تاریخ جدید بیش از ۳۰ روز آینده باشد → notification غیرفعال می‌شود.
     * اگر هنوز در بازه هشدار باشد → link و body به‌روز می‌شوند.
     */
    private function syncExpiryNotifications(Site $site): void
    {
        if (! Schema::hasTable('platform_site_notifications')) {
            return;
        }
        $expiryMap = [
            'هشدار انقضای هاست'     => ['field' => 'host_expires_at',    'type' => 'host',    'label' => 'هاست'],
            'هشدار انقضای دامنه'     => ['field' => 'domain_expires_at',  'type' => 'domain',  'label' => 'دامنه'],
            'هشدار انقضای پشتیبانی' => ['field' => 'support_expires_at', 'type' => 'support', 'label' => 'پشتیبانی'],
        ];
        foreach ($expiryMap as $title => $meta) {
            $existing = PlatformSiteNotification::where('site_id', $site->id)
                ->where('title', $title)
                ->first();

            $newDate = $site->{$meta['field']};
            if (! $newDate) {
                $existing?->updateQuietly(['is_active' => false]);
                continue;
            }

            $newDateCarbon = \Carbon\Carbon::instance($newDate);
            $daysLeft      = max(0, (int) now()->diffInDays($newDateCarbon, false));

            if ($daysLeft > ExpiryReminderService::DAYS_WINDOW) {
                $existing?->updateQuietly(['is_active' => false]);
                continue;
            }

            // Within warning window — upsert notification immediately (no scheduler wait)
            $dateFa     = Jalali::formatFa($newDateCarbon, 'Y/m/d');
            $expiryLink = '__expiry__:' . $meta['type'] . ':' . $newDateCarbon->toDateString();
            $body       = "تاریخ انقضای {$meta['label']}: {$dateFa}. لطفاً برای تمدید اقدام کنید.";

            if ($existing) {
                $existing->updateQuietly([
                    'body'      => $body,
                    'link'      => $expiryLink,
                    'is_active' => true,
                ]);
            } else {
                $order = PlatformSiteNotification::where('site_id', $site->id)->max('order') + 1;
                PlatformSiteNotification::create([
                    'site_id'   => $site->id,
                    'title'     => $title,
                    'body'      => $body,
                    'type'      => 'warning',
                    'link'      => $expiryLink,
                    'link_text' => null,
                    'audience'  => 'admin',
                    'priority'  => 'high',
                    'is_active' => true,
                    'order'     => $order,
                ]);
            }
        }
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $value = trim($value);
        if (Jalali::isJalaliString($value)) {
            $carbon = Jalali::toCarbon($value);
            return $carbon ? $carbon->format('Y-m-d') : null;
        }
        try {
            $carbon = \Carbon\Carbon::parse($value);
            return $carbon->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
