<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Import Controllers
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;

use App\Http\Controllers\DashboardController; // داشبورد کاربر عادی
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InstallController;

use App\Http\Controllers\Admin\ProfileFieldController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\SiteModuleController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\ModuleLicenseController as AdminModuleLicenseController;
use App\Http\Controllers\Admin\PlatformLicenseController;
use App\Http\Controllers\Admin\UpdateDashboardController;
use App\Http\Controllers\Admin\SiteNotificationController;
use App\Http\Controllers\Admin\PlatformNotificationTemplateController;
use App\Http\Controllers\Admin\SiteUpdateController;
use App\Http\Controllers\Admin\SiteLicenseController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\AccessMenuController;
use App\Http\Controllers\Admin\CaptchaController;
use App\Http\Controllers\Admin\ModuleLabelController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\PlatformSettingsController;
use App\Http\Controllers\Admin\GuideSearchController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ResellerController as AdminResellerController;
use App\Http\Controllers\Admin\ResellerCommissionController as AdminResellerCommissionController;
use App\Http\Controllers\Admin\PayoutRequestController as AdminPayoutRequestController;
use App\Http\Controllers\Admin\SupportPlanController as AdminSupportPlanController;
// PHASE 7.2 — AdminSupportTicketController removed from active routing (redirected to canonical)
// use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\ThemeBuilderController;
use App\Http\Controllers\Admin\ThemeCanvasController;
use App\Http\Controllers\Admin\ThemeMarketplaceAdminController;
use App\Http\Controllers\Admin\ThemeAiController;
use App\Http\Controllers\Admin\ThemeAiGeneratorController;
use App\Http\Controllers\ThemeMarketplacePaymentController;
use App\Http\Controllers\PageController;

use App\Http\Middleware\EnsureProfileIsComplete;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormFrontendController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormSiteController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormSubmissionController;
use App\Http\Controllers\Customer\CustomerFormController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\CheckoutController;

/*
|--------------------------------------------------------------------------
| صفحه اصلی
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => view('welcome'))->name('home');

/*
|--------------------------------------------------------------------------
| Health Endpoints — PHASE 8.2 (load-balancer / orchestrator probes)
|--------------------------------------------------------------------------
| No auth, no CSRF, always bypass maintenance mode.
*/
Route::prefix('health')->name('health.')->group(function () {
    Route::get('live', function () {
        return response()->json(
            app(\SaaSPlatform\CoreUtils\Services\Health\RuntimeHealthService::class)->live()
        );
    })->name('live');

    Route::get('ready', function () {
        $result = app(\SaaSPlatform\CoreUtils\Services\Health\RuntimeHealthService::class)->ready();
        return response()->json($result, $result['ready'] ? 200 : 503);
    })->name('ready');

    Route::get('release', function () {
        $result = app(\SaaSPlatform\CoreUtils\Services\Health\RuntimeHealthService::class)->release();
        return response()->json($result, $result['coherent'] ? 200 : 503);
    })->name('release');
});

// سرو فایل‌های استوریج از storage/app/public (جایگزین لینک نمادین وقتی ۴۰۴ می‌دهد)
Route::get('storage/{path}', function (string $path) {
    $path = str_replace(['..', '\\'], ['', '/'], $path);
    $path = trim($path, '/');
    if ($path === '') {
        abort(404);
    }
    $fullPath = storage_path('app/public/' . $path);
    if (!\Illuminate\Support\Facades\File::exists($fullPath) || !\Illuminate\Support\Facades\File::isFile($fullPath)) {
        abort(404);
    }
    $mime = match (strtolower(\Illuminate\Support\Facades\File::extension($fullPath))) {
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        // Commercial Productization fase-4 — a real bug found via an
        // actual local end-to-end theme install (not a fake/mocked HTTP
        // test): this map predates the theme's real local demo video
        // (hero-intro.webm) and had no video/audio extensions at all, so
        // every such file fell through to 'application/octet-stream'.
        // customer-site's ThemeMediaLocalizationService correctly rejects
        // that as an untrusted content type — the real fix belongs here,
        // at the actual source of the wrong Content-Type, not by loosening
        // that validation on the receiving end.
        'webm' => 'video/webm',
        'mp4' => 'video/mp4',
        'ogg' => 'video/ogg',
        'mov' => 'video/quicktime',
        default => 'application/octet-stream',
    };
    return response()->file($fullPath, ['Content-Type' => $mime]);
})->where('path', '.+');

// سرو فایل‌های آیکن از public/page/icon/svg/ (قبل از page/{slug} تا تداخل نداشته باشد)
Route::get('page/icon/svg/{filename}', function (string $filename) {
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
        abort(404);
    }
    $path = public_path('page/icon/svg/' . $filename);
    if (!\Illuminate\Support\Facades\File::exists($path)) {
        abort(404);
    }
    $mime = match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        default => 'application/octet-stream',
    };
    return response()->file($path, ['Content-Type' => $mime]);
})->where('filename', '[a-zA-Z0-9_\-\.]+');

// صفحه ثابت (درباره ما، قوانین، راهنما و ...)
Route::get('page/{slug}', [PageController::class, 'show'])->name('page.show');


/*
|--------------------------------------------------------------------------
| Authentication (ورود / ثبت نام / خروج)
|--------------------------------------------------------------------------
*/

// فرم ورود
Route::get('/login', [LoginController::class, 'show'])
    ->name('login.show');

// ارسال اطلاعات ورود
Route::post('/login', [LoginController::class, 'login'])
    ->name('login');

// خروج
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');

// فرم ثبت‌نام
Route::get('/register', [RegisterController::class, 'showRegisterForm'])
    ->name('register');

// ارسال ثبت‌نام
Route::post('/register', [RegisterController::class, 'register'])
    ->name('register.submit');


/*
|--------------------------------------------------------------------------
| بخش کاربر عادی (بعد از ورود)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // تکمیل پروفایل (فقط وقتی فرم توسط ادمین فعال باشد)
    Route::get('/complete-profile', [ProfileController::class, 'show'])
        ->middleware('profile.form.enabled')
        ->name('profile.complete');

    Route::post('/complete-profile', [ProfileController::class, 'store'])
        ->middleware('profile.form.enabled')
        ->name('profile.store');

    // ویرایش پروفایل
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    // داشبورد کاربر (نیازمند تکمیل پروفایل)
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware(EnsureProfileIsComplete::class)
        ->name('dashboard');

    // فرم‌های قابل دسترسی برای مشتری (منوی پنل مشتری)
    Route::get('/dashboard/forms', [CustomerFormController::class, 'index'])
        ->middleware(EnsureProfileIsComplete::class)
        ->name('customer.forms.index');

    // PHASE 7.2 — Customer portal support-tickets redirected to canonical /tickets/* routes
    // CustomerSupportTicketController removed from active routing; data lives in tickets table.
    Route::prefix('dashboard/support-tickets')->name('customer.support-tickets.')->middleware(EnsureProfileIsComplete::class)->group(function () {
        Route::get('/', fn() => redirect()->route('tickets.site.index', [], 301))->name('index');
        Route::get('/create', fn() => redirect()->route('tickets.site.create', [], 301))->name('create');
        Route::post('/', fn() => redirect()->route('tickets.site.store', [], 301))->name('store');
        Route::get('/{support_ticket}', fn($id) => redirect()->route('tickets.site.show', $id, 301))->name('show');
        Route::post('/{support_ticket}/replies', fn($id) => redirect()->route('tickets.site.replies.store', $id, 301))->name('replies.store');
    });

    // فروشگاه و پرداخت (خرید لایسنس، ماژول، پشتیبانی)
    Route::get('/store', [StoreController::class, 'index'])->name('store.index');
    Route::get('/store/modules', [StoreController::class, 'modules'])->name('store.modules');
    Route::get('/store/cart', [StoreController::class, 'cart'])->name('store.cart');
    Route::post('/store/cart/add', [StoreController::class, 'addToCart'])->name('store.cart.add');
    Route::get('/store/cart/remove/{key}', [StoreController::class, 'removeFromCart'])->name('store.cart.remove');
    Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
    Route::get('/checkout/callback/{invoice}', [CheckoutController::class, 'callback'])->name('checkout.callback');

    // Theme Marketplace payment callback (no CSRF, public — called by payment gateway)
    Route::get('/theme-marketplace/callback', [ThemeMarketplacePaymentController::class, 'callback'])->name('theme-marketplace.payment-callback')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

    // Theme Bundle payment callback
    Route::get('/theme-bundles/payment-callback', [\App\Http\Controllers\Api\ThemeBundlePaymentCallbackController::class, 'handle'])->name('theme-bundles.payment-callback')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

    // موشن گرافیک (پنل مشتری) — Phase 11
    Route::prefix('dashboard/motion-graphics')->name('customer.motion-graphics.')->middleware(EnsureProfileIsComplete::class)->group(function () {
        Route::get('/', [\App\Http\Controllers\Customer\MotionGraphicsController::class, 'index'])->name('index');
        Route::post('/request', [\App\Http\Controllers\Customer\MotionGraphicsController::class, 'requestMotion'])->name('request');
        Route::post('/{commission}/purchase', [\App\Http\Controllers\Customer\MotionGraphicsController::class, 'purchase'])->name('purchase');
    });

    // پنل نمایندگی (فقط برای کاربران دارای رکورد Reseller)
    Route::prefix('reseller')->name('reseller.')->middleware('reseller')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Reseller\DashboardController::class, 'index'])->name('dashboard');
        Route::post('/payout-request', [\App\Http\Controllers\Reseller\PayoutRequestController::class, 'store'])->name('payout-request.store');
    });
});


/*
|--------------------------------------------------------------------------
| مراحل نصب سیستم (Installer)
|--------------------------------------------------------------------------
*/

Route::get('/install', [InstallController::class, 'step1'])
    ->name('install.step1');

Route::post('/install/db', [InstallController::class, 'saveDb'])
    ->name('install.db');

Route::post('/install/admin', [InstallController::class, 'saveAdmin'])
    ->name('install.admin');

Route::get('/install/done', [InstallController::class, 'done'])
    ->name('install.done');

/*
|--------------------------------------------------------------------------
| Platform Installer (New — Phase 1)
| All routes protected by InstallerLockMiddleware except locked + unlock.
|--------------------------------------------------------------------------
*/
Route::prefix('platform-installer')->name('installer.')->group(function () {
    // Always accessible (no lock restriction)
    Route::get('/locked',  [\App\Http\Controllers\Installer\PlatformInstallerController::class, 'locked'])->name('locked');
    Route::post('/unlock', [\App\Http\Controllers\Installer\PlatformInstallerController::class, 'unlock'])->name('unlock');
    Route::get('/complete',[\App\Http\Controllers\Installer\PlatformInstallerController::class, 'complete'])->name('complete');
    Route::get('/report/{format}', [\App\Http\Controllers\Installer\PlatformInstallerController::class, 'downloadReport'])
        ->name('report-download')
        ->where('format', 'html|pdf');

    // Main installer wizard (blocked when installer is locked)
    Route::middleware(\App\Http\Middleware\InstallerLockMiddleware::class)->group(function () {
        Route::get('/',                [\App\Http\Controllers\Installer\PlatformInstallerController::class, 'index'])->name('index');
        Route::post('/select-mode',    [\App\Http\Controllers\Installer\PlatformInstallerController::class, 'selectMode'])->name('select-mode');
        Route::get('/check',           [\App\Http\Controllers\Installer\PlatformInstallerController::class, 'apiCheck'])->name('api-check');

        Route::get('/{mode}/{step}',   [\App\Http\Controllers\Installer\PlatformInstallerController::class, 'step'])->name('step');
        Route::post('/{mode}/{step}',  [\App\Http\Controllers\Installer\PlatformInstallerController::class, 'processStep'])->name('process');
    });
});

/*
|--------------------------------------------------------------------------
| فرم‌های سایت مشتری (نمایش و ارسال با کپچا)
|--------------------------------------------------------------------------
*/
Route::prefix('site')->name('site.')->group(function () {
    Route::get('{site}/forms', [FormFrontendController::class, 'siteForms'])->name('forms.by_site');
    Route::get('{site}/forms/{slug}', [FormFrontendController::class, 'showForSite'])->name('forms.show_for_site');
    Route::post('{site}/forms/{slug}', [FormFrontendController::class, 'submitForSite'])->name('forms.submit_for_site');
});

/*
|--------------------------------------------------------------------------
| Signed theme preview (no session auth — for the headless screenshot
| worker only). Reuses ThemeCanvasController::preview() verbatim; the
| `signed` middleware (temporarySignedRoute, short TTL) is the access
| control instead of auth+admin+wizard.
|--------------------------------------------------------------------------
*/
Route::get('admin/theme-builder/{theme}/canvas/{pageKey}/preview-signed', [ThemeCanvasController::class, 'preview'])
    ->name('admin.theme-builder.canvas.page-preview-signed')
    ->middleware('signed')
    ->where('pageKey', '[a-z0-9_-]+');

/*
|--------------------------------------------------------------------------
| پنل مدیریت (Admin Panel)
|--------------------------------------------------------------------------
|
| دسترسی فقط برای:
| - کاربر لاگین شده
| - کاربر با نقش admin
|
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin'])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | داشبورد ادمین
        |--------------------------------------------------------------------------
        */
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/profile', [\App\Http\Controllers\Admin\AdminProfileController::class, 'index'])->name('profile.index');

        /*
        |--------------------------------------------------------------------------
        | اعلان‌های سیستمی پلتفرم
        |--------------------------------------------------------------------------
        */
        Route::prefix('system-alerts')->name('system-alerts.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminNotificationController::class, 'index'])->name('index');
            Route::get('/unread-count', [\App\Http\Controllers\Admin\AdminNotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('/{id}/read', [\App\Http\Controllers\Admin\AdminNotificationController::class, 'markRead'])->name('mark-read');
            Route::post('/read-all', [\App\Http\Controllers\Admin\AdminNotificationController::class, 'markAllRead'])->name('read-all');
            Route::delete('/{id}', [\App\Http\Controllers\Admin\AdminNotificationController::class, 'destroy'])->name('destroy');
        });
        Route::put('/profile', [\App\Http\Controllers\Admin\AdminProfileController::class, 'update'])->name('profile.update');

        /*
        |--------------------------------------------------------------------------
        | مدیریت ماژول‌ها، لایسنس‌ها و بروزرسانی‌ها
        |--------------------------------------------------------------------------
        */
        Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::post('modules/bulk-update', [ModuleController::class, 'bulkUpdate'])->name('modules.bulk-update');
        Route::get('modules/{module}/edit', [ModuleController::class, 'edit'])->name('modules.edit');
        Route::put('modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
        Route::get('module-licenses', [AdminModuleLicenseController::class, 'index'])->name('module-licenses.index');
        Route::post('module-licenses/grant', [AdminModuleLicenseController::class, 'grant'])->name('module-licenses.grant');
        Route::post('module-licenses/{license}/revoke', [AdminModuleLicenseController::class, 'revoke'])->name('module-licenses.revoke');
        Route::get('licenses', [PlatformLicenseController::class, 'index'])->name('licenses.index');
        Route::post('licenses/{platform_license}/revoke', [PlatformLicenseController::class, 'revoke'])->name('licenses.revoke');
        Route::post('licenses/{platform_license}/reactivate', [PlatformLicenseController::class, 'reactivate'])->name('licenses.reactivate');
        Route::delete('licenses/{platform_license}', [PlatformLicenseController::class, 'destroy'])->name('licenses.destroy');
        Route::get('updates', [UpdateDashboardController::class, 'index'])->name('updates.index');

        /*
        |--------------------------------------------------------------------------
        | مدیریت فیلدهای پروفایل
        |--------------------------------------------------------------------------
        */

        Route::resource('profile-fields', ProfileFieldController::class);


        /*
        |--------------------------------------------------------------------------
        | تنظیمات فرم پروفایل
        |--------------------------------------------------------------------------
        */

        Route::get('/settings/profile-form', [SettingController::class, 'index'])
            ->name('settings.profile-form');

        Route::post('/settings/profile-form/toggle-display', [SettingController::class, 'toggleProfileForm'])
            ->name('settings.profile-form.toggle-display');

        Route::post('/settings/profile-form/toggle-force', [SettingController::class, 'toggleForceCompleteProfile'])
            ->name('settings.profile-form.toggle-force');

        /*
        |--------------------------------------------------------------------------
        | دسترسی و منو (ماژول‌ها + نمایش منوی کناری)
        |--------------------------------------------------------------------------
        */
        Route::get('/access', [AccessMenuController::class, 'index'])->name('access.index');
        Route::post('/access', [AccessMenuController::class, 'update'])->name('access.update');

        /*
        |--------------------------------------------------------------------------
        | مدیریت کاربران پلتفرم
        |--------------------------------------------------------------------------
        */
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/bulk-toggle', [UserController::class, 'bulkToggle'])->name('users.bulk-toggle');
        Route::post('users/bulk-delete', [UserController::class, 'bulkDelete'])->name('users.bulk-delete');
        Route::post('users/bulk-restore', [UserController::class, 'bulkRestore'])->name('users.bulk-restore');
        Route::get('users/export-csv', [UserController::class, 'exportCsv'])->name('users.export-csv');
        Route::get('users/import', [UserController::class, 'showImportForm'])->name('users.import');
        Route::post('users/import', [UserController::class, 'importCsv'])->name('users.import.csv');

        /*
        |--------------------------------------------------------------------------
        | تنظیمات کپچا (ماژول کپچا)
        |--------------------------------------------------------------------------
        */
        Route::get('/captcha', [CaptchaController::class, 'index'])->name('captcha.index');
        Route::post('/captcha', [CaptchaController::class, 'update'])->name('captcha.update');
        Route::get('/sites/{site}/captcha', [CaptchaController::class, 'siteSettings'])->name('captcha.site');
        Route::post('/sites/{site}/captcha', [CaptchaController::class, 'siteUpdate'])->name('captcha.site.update');

        Route::get('/module-labels', [ModuleLabelController::class, 'index'])->name('module-labels.index');
        Route::post('/module-labels', [ModuleLabelController::class, 'update'])->name('module-labels.update');

        /*
        |--------------------------------------------------------------------------
        | لاگ فعالیت (ممیزی و امنیت)
        |--------------------------------------------------------------------------
        */
        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

        // PHASE 8.0 — Secure attachment download (private disk, access-controlled)
        Route::get('attachments/{path}', [\App\Http\Controllers\Admin\SecureAttachmentController::class, 'download'])
            ->where('path', '.+')
            ->name('admin.attachments.download');

        /*
        |--------------------------------------------------------------------------
        | تنظیمات عمومی پلتفرم
        |--------------------------------------------------------------------------
        */
        Route::get('platform-settings', [PlatformSettingsController::class, 'index'])->name('platform-settings.index');
        Route::post('platform-settings', [PlatformSettingsController::class, 'update'])->name('platform-settings.update');

        /*
        |--------------------------------------------------------------------------
        | مدیریت مشتری‌ها
        |--------------------------------------------------------------------------
        */

        // ADR-088 — unified customer & site onboarding wizard. The legacy
        // customers/create, sites/create, sites/wizard and
        // customer-deployment wizard pages redirect here.
        Route::prefix('onboarding')->name('onboarding.')
            ->controller(\App\Http\Controllers\Admin\OnboardingWizardController::class)
            ->group(function () {
                Route::get('/', 'create')->name('create');
                Route::get('/lookup-customer', 'lookupCustomer')->middleware('throttle:60,1')->name('lookup-customer');
                Route::get('/check-domain', 'checkDomain')->middleware('throttle:60,1')->name('check-domain');
                Route::get('/server-capacity', 'serverCapacity')->middleware('throttle:60,1')->name('server-capacity');
                Route::get('/suggest-infrastructure', 'suggestInfrastructure')->middleware('throttle:60,1')->name('suggest-infrastructure');
                Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
                Route::post('/installations/{installation}/retry-template', 'retryTemplate')
                    ->whereNumber('installation')->middleware('throttle:10,1')->name('retry-template');
                Route::post('/fleet-updates/apply', 'applyFleetUpdate')
                    ->middleware('throttle:30,1')->name('fleet-updates.apply');
                Route::post('/fleet-updates/apply-package', 'applyPackageFleetUpdate')
                    ->middleware('throttle:30,1')->name('fleet-updates.apply-package');
                Route::post('/fleet-updates/rollback', 'rollbackFleetUpdate')
                    ->middleware('throttle:30,1')->name('fleet-updates.rollback');
            });
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::post('customers/bulk-delete', [CustomerController::class, 'bulkDelete'])->name('customers.bulk-delete');
        Route::post('customers/bulk-restore', [CustomerController::class, 'bulkRestore'])->name('customers.bulk-restore');
        Route::get('customers/export-csv', [CustomerController::class, 'exportCsv'])->name('customers.export-csv');
        Route::get('customers/import', [CustomerController::class, 'showImportForm'])->name('customers.import');
        Route::post('customers/import', [CustomerController::class, 'importCsv'])->name('customers.import.csv');


        /*
        |--------------------------------------------------------------------------
        | مدیریت سایت‌ها
        |--------------------------------------------------------------------------
        */

        Route::get('sites/wizard', [SiteController::class, 'wizard'])->name('sites.wizard');
        Route::resource('sites', SiteController::class);
        // Platform Site Agent
        Route::get('sites/{site}/agent', [\App\Http\Controllers\Admin\PlatformSiteAgentController::class, 'show'])->name('sites.agents.show');
        Route::post('sites/{site}/agent', [\App\Http\Controllers\Admin\PlatformSiteAgentController::class, 'provision'])->name('sites.agents.provision');
        Route::get('sites/{site}/agent/password', [\App\Http\Controllers\Admin\PlatformSiteAgentController::class, 'revealPassword'])->name('sites.agents.reveal-password');
        Route::post('sites/{site}/agent/log-access', [\App\Http\Controllers\Admin\PlatformSiteAgentController::class, 'logAccess'])->name('sites.agents.log-access');
        Route::delete('sites/{site}/agent', [\App\Http\Controllers\Admin\PlatformSiteAgentController::class, 'revoke'])->name('sites.agents.revoke');
        Route::get('sites/{site}/modules', [SiteModuleController::class, 'edit'])->name('sites.modules.edit');
        Route::put('sites/{site}/modules', [SiteModuleController::class, 'update'])->name('sites.modules.update');
        Route::post('sites/{site}/modules/toggle', [SiteModuleController::class, 'toggle'])->name('sites.modules.toggle');
        // ADR-087 — central platform-asset access (templates, widgets).
        Route::get('asset-access', [\App\Http\Controllers\Admin\AssetAccessController::class, 'index'])->name('asset-access.index');
        Route::post('asset-access/overrides', [\App\Http\Controllers\Admin\AssetAccessController::class, 'saveOverride'])->name('asset-access.overrides.save');
        Route::post('asset-access/overrides/clear', [\App\Http\Controllers\Admin\AssetAccessController::class, 'clearOverride'])->name('asset-access.overrides.clear');
        Route::get('sites/{site}/asset-access', [\App\Http\Controllers\Admin\AssetAccessController::class, 'site'])->name('sites.asset-access');
        Route::post('sites/{site}/asset-access/vertical', [\App\Http\Controllers\Admin\AssetAccessController::class, 'updateVertical'])->name('sites.asset-access.vertical');
        Route::post('sites/{site}/asset-access/grants', [\App\Http\Controllers\Admin\AssetAccessController::class, 'grant'])->name('sites.asset-access.grant');
        Route::post('sites/{site}/asset-access/grants/revoke', [\App\Http\Controllers\Admin\AssetAccessController::class, 'revoke'])->name('sites.asset-access.revoke');
        // Phase 13-K — module upgrade request & approval flow
        Route::get('sites/{site}/module-upgrades', [\App\Http\Controllers\Admin\SiteModuleUpgradeController::class, 'index'])->name('sites.module-upgrades.index');
        Route::get('sites/{site}/module-upgrades/{moduleSlug}', [\App\Http\Controllers\Admin\SiteModuleUpgradeController::class, 'confirm'])->name('sites.module-upgrades.confirm');
        Route::post('sites/{site}/module-upgrades/{moduleSlug}', [\App\Http\Controllers\Admin\SiteModuleUpgradeController::class, 'store'])->name('sites.module-upgrades.store');
        // ADR-015: Menu management removed from platform admin.
        // Each customer site manages its own menus in its own admin panel (customer-site /admin/menus/*).
        // Platform admin has no role in tenant menu structure.
        Route::get('sites/{site}/seo', [SeoController::class, 'index'])->name('sites.seo.index');
        Route::post('sites/{site}/seo/redirects', [SeoController::class, 'storeRedirect'])->name('sites.seo.redirects.store');
        Route::put('sites/{site}/seo/redirects/{redirect}', [SeoController::class, 'updateRedirect'])->name('sites.seo.redirects.update');
        Route::delete('sites/{site}/seo/redirects/{redirect}', [SeoController::class, 'destroyRedirect'])->name('sites.seo.redirects.destroy');
        Route::get('sites/{site}/updates', [SiteUpdateController::class, 'index'])->name('sites.updates.index');
        Route::post('sites/{site}/updates', [SiteUpdateController::class, 'store'])->name('sites.updates.store');
        Route::get('sites/{site}/license', [SiteLicenseController::class, 'create'])->name('sites.license.create');
        Route::post('sites/{site}/license', [SiteLicenseController::class, 'store'])->name('sites.license.store');
        Route::get('sites/{site}/license/show', [SiteLicenseController::class, 'show'])->name('sites.license.show');

        // LIFECYCLE: Customer site lifecycle management
        Route::get('lifecycle', [\App\Http\Controllers\Admin\SiteLifecycleController::class, 'overview'])->name('lifecycle.overview');
        Route::get('lifecycle/settings', [\App\Http\Controllers\Admin\SiteLifecycleController::class, 'settings'])->name('lifecycle.settings');
        Route::put('lifecycle/settings', [\App\Http\Controllers\Admin\SiteLifecycleController::class, 'updateSettings'])->name('lifecycle.settings.update');
        Route::get('sites/{site}/lifecycle', [\App\Http\Controllers\Admin\SiteLifecycleController::class, 'index'])->name('sites.lifecycle.index');
        Route::post('sites/{site}/lifecycle/transition', [\App\Http\Controllers\Admin\SiteLifecycleController::class, 'forceTransition'])->name('sites.lifecycle.transition');
        Route::post('sites/{site}/lifecycle/restore', [\App\Http\Controllers\Admin\SiteLifecycleController::class, 'forceRestore'])->name('sites.lifecycle.restore');
        Route::post('sites/{site}/lifecycle/invoices/{invoice}/paid', [\App\Http\Controllers\Admin\SiteLifecycleController::class, 'markInvoicePaid'])->name('sites.lifecycle.invoice-paid');
        // C01 — send payment link SMS + create restore invoice
        Route::post('sites/{site}/lifecycle/send-payment-link', [\App\Http\Controllers\Admin\SiteLifecycleController::class, 'sendPaymentLink'])->name('sites.lifecycle.send-payment-link');

        // RESOURCE GOVERNANCE: Storage, DB, bandwidth and quota management
        Route::get('resource-governance', [\App\Http\Controllers\Admin\SiteResourceGovernanceController::class, 'index'])->name('resource-governance.index');
        Route::get('resource-governance/settings', [\App\Http\Controllers\Admin\SiteResourceGovernanceController::class, 'settings'])->name('resource-governance.settings');
        Route::put('resource-governance/settings', [\App\Http\Controllers\Admin\SiteResourceGovernanceController::class, 'updateSettings'])->name('resource-governance.settings.update');
        Route::get('resource-governance/{site}', [\App\Http\Controllers\Admin\SiteResourceGovernanceController::class, 'site'])->name('resource-governance.site');
        Route::post('resource-governance/{site}/recalculate', [\App\Http\Controllers\Admin\SiteResourceGovernanceController::class, 'recalculate'])->name('resource-governance.recalculate');
        Route::put('resource-governance/{site}/limits', [\App\Http\Controllers\Admin\SiteResourceGovernanceController::class, 'updateLimits'])->name('resource-governance.update-limits');

        // DEVOPS: measured disk governance, danger zones and audited storage pruning
        Route::prefix('devops/resources')->name('devops.resources.')->controller(\App\Http\Controllers\Admin\DevOpsResourceController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('metrics', 'metrics')->name('metrics');
            Route::get('{site}', 'show')->whereNumber('site')->name('show');
            Route::post('{site}/audit', 'audit')->whereNumber('site')->middleware('throttle:10,1')->name('audit');
            Route::post('{site}/preview', 'preview')->whereNumber('site')->middleware('throttle:10,1')->name('preview');
            Route::post('{site}/prune', 'prune')->whereNumber('site')->middleware('throttle:5,1')->name('prune');
            Route::put('{site}/quota', 'updateQuota')->whereNumber('site')->name('quota');
        });

        Route::middleware('notifications.enabled')->group(function () {
            Route::get('sites/notifications/create-bulk', [SiteNotificationController::class, 'createBulk'])->name('sites.notifications.create-bulk');
            Route::post('sites/notifications/store-bulk', [SiteNotificationController::class, 'storeBulk'])->name('sites.notifications.store-bulk');
            Route::get('sites/{site}/notifications', [SiteNotificationController::class, 'index'])->name('sites.notifications.index');
            Route::get('sites/{site}/notifications/create', [SiteNotificationController::class, 'create'])->name('sites.notifications.create');
            Route::post('sites/{site}/notifications', [SiteNotificationController::class, 'store'])->name('sites.notifications.store');
            Route::get('sites/{site}/notifications/{notification}/edit', [SiteNotificationController::class, 'edit'])->name('sites.notifications.edit');
            Route::put('sites/{site}/notifications/{notification}', [SiteNotificationController::class, 'update'])->name('sites.notifications.update');
            Route::delete('sites/{site}/notifications/{notification}', [SiteNotificationController::class, 'destroy'])->name('sites.notifications.destroy');
            Route::post('notifications/upload-image', [SiteNotificationController::class, 'uploadImage'])->name('notifications.upload-image');
            Route::get('notification-templates', [PlatformNotificationTemplateController::class, 'index'])->name('notification-templates.index');
            Route::get('notification-templates/create', [PlatformNotificationTemplateController::class, 'create'])->name('notification-templates.create');
            Route::post('notification-templates', [PlatformNotificationTemplateController::class, 'store'])->name('notification-templates.store');
            Route::get('notification-templates/{template}/edit', [PlatformNotificationTemplateController::class, 'edit'])->name('notification-templates.edit');
            Route::put('notification-templates/{template}', [PlatformNotificationTemplateController::class, 'update'])->name('notification-templates.update');
            Route::delete('notification-templates/{template}', [PlatformNotificationTemplateController::class, 'destroy'])->name('notification-templates.destroy');
            Route::post('notification-templates/upload-image', [PlatformNotificationTemplateController::class, 'uploadImage'])->name('notification-templates.upload-image');
        });

        Route::resource('products', AdminProductController::class)->except('show');
        Route::resource('resellers', AdminResellerController::class);
        Route::get('reseller-commissions', [AdminResellerCommissionController::class, 'index'])->name('reseller-commissions.index');
        Route::patch('reseller-commissions/{commission}/mark-paid', [AdminResellerCommissionController::class, 'markPaid'])->name('reseller-commissions.mark-paid');
        Route::get('payout-requests', [AdminPayoutRequestController::class, 'index'])->name('payout-requests.index');
        Route::patch('payout-requests/{payout_request}/mark-paid', [AdminPayoutRequestController::class, 'markPaid'])->name('payout-requests.mark-paid');
        Route::patch('payout-requests/{payout_request}/reject', [AdminPayoutRequestController::class, 'reject'])->name('payout-requests.reject');
        Route::resource('support-plans', AdminSupportPlanController::class)->except('show');

        // SP-5.0 — Knowledge Base
        Route::prefix('kb')->name('kb.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'index'])->name('index');
            Route::get('/create',            [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'create'])->name('create');
            Route::post('/',                 [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'store'])->name('store');
            Route::get('/{article}',         [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'show'])->name('show');
            Route::get('/{article}/edit',    [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'edit'])->name('edit');
            Route::put('/{article}',         [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'update'])->name('update');
            Route::delete('/{article}',      [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'destroy'])->name('destroy');
            // Categories
            Route::get('/categories',        [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'categories'])->name('categories');
            Route::post('/categories',       [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'storeCategory'])->name('categories.store');
            Route::patch('/categories/{category}', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'updateCategory'])->name('categories.update');
            Route::delete('/categories/{category}', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'destroyCategory'])->name('categories.destroy');
        });
        Route::resource('payment-gateways', PaymentGatewayController::class)->except('show');

        // ADR-015: Forms overview — read-only, shows which sites have forms module enabled.
        // Full form management is done in each site's own admin panel (customer-site).
        Route::get('forms', [\App\Http\Controllers\Admin\FormController::class, 'index'])->name('forms.index');

        // App Releases — دانلود اپلیکیشن
        Route::prefix('app-releases')->name('app-releases.')->group(function () {
            Route::get('/',                              [\App\Http\Controllers\Admin\AppReleaseController::class, 'index'])->name('index');
            Route::post('/',                             [\App\Http\Controllers\Admin\AppReleaseController::class, 'store'])->name('store');
            Route::get('/{release}/download',            [\App\Http\Controllers\Admin\AppReleaseController::class, 'download'])->name('download');
            Route::patch('/{release}/set-latest',        [\App\Http\Controllers\Admin\AppReleaseController::class, 'setLatest'])->name('set-latest');
            Route::delete('/{release}',                  [\App\Http\Controllers\Admin\AppReleaseController::class, 'destroy'])->name('destroy');
        });
        // PHASE 7.2 — Legacy admin support-tickets routes redirected to canonical /admin/tickets/* routes.
        // AdminSupportTicketController has been removed from active routing.
        Route::get('support-tickets',                fn() => redirect()->route('tickets.admin.index', [], 301))->name('support-tickets.index');
        Route::get('support-tickets/create',         fn() => redirect()->route('tickets.admin.create', [], 301))->name('support-tickets.create');
        Route::post('support-tickets',               fn() => redirect()->route('tickets.admin.store', [], 301))->name('support-tickets.store');
        Route::get('support-tickets/{id}',           fn($id) => redirect()->route('tickets.admin.show', $id, 301))->name('support-tickets.show');
        Route::patch('support-tickets/{id}/status',  fn($id) => redirect()->route('tickets.admin.status.update', $id, 301))->name('support-tickets.update-status');
        Route::post('support-tickets/{id}/replies',  fn($id) => redirect()->route('tickets.admin.replies.store', $id, 301))->name('support-tickets.replies.store');
        Route::patch('support-tickets/{id}/assign',  fn($id) => redirect()->route('tickets.admin.assign', $id, 301))->name('support-tickets.assign');

        /*
        |--------------------------------------------------------------------------
        | صفحات ثابت و راهنما
        |--------------------------------------------------------------------------
        */
        Route::post('pages/upload-image', [AdminPageController::class, 'uploadImage'])->name('pages.upload-image');
        Route::resource('pages', AdminPageController::class);
        Route::post('pages/menu-position', [AdminPageController::class, 'menuPosition'])->name('pages.menu-position');
        Route::post('pages/reorder', [AdminPageController::class, 'reorder'])->name('pages.reorder');

        /*
        |--------------------------------------------------------------------------
        | Theme Builder / Wizard (فقط Super Admin)
        | عملیات ساختاری: ایجاد تم، انتشار، وارد کردن قالب، تخصیص به سایت.
        | مشتریان (Tenant Admin) و Staff به این مسیرها دسترسی ندارند.
        |--------------------------------------------------------------------------
        */
        Route::middleware('wizard')->group(function () {
            Route::get('theme-builder', [ThemeBuilderController::class, 'index'])->name('theme-builder.index');
            Route::get('theme-builder/create', [ThemeBuilderController::class, 'create'])->name('theme-builder.create');
            Route::post('theme-builder', [ThemeBuilderController::class, 'store'])->name('theme-builder.store');
            Route::get('theme-builder/import', function () {
                return redirect()->route('admin.theme-builder.index');
            });
            Route::post('theme-builder/import', [ThemeBuilderController::class, 'import'])->name('theme-builder.import');
            Route::post('theme-builder/assign', [ThemeBuilderController::class, 'assign'])->name('theme-builder.assign');
            Route::get('theme-builder/{theme_builder}/edit', [ThemeBuilderController::class, 'edit'])->name('theme-builder.edit');
            Route::put('theme-builder/{theme_builder}', [ThemeBuilderController::class, 'update'])->name('theme-builder.update');
            Route::delete('theme-builder/{theme_builder}', [ThemeBuilderController::class, 'destroy'])->name('theme-builder.destroy');
            Route::post('theme-builder/{theme_builder}/publish', [ThemeBuilderController::class, 'publish'])->name('theme-builder.publish');
            Route::post('theme-builder/{theme_builder}/unpublish', [ThemeBuilderController::class, 'unpublish'])->name('theme-builder.unpublish');
            Route::post('theme-builder/{theme_builder}/revision', [ThemeBuilderController::class, 'saveRevision'])->name('theme-builder.revision');
            Route::get('theme-builder/{theme_builder}/revisions', [ThemeBuilderController::class, 'listRevisions'])->name('theme-builder.revisions');
            Route::get('theme-builder/{theme_builder}/export', [ThemeBuilderController::class, 'export'])->name('theme-builder.export');
            Route::get('theme-builder/{theme_builder}/preview', [ThemeBuilderController::class, 'preview'])->name('theme-builder.preview');
            Route::get('theme-builder/{theme_builder}/clone', function (\App\Models\Theme $theme_builder) {
                return view('admin.theme-builder.clone', ['theme' => $theme_builder]);
            })->name('theme-builder.clone-form');
            Route::post('theme-builder/{theme_builder}/clone', [ThemeBuilderController::class, 'cloneTheme'])->name('theme-builder.clone');
            Route::post('theme-revisions/{revision}/restore', [ThemeBuilderController::class, 'restoreRevision'])->name('theme-builder.restore-revision');

            // ─── Canvas Builder ──────────────────────────────────────────────
            Route::get('theme-builder/{theme}/canvas', [ThemeCanvasController::class, 'show'])->name('theme-builder.canvas');
            Route::put('theme-builder/{theme}/canvas/save', [ThemeCanvasController::class, 'save'])->name('theme-builder.canvas.save');
            Route::patch('theme-builder/{theme}/canvas/global-styles', [ThemeCanvasController::class, 'saveGlobalStyles'])->name('theme-builder.canvas.global-styles');
            Route::post('theme-builder/{theme}/canvas/preview-ajax', [ThemeCanvasController::class, 'previewAjax'])->name('theme-builder.canvas.preview-ajax');
            Route::post('theme-builder/{theme}/canvas/pages', [ThemeCanvasController::class, 'addPage'])->name('theme-builder.canvas.pages.add');
            Route::delete('theme-builder/{theme}/canvas/pages/{key}', [ThemeCanvasController::class, 'removePage'])->name('theme-builder.canvas.pages.remove')->where('key', '[a-z0-9_-]+');
            Route::get('theme-builder/{theme}/canvas/{pageKey}/preview', [ThemeCanvasController::class, 'preview'])->name('theme-builder.canvas.page-preview')->where('pageKey', '[a-z0-9_-]+');
            Route::post('theme-builder/{theme}/install-to-site', [ThemeCanvasController::class, 'installToSite'])->name('theme-builder.install-to-site');
            Route::post('theme-builder/{theme}/sync-pages', [ThemeCanvasController::class, 'syncPagesForSite'])->name('theme-builder.sync-pages');
            // ─── TB3-02: Live Iframe Canvas ───────────────────────────────────────
            Route::get('theme-builder/{theme}/canvas/live-preview', [ThemeCanvasController::class, 'livePreview'])->name('theme-builder.canvas.live-preview');
            Route::post('theme-builder/{theme}/canvas/render-block', [ThemeCanvasController::class, 'renderBlockAjax'])->name('theme-builder.canvas.render-block');
            // TB3-07: Theme Media Manager
            Route::get('theme-builder/{theme}/media', [\App\Http\Controllers\Admin\ThemeMediaController::class, 'index'])->name('theme-builder.media.index');
            Route::post('theme-builder/{theme}/media/upload', [\App\Http\Controllers\Admin\ThemeMediaController::class, 'upload'])->name('theme-builder.media.upload');
            Route::delete('theme-builder/{theme}/media', [\App\Http\Controllers\Admin\ThemeMediaController::class, 'destroy'])->name('theme-builder.media.destroy');

            // ─── TB-3: AI URL Analyzer ──────────────────────────────────────
            Route::get('theme-builder/ai-analyze',         [ThemeAiController::class, 'form'])->name('theme-builder.ai-form');
            Route::post('theme-builder/ai-analyze',        [ThemeAiController::class, 'analyze'])->name('theme-builder.ai-analyze');
            Route::post('theme-builder/ai-analyze-ajax',   [ThemeAiController::class, 'analyzeAjax'])->name('theme-builder.ai-analyze-ajax');
            Route::get('theme-builder/ai-confirm',         [ThemeAiController::class, 'confirm'])->name('theme-builder.ai-confirm');
            Route::post('theme-builder/ai-generate-prepare', function (\Illuminate\Http\Request $req) {
                $data = $req->validate(['theme_json' => 'required|array', 'name' => 'required|string|max:255']);
                $data['theme_json']['suggested_name'] = $data['name'];
                session(['tb3_pending_theme' => $data['theme_json']]);
                return response()->json(['success' => true]);
            })->name('theme-builder.ai-generate-prepare');
            Route::post('theme-builder/ai-generate',       [ThemeAiController::class, 'generate'])->name('theme-builder.ai-generate');

            // ─── TB8: AI Theme Generator (multi-input pipeline) ────────────────
            Route::get('theme-builder/ai-generator',                                [ThemeAiGeneratorController::class, 'form'])->name('theme-builder.ai-generator');
            Route::post('theme-builder/ai-generator/detect',                        [ThemeAiGeneratorController::class, 'detect'])->name('theme-builder.ai-generator.detect');
            Route::post('theme-builder/ai-generator/{job}/generate',                [ThemeAiGeneratorController::class, 'generate'])->name('theme-builder.ai-generator.generate');
            Route::get('theme-builder/ai-generator/{job}/result',                   [ThemeAiGeneratorController::class, 'result'])->name('theme-builder.ai-generator.result');
            Route::get('theme-builder/ai-generator/{job}/export',                   [ThemeAiGeneratorController::class, 'export'])->name('theme-builder.ai-generator.export');
            Route::get('theme-builder/ai-generator/{job}/install',                  [ThemeAiGeneratorController::class, 'install'])->name('theme-builder.ai-generator.install');

            // ─── TB9.5: Visual Reverse Engineering Engine ─────────────────────────
            Route::get('theme-builder/reverse-engineer',                   [\App\Http\Controllers\Admin\ThemeReverseEngineeringController::class, 'form'])->name('theme-builder.reverse-engineer');
            Route::post('theme-builder/reverse-engineer/analyze',          [\App\Http\Controllers\Admin\ThemeReverseEngineeringController::class, 'analyze'])->name('theme-builder.reverse-engineer.analyze');
            Route::get('theme-builder/reverse-engineer/{job}/result',      [\App\Http\Controllers\Admin\ThemeReverseEngineeringController::class, 'result'])->name('admin.theme-builder.reverse-engineer.result');
            Route::get('theme-builder/reverse-engineer/{job}/export',      [\App\Http\Controllers\Admin\ThemeReverseEngineeringController::class, 'export'])->name('admin.theme-builder.reverse-engineer.export');
            Route::post('theme-builder/reverse-engineer/{job}/install',    [\App\Http\Controllers\Admin\ThemeReverseEngineeringController::class, 'install'])->name('admin.theme-builder.reverse-engineer.install');
            Route::delete('theme-builder/reverse-engineer/{job}',          [\App\Http\Controllers\Admin\ThemeReverseEngineeringController::class, 'destroy'])->name('admin.theme-builder.reverse-engineer.destroy');

            // ─── JSON Import Engine ───────────────────────────────────────────────
            Route::get('theme-builder/json-import',                          [\App\Http\Controllers\Admin\ThemeJsonImportController::class, 'form'])->name('theme-builder.json-import.form');
            Route::post('theme-builder/json-import/detect',                  [\App\Http\Controllers\Admin\ThemeJsonImportController::class, 'detect'])->name('theme-builder.json-import.detect');
            Route::post('theme-builder/json-import/import',                  [\App\Http\Controllers\Admin\ThemeJsonImportController::class, 'import'])->name('theme-builder.json-import.import');
            Route::get('theme-builder/json-import/{pack}/result',            [\App\Http\Controllers\Admin\ThemeJsonImportController::class, 'result'])->name('theme-builder.json-import.result');
            Route::post('theme-builder/json-import/{pack}/install',          [\App\Http\Controllers\Admin\ThemeJsonImportController::class, 'install'])->name('theme-builder.json-import.install');
            Route::get('theme-builder/json-import/{pack}/export',            [\App\Http\Controllers\Admin\ThemeJsonImportController::class, 'export'])->name('theme-builder.json-import.export');

            // ─── ZIP Import Engine ────────────────────────────────────────────────
            Route::get('theme-builder/zip-import',                           [\App\Http\Controllers\Admin\ThemeZipImportController::class, 'form'])->name('theme-builder.zip-import.form');
            Route::post('theme-builder/zip-import/detect',                   [\App\Http\Controllers\Admin\ThemeZipImportController::class, 'detect'])->name('theme-builder.zip-import.detect');
            Route::post('theme-builder/zip-import/import',                   [\App\Http\Controllers\Admin\ThemeZipImportController::class, 'import'])->name('theme-builder.zip-import.import');
            Route::get('theme-builder/zip-import/{pack}/result',             [\App\Http\Controllers\Admin\ThemeZipImportController::class, 'result'])->name('theme-builder.zip-import.result');
            Route::post('theme-builder/zip-import/{pack}/install',           [\App\Http\Controllers\Admin\ThemeZipImportController::class, 'install'])->name('theme-builder.zip-import.install');
            Route::get('theme-builder/zip-import/{pack}/export',             [\App\Http\Controllers\Admin\ThemeZipImportController::class, 'export'])->name('theme-builder.zip-import.export');

            // ─── TB6: Template Library (Canvas) ──────────────────────────────────
            Route::get('theme-builder/{theme}/canvas/templates',        [ThemeCanvasController::class, 'canvasTemplates'])->name('theme-builder.canvas.templates');
            Route::post('theme-builder/{theme}/canvas/save-as-template',[ThemeCanvasController::class, 'canvasSaveAsTemplate'])->name('theme-builder.canvas.save-as-template');
            Route::post('theme-builder/{theme}/canvas/insert-template', [ThemeCanvasController::class, 'canvasInsertTemplate'])->name('theme-builder.canvas.insert-template');
        });

        // ─── TB6: Theme Templates Admin (CRUD + import/export) ──────────────
        Route::prefix('theme-templates')->name('theme-templates.')->group(function () {
            Route::get('/',                                     [\App\Http\Controllers\Admin\ThemeTemplateController::class, 'index'])->name('index');
            Route::get('/create',                               [\App\Http\Controllers\Admin\ThemeTemplateController::class, 'create'])->name('create');
            Route::post('/',                                    [\App\Http\Controllers\Admin\ThemeTemplateController::class, 'store'])->name('store');
            Route::get('/{themeTemplate}',                      [\App\Http\Controllers\Admin\ThemeTemplateController::class, 'show'])->name('show');
            Route::get('/{themeTemplate}/edit',                 [\App\Http\Controllers\Admin\ThemeTemplateController::class, 'edit'])->name('edit');
            Route::put('/{themeTemplate}',                      [\App\Http\Controllers\Admin\ThemeTemplateController::class, 'update'])->name('update');
            Route::delete('/{themeTemplate}',                   [\App\Http\Controllers\Admin\ThemeTemplateController::class, 'destroy'])->name('destroy');
            Route::get('/{themeTemplate}/export',               [\App\Http\Controllers\Admin\ThemeTemplateController::class, 'export'])->name('export');
            Route::post('/import',                              [\App\Http\Controllers\Admin\ThemeTemplateController::class, 'import'])->name('import');
        });

        // ─── TB6: Theme Packs Admin ──────────────────────────────────────────
        Route::prefix('theme-packs')->name('theme-packs.')->group(function () {
            Route::get('/',                                     [\App\Http\Controllers\Admin\ThemePackController::class, 'index'])->name('index');
            Route::get('/create',                               [\App\Http\Controllers\Admin\ThemePackController::class, 'create'])->name('create');
            Route::post('/',                                    [\App\Http\Controllers\Admin\ThemePackController::class, 'store'])->name('store');
            Route::get('/{themePack}',                          [\App\Http\Controllers\Admin\ThemePackController::class, 'show'])->name('show');
            Route::delete('/{themePack}',                       [\App\Http\Controllers\Admin\ThemePackController::class, 'destroy'])->name('destroy');
            Route::get('/{themePack}/export-json',              [\App\Http\Controllers\Admin\ThemePackController::class, 'exportJson'])->name('export-json');
            Route::get('/{themePack}/export-zip',               [\App\Http\Controllers\Admin\ThemePackController::class, 'exportZip'])->name('export-zip');
            Route::post('/import-json',                         [\App\Http\Controllers\Admin\ThemePackController::class, 'importJson'])->name('import-json');
            Route::post('/import-zip',                          [\App\Http\Controllers\Admin\ThemePackController::class, 'importZip'])->name('import-zip');
        });

        // ─── TB-AI Studio Import Pipeline Admin ─────────────────────────────
        Route::prefix('studio-imports')->name('studio-imports.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\Admin\ThemePackStudioAdminController::class, 'index'])->name('index');
            Route::get('/{pack}',          [\App\Http\Controllers\Admin\ThemePackStudioAdminController::class, 'show'])->name('show');
            Route::post('/{pack}/publish', [\App\Http\Controllers\Admin\ThemePackStudioAdminController::class, 'publish'])->name('publish');
            Route::delete('/{pack}',       [\App\Http\Controllers\Admin\ThemePackStudioAdminController::class, 'destroy'])->name('destroy');
        });

        // ─── Theme Marketplace (TB-2) ────────────────────────────────────────
        Route::prefix('theme-marketplace')->name('theme-marketplace.')->group(function () {
            Route::get('/',                                         [ThemeMarketplaceAdminController::class, 'index'])->name('index');
            Route::get('/scoring',                                  [ThemeMarketplaceAdminController::class, 'scoring'])->name('scoring');
            Route::get('/{theme}/preview',                          [ThemeMarketplaceAdminController::class, 'preview'])->name('preview');
            Route::post('/{theme}/demo-manifest',                   [ThemeMarketplaceAdminController::class, 'buildDemoManifest'])->name('demo-manifest');
            Route::post('/{theme}/score',                           [ThemeMarketplaceAdminController::class, 'scoreTheme'])->name('score-theme');
            Route::post('/{theme}/record-install',                  [ThemeMarketplaceAdminController::class, 'recordInstall'])->name('record-install');
            Route::put('/{theme}/listing',                          [ThemeMarketplaceAdminController::class, 'updateListing'])->name('update-listing');
            Route::post('/{theme}/featured',                        [ThemeMarketplaceAdminController::class, 'toggleFeatured'])->name('featured');
            Route::get('/licenses',                                 [ThemeMarketplaceAdminController::class, 'licenses'])->name('licenses');
            Route::delete('/licenses/{license}',                    [ThemeMarketplaceAdminController::class, 'revokeLicense'])->name('revoke-license');
            Route::post('/licenses/grant',                          [ThemeMarketplaceAdminController::class, 'grantLicense'])->name('grant-license');
        });

        // ─── Theme Bundles (GAP-010) ─────────────────────────────────────────
        Route::prefix('theme-bundles')->name('theme-bundles.')->group(function () {
            Route::get('/',                             [\App\Http\Controllers\Admin\ThemeBundleAdminController::class, 'index'])->name('index');
            Route::get('/create',                       [\App\Http\Controllers\Admin\ThemeBundleAdminController::class, 'create'])->name('create');
            Route::post('/',                            [\App\Http\Controllers\Admin\ThemeBundleAdminController::class, 'store'])->name('store');
            Route::get('/orders',                       [\App\Http\Controllers\Admin\ThemeBundleAdminController::class, 'orders'])->name('orders');
            Route::get('/{themeBundle}/edit',           [\App\Http\Controllers\Admin\ThemeBundleAdminController::class, 'edit'])->name('edit');
            Route::put('/{themeBundle}',                [\App\Http\Controllers\Admin\ThemeBundleAdminController::class, 'update'])->name('update');
            Route::delete('/{themeBundle}',             [\App\Http\Controllers\Admin\ThemeBundleAdminController::class, 'destroy'])->name('destroy');
        });

        /*
        |--------------------------------------------------------------------------
        | Theme Manager (Enterprise Theme Engine — Phase UI-THEME.1)
        |--------------------------------------------------------------------------
        */
        Route::prefix('theme-manager')->name('theme-manager.')->group(function () {
            Route::get('/',                                                   [\App\Http\Controllers\Admin\ThemeManagerController::class, 'index'])->name('index');
            Route::get('/create',                                             [\App\Http\Controllers\Admin\ThemeManagerController::class, 'create'])->name('create');
            Route::post('/',                                                  [\App\Http\Controllers\Admin\ThemeManagerController::class, 'store'])->name('store');
            Route::get('/{themeProfile}/edit',                                [\App\Http\Controllers\Admin\ThemeManagerController::class, 'edit'])->name('edit');
            Route::put('/{themeProfile}',                                     [\App\Http\Controllers\Admin\ThemeManagerController::class, 'update'])->name('update');
            Route::delete('/{themeProfile}',                                  [\App\Http\Controllers\Admin\ThemeManagerController::class, 'destroy'])->name('destroy');
            Route::post('/{themeProfile}/activate',                           [\App\Http\Controllers\Admin\ThemeManagerController::class, 'activate'])->name('activate');
            Route::post('/{themeProfile}/clone',                              [\App\Http\Controllers\Admin\ThemeManagerController::class, 'clone'])->name('clone');
            Route::post('/{themeProfile}/assign-to-sites',                    [\App\Http\Controllers\Admin\ThemeManagerController::class, 'assignToSites'])->name('assign-to-sites');
            Route::post('/reset-default',                                     [\App\Http\Controllers\Admin\ThemeManagerController::class, 'resetDefault'])->name('reset-default');
            Route::get('/{themeProfile}/preview-tokens',                      [\App\Http\Controllers\Admin\ThemeManagerController::class, 'previewTokens'])->name('preview-tokens');
        });

        Route::get('guide-search', [GuideSearchController::class, 'index'])->name('guide-search');

        /*
        |--------------------------------------------------------------------------
        | دفتر کل مالی — Finance Ledger
        |--------------------------------------------------------------------------
        */
        Route::prefix('finance')->name('finance-ledger.')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\Admin\FinanceLedgerController::class, 'dashboard'])->name('dashboard');
            Route::get('/ledger',         [\App\Http\Controllers\Admin\FinanceLedgerController::class, 'index'])->name('index');
            Route::get('/ledger/create',  [\App\Http\Controllers\Admin\FinanceLedgerController::class, 'create'])->name('create');
            Route::post('/ledger',        [\App\Http\Controllers\Admin\FinanceLedgerController::class, 'store'])->name('store');
            Route::get('/ledger/{financeLedgerEntry}/edit',    [\App\Http\Controllers\Admin\FinanceLedgerController::class, 'edit'])->name('edit');
            Route::put('/ledger/{financeLedgerEntry}',         [\App\Http\Controllers\Admin\FinanceLedgerController::class, 'update'])->name('update');
            Route::delete('/ledger/{financeLedgerEntry}',      [\App\Http\Controllers\Admin\FinanceLedgerController::class, 'destroy'])->name('destroy');
            Route::get('/export', [\App\Http\Controllers\Admin\FinanceLedgerController::class, 'export'])->name('export');
        });

        /*
        |--------------------------------------------------------------------------
        | مدیریت زیرساخت — Infrastructure Management
        |--------------------------------------------------------------------------
        */
        Route::prefix('infrastructure')->name('infrastructure.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\Infrastructure\InfrastructureOverviewController::class, 'index'])->name('overview');

            Route::resource('servers', \App\Http\Controllers\Admin\Infrastructure\InfraServerController::class)
                ->except(['show']);

            Route::resource('domains', \App\Http\Controllers\Admin\Infrastructure\InfraDomainController::class)
                ->except(['show']);

            Route::resource('hosting', \App\Http\Controllers\Admin\Infrastructure\InfraHostingController::class)
                ->except(['show']);

            Route::resource('storage', \App\Http\Controllers\Admin\Infrastructure\InfraStorageController::class)
                ->except(['show'])
                ->parameters(['storage' => 'storage']);

            Route::resource('databases', \App\Http\Controllers\Admin\Infrastructure\InfraDatabaseController::class)
                ->except(['show'])
                ->parameters(['databases' => 'database']);

            // INFRA-5: SMS delivery statistics
            Route::get('sms', [\App\Http\Controllers\Admin\Infrastructure\InfraSmsController::class, 'index'])->name('sms');

            // INFRA-6: Remote Agent Layer
            Route::resource('remote-agents', \App\Http\Controllers\Admin\Infrastructure\RemoteAgentController::class)
                ->parameters(['remote-agents' => 'remoteAgent']);
            Route::get('remote-agents/{remoteAgent}/health',  [\App\Http\Controllers\Admin\Infrastructure\RemoteAgentController::class, 'checkHealth'])->name('remote-agents.health');
            Route::post('remote-agents/{remoteAgent}/backup', [\App\Http\Controllers\Admin\Infrastructure\RemoteAgentController::class, 'triggerBackup'])->name('remote-agents.backup');
            Route::post('remote-agents/{remoteAgent}/deploy', [\App\Http\Controllers\Admin\Infrastructure\RemoteAgentController::class, 'triggerDeploy'])->name('remote-agents.deploy');
            Route::post('remote-agents/{remoteAgent}/restart',[\App\Http\Controllers\Admin\Infrastructure\RemoteAgentController::class, 'triggerRestart'])->name('remote-agents.restart');
            Route::get('remote-agents/generate-secret',       [\App\Http\Controllers\Admin\Infrastructure\RemoteAgentController::class, 'generateSecret'])->name('remote-agents.generate-secret');

            // INFRA-MONITORING-01: Central Monitoring & Health Center
            Route::prefix('monitoring')->name('monitoring.')->group(function () {
                Route::get('/',         [\App\Http\Controllers\Admin\Infrastructure\MonitoringController::class, 'index'])->name('index');
                Route::get('{siteId}',  [\App\Http\Controllers\Admin\Infrastructure\MonitoringController::class, 'show'])->name('site');
            });

            // PHASE-003B — Customer Deployment Center
            Route::prefix('customer-deployment')->name('customer-deployment.')->group(function () {
                // Dashboard
                Route::get('/', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeploymentController::class, 'dashboard'])->name('dashboard');

                // Installation Wizard + Start
                Route::get('/wizard', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeploymentController::class, 'wizard'])->name('wizard');
                Route::post('/start', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeploymentController::class, 'start'])->name('start');

                // Installation History
                Route::get('/history', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeploymentController::class, 'history'])->name('history');

                // Release Packages
                Route::get('/packages', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeploymentController::class, 'packages'])->name('packages');

                // Installation Detail + Rollback + Delete (legacy quick-delete preserved)
                Route::get('/installations/{installation}', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeploymentController::class, 'show'])->name('installations.show');
                // Phase 13-O9e — Secure Reveal endpoint; permission (tenant.credentials.view) is enforced inside the controller.
                Route::get('/installations/{installation}/credential', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeploymentController::class, 'revealCredential'])->name('installations.credential');
                Route::post('/installations/{installation}/rollback', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeploymentController::class, 'rollback'])->name('installations.rollback');
                Route::delete('/installations/{installation}', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeploymentController::class, 'destroy'])->name('installations.destroy');
                Route::delete('/installations/{installation}/force', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeploymentController::class, 'forceDestroy'])->name('installations.force-destroy');

                // SSE Progress Stream
                Route::get('/installations/{installation}/progress', [\App\Http\Controllers\Admin\Infrastructure\CustomerInstallationProgressController::class, 'stream'])->name('installations.progress');

                // PHASE-003H — Enterprise Delete Engine
                Route::get('/installations/{installation}/delete', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeleteController::class, 'confirm'])->name('installations.delete.confirm');
                Route::post('/installations/{installation}/delete', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeleteController::class, 'initiate'])->name('installations.delete.initiate');
                Route::get('/deletions/{deletion}', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeleteController::class, 'progress'])->name('deletions.progress');
                Route::post('/deletions/{deletion}/retry', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeleteController::class, 'retry'])->name('deletions.retry');
                Route::get('/deletions/{deletion}/stream', [\App\Http\Controllers\Admin\Infrastructure\CustomerDeleteController::class, 'stream'])->name('deletions.stream');

                // Remote Server Management
                Route::prefix('servers')->name('servers.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\Infrastructure\CustomerRemoteServerAdminController::class, 'index'])->name('index');
                    Route::get('/create', [\App\Http\Controllers\Admin\Infrastructure\CustomerRemoteServerAdminController::class, 'create'])->name('create');
                    Route::post('/', [\App\Http\Controllers\Admin\Infrastructure\CustomerRemoteServerAdminController::class, 'store'])->name('store');
                    Route::post('/generate-ssh-key', [\App\Http\Controllers\Admin\Infrastructure\CustomerRemoteServerAdminController::class, 'generateSshKeyPair'])->name('generate-ssh-key');
                    Route::get('/{server}/edit', [\App\Http\Controllers\Admin\Infrastructure\CustomerRemoteServerAdminController::class, 'edit'])->name('edit');
                    Route::put('/{server}', [\App\Http\Controllers\Admin\Infrastructure\CustomerRemoteServerAdminController::class, 'update'])->name('update');
                    Route::delete('/{server}', [\App\Http\Controllers\Admin\Infrastructure\CustomerRemoteServerAdminController::class, 'destroy'])->name('destroy');
                    Route::post('/{server}/test', [\App\Http\Controllers\Admin\Infrastructure\CustomerRemoteServerAdminController::class, 'testConnection'])->name('test');
                });

                // PHASE-003D6 — Authentication Center
                Route::prefix('auth-center')->name('auth-center.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'index'])->name('index');

                    // SSH Keys
                    Route::prefix('ssh-keys')->name('ssh-keys.')->group(function () {
                        Route::get('/', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'sshKeys'])->name('index');
                        Route::get('/generate', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'generateKeyForm'])->name('generate');
                        Route::post('/generate', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'generateKey'])->name('generate.store');
                        Route::get('/import', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'importKeyForm'])->name('import');
                        Route::post('/import', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'importKey'])->name('import.store');
                        Route::get('/{key}', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'showKey'])->name('show');
                        Route::post('/{key}/install', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'installKey'])->name('install');
                        Route::post('/{key}/test', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'testKey'])->name('test');
                        Route::post('/{key}/activate', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'activateKey'])->name('activate');
                        Route::post('/{key}/rotate', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'rotateKey'])->name('rotate');
                        Route::post('/{key}/revoke', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'revokeKey'])->name('revoke');
                        Route::delete('/{key}', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'deleteKey'])->name('delete');
                    });

                    // API Tokens
                    Route::get('/api-tokens', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'apiTokens'])->name('api-tokens');
                    Route::post('/api-tokens/{server}', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'storeApiToken'])->name('api-tokens.store');
                    Route::post('/api-tokens/{server}/rotate', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'rotateApiToken'])->name('api-tokens.rotate');
                    Route::delete('/api-tokens/{server}', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'deleteApiToken'])->name('api-tokens.delete');

                    // Security Audit
                    Route::get('/security-audit', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'securityAudit'])->name('security-audit');

                    // Migration Wizard
                    Route::get('/migration-wizard/{server}', [\App\Http\Controllers\Admin\Infrastructure\AuthenticationCenterController::class, 'migrationWizard'])->name('migration-wizard');
                });
            });
        });

        /*
        |--------------------------------------------------------------------------
        | Incident Management
        |--------------------------------------------------------------------------
        */
        Route::prefix('incidents')->name('incidents.')->group(function () {
            Route::get('/',                                  [\App\Http\Controllers\Admin\IncidentController::class, 'index'])->name('index');
            Route::get('{id}',                               [\App\Http\Controllers\Admin\IncidentController::class, 'show'])->name('show');
            Route::post('{id}/investigate',                  [\App\Http\Controllers\Admin\IncidentController::class, 'investigate'])->name('investigate');
            Route::post('{id}/resolve',                      [\App\Http\Controllers\Admin\IncidentController::class, 'resolve'])->name('resolve');
            Route::post('{id}/close',                        [\App\Http\Controllers\Admin\IncidentController::class, 'close'])->name('close');
            Route::post('{id}/notes',                        [\App\Http\Controllers\Admin\IncidentController::class, 'addNote'])->name('notes.store');
            Route::delete('{incidentId}/notes/{noteId}',     [\App\Http\Controllers\Admin\IncidentController::class, 'deleteNote'])->name('notes.delete');
        });

        /*
        |--------------------------------------------------------------------------
        | Monitoring Admin API (JSON — no blade, inside admin middleware)
        |--------------------------------------------------------------------------
        */
        Route::prefix('api/monitoring')->name('api.monitoring.')->group(function () {
            Route::get('summary',                 [\App\Http\Controllers\Admin\Infrastructure\MonitoringController::class, 'summary'])->name('summary');
            Route::get('sites',                   [\App\Http\Controllers\Admin\Infrastructure\MonitoringController::class, 'sites'])->name('sites');
            Route::get('incidents',               [\App\Http\Controllers\Admin\Infrastructure\MonitoringController::class, 'incidents'])->name('incidents');
            Route::post('incidents/{id}/resolve', [\App\Http\Controllers\Admin\Infrastructure\MonitoringController::class, 'resolveIncident'])->name('incidents.resolve');
            Route::get('platform-history',        [\App\Http\Controllers\Admin\Infrastructure\MonitoringController::class, 'platformHistory'])->name('platform-history');
        });

        /*
        |--------------------------------------------------------------------------
        | Module Adoption Overview
        |--------------------------------------------------------------------------
        */
        Route::get('module-adoption', [\App\Http\Controllers\Admin\ModuleAdoptionController::class, 'index'])->name('module-adoption.index');

        /*
        |--------------------------------------------------------------------------
        | Notification Center
        |--------------------------------------------------------------------------
        */
        Route::prefix('notification-center')->name('notification-center.')->group(function () {
            Route::get('/',          [\App\Http\Controllers\Admin\Notification\NotificationCenterController::class, 'index'])->name('index');
            Route::get('/create',    [\App\Http\Controllers\Admin\Notification\NotificationCenterController::class, 'create'])->name('create');
            Route::post('/',         [\App\Http\Controllers\Admin\Notification\NotificationCenterController::class, 'store'])->name('store');
            Route::post('/{id}/read',[\App\Http\Controllers\Admin\Notification\NotificationCenterController::class, 'markRead'])->name('read');
            Route::post('/read-all', [\App\Http\Controllers\Admin\Notification\NotificationCenterController::class, 'markAllRead'])->name('read-all');
            Route::delete('/{id}',   [\App\Http\Controllers\Admin\Notification\NotificationCenterController::class, 'destroy'])->name('destroy');
            Route::get('/stats',     [\App\Http\Controllers\Admin\Notification\NotificationCenterController::class, 'stats'])->name('stats');
        });

        /*
        |--------------------------------------------------------------------------
        | Disaster Recovery — Restore Wizard (Phase 1)
        |--------------------------------------------------------------------------
        */
        Route::prefix('disaster-recovery')->name('disaster-recovery.')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\Admin\RestoreWizardController::class, 'index'])->name('index');
            Route::get('/browse',              [\App\Http\Controllers\Admin\RestoreWizardController::class, 'browse'])->name('browse');
            Route::post('/validate',           [\App\Http\Controllers\Admin\RestoreWizardController::class, 'checkBackup'])->name('validate');
            Route::post('/dry-run',            [\App\Http\Controllers\Admin\RestoreWizardController::class, 'dryRun'])->name('dry-run');
            Route::post('/execute',            [\App\Http\Controllers\Admin\RestoreWizardController::class, 'execute'])->name('execute');
            Route::get('/stream',              [\App\Http\Controllers\Admin\RestoreWizardController::class, 'stream'])->name('stream');
            Route::get('/status',              [\App\Http\Controllers\Admin\RestoreWizardController::class, 'status'])->name('status');
            Route::post('/abort',              [\App\Http\Controllers\Admin\RestoreWizardController::class, 'abort'])->name('abort');
            Route::get('/logs',                [\App\Http\Controllers\Admin\RestoreWizardController::class, 'logs'])->name('logs');
            Route::get('/logs/{log}',          [\App\Http\Controllers\Admin\RestoreWizardController::class, 'logShow'])->name('logs.show');

            // Phase 2: DR Center (read-only monitoring dashboard)
            Route::prefix('center')->name('center.')->group(function () {
                Route::get('/',                        [\App\Http\Controllers\Admin\DrCenterController::class, 'index'])->name('index');
                Route::get('/tenant/{site}',           [\App\Http\Controllers\Admin\DrCenterController::class, 'tenantHealth'])->name('tenant');
                Route::get('/timeline',                [\App\Http\Controllers\Admin\DrCenterController::class, 'backupTimeline'])->name('timeline');
                Route::post('/integrity',              [\App\Http\Controllers\Admin\DrCenterController::class, 'integrityCheck'])->name('integrity');
                Route::get('/alerts',                  [\App\Http\Controllers\Admin\DrCenterController::class, 'alertHistory'])->name('alerts');
                Route::get('/alert-feed',              [\App\Http\Controllers\Admin\DrCenterController::class, 'alertFeed'])->name('alert-feed');
                Route::get('/tenant-health-ajax',      [\App\Http\Controllers\Admin\DrCenterController::class, 'tenantHealthAjax'])->name('tenant-health-ajax');
                // Phase 2 additions: Simulation History + Run Simulation
                Route::get('/simulations',             [\App\Http\Controllers\Admin\DrCenterController::class, 'simulationIndex'])->name('simulations');
                Route::post('/simulations/run',        [\App\Http\Controllers\Admin\DrCenterController::class, 'runSimulation'])->name('simulations.run');
            });
        });

        /*
        |--------------------------------------------------------------------------
        | DNS Provider Settings (Arvan DNS)
        |--------------------------------------------------------------------------
        */
        Route::prefix('infrastructure/dns-provider')->name('infrastructure.dns-provider.')->group(function () {
            Route::get('/',          [\App\Http\Controllers\Admin\Infrastructure\DnsProviderController::class, 'index'])->name('index');
            Route::post('/save',     [\App\Http\Controllers\Admin\Infrastructure\DnsProviderController::class, 'save'])->name('save');
            Route::post('/health',   [\App\Http\Controllers\Admin\Infrastructure\DnsProviderController::class, 'healthCheck'])->name('health');
            Route::get('/records',   [\App\Http\Controllers\Admin\Infrastructure\DnsProviderController::class, 'listRecords'])->name('records');
        });

        /*
        |--------------------------------------------------------------------------
        | Local Recovery Repository
        |--------------------------------------------------------------------------
        */
        Route::prefix('infrastructure/recovery-repository')->name('infrastructure.recovery-repository.')->group(function () {
            Route::get('/',            [\App\Http\Controllers\Admin\RecoveryRepositoryController::class, 'index'])->name('index');
            Route::post('/save',       [\App\Http\Controllers\Admin\RecoveryRepositoryController::class, 'save'])->name('save');
            Route::post('/validate-path', [\App\Http\Controllers\Admin\RecoveryRepositoryController::class, 'validatePath'])->name('validate-path');
            Route::post('/verify-all', [\App\Http\Controllers\Admin\RecoveryRepositoryController::class, 'verifyAll'])->name('verify-all');
            Route::post('/verify/{log}',  [\App\Http\Controllers\Admin\RecoveryRepositoryController::class, 'verify'])->name('verify');
            Route::delete('/{log}',    [\App\Http\Controllers\Admin\RecoveryRepositoryController::class, 'destroy'])->name('destroy');
            Route::get('/download/platform', [\App\Http\Controllers\Admin\RecoveryRepositoryController::class, 'downloadPlatformPackage'])->name('download.platform');
            Route::get('/download/full',     [\App\Http\Controllers\Admin\RecoveryRepositoryController::class, 'downloadFullPackage'])->name('download.full');
        });

        /*
        |--------------------------------------------------------------------------
        | Installer Admin Panel (read-only — shows baseline, manifest, verification)
        |--------------------------------------------------------------------------
        */
        Route::prefix('installer')->name('installer.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\Admin\InstallerAdminController::class, 'index'])->name('index');
            Route::post('/verify',         [\App\Http\Controllers\Admin\InstallerAdminController::class, 'verify'])->name('verify');
            Route::get('/report/{format}', [\App\Http\Controllers\Admin\InstallerAdminController::class, 'downloadReport'])->name('report')->where('format', 'html|pdf');
            Route::get('/manifest',        [\App\Http\Controllers\Admin\InstallerAdminController::class, 'downloadManifest'])->name('manifest');
            Route::get('/baseline-meta',   [\App\Http\Controllers\Admin\InstallerAdminController::class, 'downloadBaselineMeta'])->name('baseline-meta');
        });

        /*
        |--------------------------------------------------------------------------
        | ریدایرکت /admin به داشبورد
        |--------------------------------------------------------------------------
        */

        Route::get('/', function () {
            return redirect()->route('admin.dashboard');
        });

        /*
        |--------------------------------------------------------------------------
        | Design System — Icon Gallery
        |--------------------------------------------------------------------------
        */
        Route::get('/design/icons', function () {
            return view('admin.design-system.icons', [
                'icons' => \SaaSPlatform\CoreUtils\Icons\IconRegistry::all(),
            ]);
        })->name('design.icons');

        /*
        |--------------------------------------------------------------------------
        | Phase 7 — Motion Graphics Admin Management & Internal Site Activation
        | (docs/motion-admin-management/). Platform-Admin-only, internal
        | testing tool — no customer-facing route exists anywhere for this.
        |--------------------------------------------------------------------------
        */
        Route::prefix('motion-graphics')->name('motion-graphics.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\MotionGraphicsAdminController::class, 'selectSite'])->name('select-site');
            // ADR-086 — central catalog: availability switch + private-motion
            // grants. Registered before the `{site}/...` routes.
            Route::get('catalog', [\App\Http\Controllers\Admin\MotionCatalogAdminController::class, 'index'])->name('catalog.index');
            Route::post('catalog/{motion}/availability', [\App\Http\Controllers\Admin\MotionCatalogAdminController::class, 'availability'])->whereNumber('motion')->name('catalog.availability');
            Route::post('catalog/{motion}/grants', [\App\Http\Controllers\Admin\MotionCatalogAdminController::class, 'grant'])->whereNumber('motion')->name('catalog.grants.store');
            Route::post('catalog/grants/{grant}/revoke', [\App\Http\Controllers\Admin\MotionCatalogAdminController::class, 'revoke'])->whereNumber('grant')->name('catalog.grants.revoke');
            Route::get('{site}/dashboard', [\App\Http\Controllers\Admin\MotionGraphicsAdminController::class, 'dashboard'])->name('dashboard');
            Route::get('{site}/templates', [\App\Http\Controllers\Admin\MotionGraphicsAdminController::class, 'templates'])->name('templates');
            Route::post('{site}/templates/preview', [\App\Http\Controllers\Admin\MotionGraphicsAdminController::class, 'previewCompatibility'])->name('templates.preview');
            Route::get('{site}/assets/preview', [\App\Http\Controllers\Admin\MotionGraphicsAdminController::class, 'previewAsset'])->name('assets.preview');
            Route::get('{site}/assets/browse', [\App\Http\Controllers\Admin\MotionGraphicsAdminController::class, 'browseAssets'])->name('assets.browse');
            Route::post('{site}/templates/{template}/assign', [\App\Http\Controllers\Admin\MotionGraphicsAdminController::class, 'assignPackage'])->name('templates.assign');
            Route::post('{site}/templates/{template}/activate', [\App\Http\Controllers\Admin\MotionGraphicsAdminController::class, 'activateSite'])->name('templates.activate');
            Route::get('{site}/preview', [\App\Http\Controllers\Admin\MotionGraphicsAdminController::class, 'preview'])->name('preview');
            Route::get('{site}/preview/{sitePackage}', [\App\Http\Controllers\Admin\MotionGraphicsAdminController::class, 'previewProjection'])->name('preview.show');
        });

        /*
        |--------------------------------------------------------------------------
        | Phase X — Bespoke/Custom Motion Commercialization
        | (docs/motion-custom-assets/). Platform-Admin-only. Never
        | auto-activates a customer; assignment is always a separate action.
        |--------------------------------------------------------------------------
        */
        Route::prefix('motion-graphics/{site}/bespoke')->name('motion-graphics.bespoke.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\MotionBespokeAdminController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\MotionBespokeAdminController::class, 'store'])->name('store');
            Route::post('{commission}/transition', [\App\Http\Controllers\Admin\MotionBespokeAdminController::class, 'transition'])->name('transition');
            Route::post('{commission}/attach-asset', [\App\Http\Controllers\Admin\MotionBespokeAdminController::class, 'attachAsset'])->name('attach-asset');
            Route::post('{commission}/link-product', [\App\Http\Controllers\Admin\MotionBespokeAdminController::class, 'linkProduct'])->name('link-product');
            Route::post('{commission}/assign', [\App\Http\Controllers\Admin\MotionBespokeAdminController::class, 'assign'])->name('assign');
            Route::post('{commission}/rollback', [\App\Http\Controllers\Admin\MotionBespokeAdminController::class, 'rollback'])->name('rollback');
            Route::post('{commission}/revoke', [\App\Http\Controllers\Admin\MotionBespokeAdminController::class, 'revoke'])->name('revoke');
        });
    });
