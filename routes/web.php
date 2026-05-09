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

use App\Http\Controllers\Admin\FormController;
use App\Http\Controllers\Admin\FormSiteController;
use App\Http\Controllers\Admin\ProfileFieldController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\SiteModuleController;
use App\Http\Controllers\Admin\MenuController;
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
use App\Http\Controllers\Admin\FormSubmissionController;
use App\Http\Controllers\Admin\PlatformSettingsController;
use App\Http\Controllers\Admin\GuideSearchController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ResellerController as AdminResellerController;
use App\Http\Controllers\Admin\ResellerCommissionController as AdminResellerCommissionController;
use App\Http\Controllers\Admin\PayoutRequestController as AdminPayoutRequestController;
use App\Http\Controllers\Admin\SupportPlanController as AdminSupportPlanController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\ThemeBuilderController;
use App\Http\Controllers\PageController;

use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Controllers\Site\FormFrontendController;
use App\Http\Controllers\Customer\CustomerFormController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\CheckoutController;

/*
|--------------------------------------------------------------------------
| صفحه اصلی
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => view('welcome'))->name('home');

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

    // فروشگاه و پرداخت (خرید لایسنس، ماژول، پشتیبانی)
    Route::get('/store', [StoreController::class, 'index'])->name('store.index');
    Route::get('/store/modules', [StoreController::class, 'modules'])->name('store.modules');
    Route::get('/store/cart', [StoreController::class, 'cart'])->name('store.cart');
    Route::post('/store/cart/add', [StoreController::class, 'addToCart'])->name('store.cart.add');
    Route::get('/store/cart/remove/{key}', [StoreController::class, 'removeFromCart'])->name('store.cart.remove');
    Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
    Route::get('/checkout/callback/{invoice}', [CheckoutController::class, 'callback'])->name('checkout.callback');

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

        /*
        |--------------------------------------------------------------------------
        | مدیریت ماژول‌ها، لایسنس‌ها و بروزرسانی‌ها
        |--------------------------------------------------------------------------
        */
        Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::get('module-releases', [ModuleController::class, 'releasesIndex'])->name('module-releases.index');
        Route::post('modules/bulk-update', [ModuleController::class, 'bulkUpdate'])->name('modules.bulk-update');
        Route::get('modules/{module}/releases', [ModuleController::class, 'releases'])->name('modules.releases');
        Route::post('modules/{module}/releases', [ModuleController::class, 'storeRelease'])->name('modules.releases.store');
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
        | مدیریت فرم‌ها
        |--------------------------------------------------------------------------
        */

        Route::get('forms/submissions', [FormSubmissionController::class, 'index'])->name('forms.submissions.index');
        Route::get('forms/submissions/export', [FormSubmissionController::class, 'export'])->name('forms.submissions.export');
        Route::post('forms/submissions/column-order', [FormSubmissionController::class, 'updateColumnOrder'])->name('forms.submissions.column-order');

        Route::resource('forms', FormController::class);

        Route::get('forms/{form}/preview', [FormController::class, 'preview'])
            ->name('forms.preview');

        // افزودن فیلد به فرم
        Route::post('forms/{form}/fields', [FormController::class, 'storeField'])
            ->name('forms.fields.store');

        // حذف فیلد
        Route::delete('forms/fields/{field}', [FormController::class, 'destroyField'])
            ->name('forms.fields.destroy');

        // فعال / غیرفعال کردن فیلد
        Route::patch('forms/fields/{field}/toggle', [FormController::class, 'toggleField'])
            ->name('forms.fields.toggle');

        // مرتب‌سازی فیلدها (Drag & Drop)
        Route::post('forms/{form}/fields/reorder', [FormController::class, 'sortFields'])
            ->name('forms.fields.reorder');

        // اتصال فرم به سایت‌ها و فعال/غیرفعال
        Route::get('forms/{form}/sites', [FormSiteController::class, 'edit'])
            ->name('forms.sites.edit');
        Route::put('forms/{form}/sites', [FormSiteController::class, 'update'])
            ->name('forms.sites.update');
        Route::patch('forms/{form}/sites/{site}/toggle', [FormSiteController::class, 'toggleSite'])
            ->name('forms.sites.toggle');


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
        Route::patch('users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');

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

        Route::resource('customers', CustomerController::class);


        /*
        |--------------------------------------------------------------------------
        | مدیریت سایت‌ها
        |--------------------------------------------------------------------------
        */

        Route::resource('sites', SiteController::class);
        Route::get('sites/{site}/modules', [SiteModuleController::class, 'edit'])->name('sites.modules.edit');
        Route::put('sites/{site}/modules', [SiteModuleController::class, 'update'])->name('sites.modules.update');
        Route::post('sites/{site}/modules/toggle', [SiteModuleController::class, 'toggle'])->name('sites.modules.toggle');
        Route::get('sites/{site}/menus', [MenuController::class, 'index'])->name('sites.menus.index');
        Route::get('sites/{site}/menus/create', [MenuController::class, 'create'])->name('sites.menus.create');
        Route::post('sites/{site}/menus', [MenuController::class, 'store'])->name('sites.menus.store');
        Route::get('sites/{site}/menus/{menu}/edit', [MenuController::class, 'edit'])->name('sites.menus.edit');
        Route::put('sites/{site}/menus/{menu}', [MenuController::class, 'update'])->name('sites.menus.update');
        Route::delete('sites/{site}/menus/{menu}', [MenuController::class, 'destroy'])->name('sites.menus.destroy');
        Route::post('sites/{site}/menus/{menu}/items', [MenuController::class, 'storeItem'])->name('sites.menus.items.store');
        Route::put('sites/{site}/menus/{menu}/items/{item}', [MenuController::class, 'updateItem'])->name('sites.menus.items.update');
        Route::delete('sites/{site}/menus/{menu}/items/{item}', [MenuController::class, 'destroyItem'])->name('sites.menus.items.destroy');
        Route::post('sites/{site}/menus/{menu}/reorder', [MenuController::class, 'reorderItems'])->name('sites.menus.reorder');
        Route::get('sites/{site}/seo', [SeoController::class, 'index'])->name('sites.seo.index');
        Route::post('sites/{site}/seo/redirects', [SeoController::class, 'storeRedirect'])->name('sites.seo.redirects.store');
        Route::put('sites/{site}/seo/redirects/{redirect}', [SeoController::class, 'updateRedirect'])->name('sites.seo.redirects.update');
        Route::delete('sites/{site}/seo/redirects/{redirect}', [SeoController::class, 'destroyRedirect'])->name('sites.seo.redirects.destroy');
        Route::get('sites/{site}/updates', [SiteUpdateController::class, 'index'])->name('sites.updates.index');
        Route::post('sites/{site}/updates', [SiteUpdateController::class, 'store'])->name('sites.updates.store');
        Route::get('sites/{site}/license', [SiteLicenseController::class, 'create'])->name('sites.license.create');
        Route::post('sites/{site}/license', [SiteLicenseController::class, 'store'])->name('sites.license.store');
        Route::get('sites/{site}/license/show', [SiteLicenseController::class, 'show'])->name('sites.license.show');
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
        Route::resource('payment-gateways', PaymentGatewayController::class)->except('show');
        Route::get('support-tickets', [AdminSupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('support-tickets/create', [AdminSupportTicketController::class, 'create'])->name('support-tickets.create');
        Route::post('support-tickets', [AdminSupportTicketController::class, 'store'])->name('support-tickets.store');
        Route::get('support-tickets/{support_ticket}', [AdminSupportTicketController::class, 'show'])->name('support-tickets.show');
        Route::patch('support-tickets/{support_ticket}/status', [AdminSupportTicketController::class, 'updateStatus'])->name('support-tickets.update-status');
        Route::post('support-tickets/{support_ticket}/replies', [AdminSupportTicketController::class, 'storeReply'])->name('support-tickets.replies.store');
        Route::patch('support-tickets/{support_ticket}/assign', [AdminSupportTicketController::class, 'assign'])->name('support-tickets.assign');

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
            Route::get('theme-builder/{theme_builder}/export', [ThemeBuilderController::class, 'export'])->name('theme-builder.export');
            Route::get('theme-builder/{theme_builder}/preview', [ThemeBuilderController::class, 'preview'])->name('theme-builder.preview');
            Route::get('theme-builder/{theme_builder}/clone', function (\App\Models\Theme $theme_builder) {
                return view('admin.theme-builder.clone', ['theme' => $theme_builder]);
            })->name('theme-builder.clone-form');
            Route::post('theme-builder/{theme_builder}/clone', [ThemeBuilderController::class, 'cloneTheme'])->name('theme-builder.clone');
            Route::post('theme-revisions/{revision}/restore', [ThemeBuilderController::class, 'restoreRevision'])->name('theme-builder.restore-revision');
        });

        Route::get('guide-search', [GuideSearchController::class, 'index'])->name('guide-search');

        /*
        |--------------------------------------------------------------------------
        | ریدایرکت /admin به داشبورد
        |--------------------------------------------------------------------------
        */

        Route::get('/', function () {
            return redirect()->route('admin.dashboard');
        });
    });
