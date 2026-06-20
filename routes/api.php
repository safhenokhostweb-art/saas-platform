<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LicenseController as ApiLicenseController;
use App\Http\Controllers\Api\UpdateController as ApiUpdateController;
use App\Http\Controllers\Api\SiteConfigController as ApiSiteConfigController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Api\SupportTicketController as ApiSupportTicketController;
use App\Http\Controllers\Api\TenantTicketController as ApiTenantTicketController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\GroupApiController;
use App\Http\Controllers\Api\TagApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| License Server API (برای customer-site)
|--------------------------------------------------------------------------
*/
Route::prefix('license')->middleware('throttle:60,1')->group(function () {
    Route::post('validate', [ApiLicenseController::class, 'validateLicense'])->name('api.license.validate');
});

Route::prefix('updates')->middleware('throttle:30,1')->group(function () {
    Route::get('check', [ApiUpdateController::class, 'check'])->name('api.updates.check');
    Route::get('modules', [ApiUpdateController::class, 'checkModules'])->name('api.updates.modules');
    Route::get('module-history', [ApiUpdateController::class, 'moduleHistory'])->name('api.updates.module-history');
    Route::post('report-versions', [ApiUpdateController::class, 'reportVersions'])->name('api.updates.report-versions');
});

Route::prefix('site-config')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [ApiSiteConfigController::class, 'show'])->name('api.site-config.show');
});

/*
|--------------------------------------------------------------------------
| Products API (برای نمایش در سایت مالک)
|--------------------------------------------------------------------------
*/
Route::prefix('products')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [ApiProductController::class, 'index'])->name('api.products.index');
});

/*
|--------------------------------------------------------------------------
| Tenant Tickets API (برای customer-site) — PHASE 7.25 CANONICAL
|--------------------------------------------------------------------------
| Auth: license_key + domain (AuthenticateTenantApi middleware)
| Data: reads from canonical `tickets` table via TicketServiceInterface
| Response: backward-compatible with legacy /api/support-tickets format
|--------------------------------------------------------------------------
*/
// PHASE 8.0 — Secure tenant attachment download (private disk, site-scoped)
Route::prefix('tenant/attachments')->middleware(['throttle:30,1', 'tenant.api'])->group(function () {
    Route::get('{path}', [\App\Http\Controllers\Admin\SecureAttachmentController::class, 'tenantDownload'])
        ->where('path', '.+')
        ->name('api.tenant-attachments.download');
});

/*
|--------------------------------------------------------------------------
| Theme Marketplace API (برای customer-site) — TB-2
|--------------------------------------------------------------------------
| Auth: license_key + domain (tenant.api middleware)
*/
Route::prefix('theme-marketplace')->middleware(['throttle:30,1', 'tenant.api'])->group(function () {
    Route::get('/',                         [\App\Http\Controllers\Api\ThemeMarketplaceApiController::class, 'index'])->name('api.theme-marketplace.index');
    Route::get('/my-licenses',              [\App\Http\Controllers\Api\ThemeMarketplaceApiController::class, 'myLicenses'])->name('api.theme-marketplace.my-licenses');
    Route::get('/{theme}',                  [\App\Http\Controllers\Api\ThemeMarketplaceApiController::class, 'show'])->name('api.theme-marketplace.show');
    Route::post('/{theme}/initiate-purchase', [\App\Http\Controllers\Api\ThemeMarketplaceApiController::class, 'initiatePurchase'])->name('api.theme-marketplace.initiate-purchase');
    Route::get('/{theme}/download',         [\App\Http\Controllers\Api\ThemeMarketplaceApiController::class, 'download'])->name('api.theme-marketplace.download');
});

Route::prefix('tenant/tickets')->middleware(['throttle:30,1', 'tenant.api'])->group(function () {
    Route::get('/', [ApiTenantTicketController::class, 'index'])->name('api.tenant-tickets.index');
    Route::post('/', [ApiTenantTicketController::class, 'store'])->name('api.tenant-tickets.store');
    Route::get('{id}', [ApiTenantTicketController::class, 'show'])->name('api.tenant-tickets.show');
    Route::get('{id}/poll', [ApiTenantTicketController::class, 'poll'])->name('api.tenant-tickets.poll');
    Route::post('{id}/reply', [ApiTenantTicketController::class, 'reply'])->name('api.tenant-tickets.reply');
});

/*
|--------------------------------------------------------------------------
| Support Tickets API (برای customer-site) — PHASE 7.25 DEPRECATED
|--------------------------------------------------------------------------
| DEPRECATED: SupportApiClient در customer-site به /api/tenant/tickets مایگریت شد.
| این مسیرها برای backward-compatibility با نسخه‌های قدیمی‌تر باقی می‌مانند.
| TODO (post-PHASE 7.25): حذف پس از اطمینان از عدم استفاده.
|--------------------------------------------------------------------------
*/
// PHASE 8.0 — SECURITY: tenant.api middleware now required on legacy routes.
// All callers must supply a valid license_key + domain (Authorization header or body).
// X-Deprecated-Endpoint: true header is added by SupportTicketController.
Route::prefix('support-tickets')->middleware(['throttle:30,1', 'tenant.api'])->group(function () {
    Route::post('/', [ApiSupportTicketController::class, 'store'])->name('api.support-tickets.store');
    Route::get('/', [ApiSupportTicketController::class, 'index'])->name('api.support-tickets.index');
    Route::post('{id}/reply', [ApiSupportTicketController::class, 'reply'])->name('api.support-tickets.reply');
    Route::get('unread-count', [ApiSupportTicketController::class, 'unreadCount'])->name('api.support-tickets.unread-count');
});

/*
|--------------------------------------------------------------------------
| Tenant marketplace (فروشگاه ماژول داخل پنل مشتری)
|--------------------------------------------------------------------------
*/
Route::prefix('tenant-marketplace')->middleware('throttle:60,1')->group(function () {
    Route::get('modules', [\App\Http\Controllers\Api\TenantMarketplaceController::class, 'modules'])->name('api.tenant-marketplace.modules');
});

/*
|--------------------------------------------------------------------------
| ======================= API های کاربران (2FA, Lock, Password Reset, Groups, Tags) =======================
|--------------------------------------------------------------------------
|
| تمامی این مسیرها نیاز به احراز هویت (auth:sanctum) و محدودیت نرخ (throttle) دارند.
| مسیرهای عمومی (Password Reset) از middleware auth استفاده نمی‌کنند.
|
| رعایت قانون ۴: احراز هویت برای تمام endpoints به جز بازیابی رمز.
| رعایت قانون ۶: Rate Limiting برای تمام endpoints عمومی.
|
|--------------------------------------------------------------------------
*/

// ======================= User Security (2FA, Lock) ============================
Route::prefix('users')->middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    // 2FA
    Route::post('{user}/2fa/enable',  [UserApiController::class, 'enableTwoFactor'])->name('api.users.2fa.enable');
    Route::post('{user}/2fa/confirm', [UserApiController::class, 'confirmTwoFactor'])->name('api.users.2fa.confirm');
    Route::post('{user}/2fa/disable', [UserApiController::class, 'disableTwoFactor'])->name('api.users.2fa.disable');
    Route::post('{user}/2fa/verify',  [UserApiController::class, 'verifyTwoFactor'])->name('api.users.2fa.verify');

    // Lock
    Route::get('{user}/lock-status',  [UserApiController::class, 'lockStatus'])->name('api.users.lock.status');

    Route::post('{user}/unlock',      [UserApiController::class, 'unlockAccount'])->middleware('admin')->name('api.users.unlock');
});

// ======================= Password Reset (عمومی - بدون auth) ====================
Route::prefix('password-reset')->middleware('throttle:5,1')->group(function () {
    Route::post('send-code',    [UserApiController::class, 'sendResetCode'])->name('api.password-reset.send');
    Route::post('verify-code',  [UserApiController::class, 'verifyResetCode'])->name('api.password-reset.verify');
    Route::post('reset',        [UserApiController::class, 'resetPassword'])->name('api.password-reset.reset');
});

// ======================= Groups ===============================================
Route::prefix('groups')->middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::get('/',                   [GroupApiController::class, 'index'])->name('api.groups.index');
    Route::post('/',                  [GroupApiController::class, 'store'])->name('api.groups.store');
    Route::get('{group}',             [GroupApiController::class, 'show'])->name('api.groups.show');
    Route::put('{group}',             [GroupApiController::class, 'update'])->name('api.groups.update');
    Route::delete('{group}',          [GroupApiController::class, 'destroy'])->name('api.groups.destroy');

    // Users inside group
    Route::get('{group}/users',                  [GroupApiController::class, 'users'])->name('api.groups.users');
    Route::post('{group}/users',                 [GroupApiController::class, 'addUser'])->name('api.groups.add-user');
    Route::delete('{group}/users/{user}',        [GroupApiController::class, 'removeUser'])->name('api.groups.remove-user');
    Route::post('{group}/users/bulk',            [GroupApiController::class, 'bulkAssignUsers'])->name('api.groups.bulk-assign');
});

// ======================= Tags =================================================
Route::prefix('tags')->middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::get('/',                   [TagApiController::class, 'index'])->name('api.tags.index');
    Route::get('{tag}',               [TagApiController::class, 'show'])->name('api.tags.show');
    Route::get('{tag}/users',         [TagApiController::class, 'tagUsers'])->name('api.tags.users');
});

// ======================= User-Tag Assignment (nested under users) =============
Route::prefix('users')->middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::get('{user}/tags',         [TagApiController::class, 'userTags'])->name('api.users.tags.index');
    Route::post('{user}/tags',        [TagApiController::class, 'assignTags'])->name('api.users.tags.assign');
    Route::delete('{user}/tags',       [TagApiController::class, 'removeTags'])->name('api.users.tags.remove');
});

/*
|--------------------------------------------------------------------------
| PHASE 9.5-B — AI Runtime API (tenant-safe, governed, rate-limited)
|--------------------------------------------------------------------------
| All endpoints require auth:sanctum. Tenant isolation via middleware.
| Quota enforcement, rate limiting, prompt sanitization applied per request.
|--------------------------------------------------------------------------
*/
Route::prefix('ai')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('suggest',              [\SaaSPlatform\CoreUtils\Http\Controllers\AiApiController::class, 'suggest'])->name('api.ai.suggest');
    Route::post('chat',                 [\SaaSPlatform\CoreUtils\Http\Controllers\AiApiController::class, 'chat'])->name('api.ai.chat');
    Route::post('complete',             [\SaaSPlatform\CoreUtils\Http\Controllers\AiApiController::class, 'complete'])->name('api.ai.complete');
    Route::post('analyze',              [\SaaSPlatform\CoreUtils\Http\Controllers\AiApiController::class, 'analyze'])->name('api.ai.analyze');
    Route::post('generate',             [\SaaSPlatform\CoreUtils\Http\Controllers\AiApiController::class, 'generate'])->name('api.ai.generate');
    Route::post('translate',            [\SaaSPlatform\CoreUtils\Http\Controllers\AiApiController::class, 'translate'])->name('api.ai.translate');
    Route::post('summarize',            [\SaaSPlatform\CoreUtils\Http\Controllers\AiApiController::class, 'summarize'])->name('api.ai.summarize');
    Route::post('form-assistant',       [\SaaSPlatform\CoreUtils\Http\Controllers\AiApiController::class, 'formAssistant'])->name('api.ai.form-assistant');
    Route::post('ticket-assistant',     [\SaaSPlatform\CoreUtils\Http\Controllers\AiApiController::class, 'ticketAssistant'])->name('api.ai.ticket-assistant');
    Route::post('automation-assistant', [\SaaSPlatform\CoreUtils\Http\Controllers\AiApiController::class, 'automationAssistant'])->name('api.ai.automation-assistant');
});

/*
|--------------------------------------------------------------------------
| PHASE 9.5-B — Notifications API
|--------------------------------------------------------------------------
*/
Route::prefix('notifications')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/',                    [\SaaSPlatform\CoreUtils\Http\Controllers\NotificationApiController::class, 'index'])->name('api.notifications.index');
    Route::post('{id}/read',           [\SaaSPlatform\CoreUtils\Http\Controllers\NotificationApiController::class, 'markRead'])->name('api.notifications.mark-read');
    Route::post('mark-all-read',       [\SaaSPlatform\CoreUtils\Http\Controllers\NotificationApiController::class, 'markAllRead'])->name('api.notifications.mark-all-read');
    Route::put('preferences',          [\SaaSPlatform\CoreUtils\Http\Controllers\NotificationApiController::class, 'updatePreferences'])->name('api.notifications.preferences');
});

/*
|--------------------------------------------------------------------------
| PHASE 9.5-B — Realtime Polling Fallback + UX Telemetry
|--------------------------------------------------------------------------
*/
Route::prefix('realtime')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('poll', [\SaaSPlatform\CoreUtils\Http\Controllers\RealtimePollController::class, 'poll'])->name('api.realtime.poll');
});

Route::prefix('ux')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('rage-click',    [\SaaSPlatform\CoreUtils\Http\Controllers\RealtimePollController::class, 'recordRageClick'])->name('api.ux.rage-click');
    Route::post('interaction',   [\SaaSPlatform\CoreUtils\Http\Controllers\RealtimePollController::class, 'recordInteraction'])->name('api.ux.interaction');
    Route::post('performance',   [\SaaSPlatform\CoreUtils\Http\Controllers\RealtimePollController::class, 'recordPerformance'])->name('api.ux.performance');
});

/*
|--------------------------------------------------------------------------
| TB-AI Studio Import Pipeline
|--------------------------------------------------------------------------
| Receives theme pack ZIPs from the local TB-AI Studio desktop tool.
| Auth: studio.api middleware (Bearer key + optional HMAC).
|--------------------------------------------------------------------------
*/
Route::prefix('v1/studio')->middleware(['studio.api', 'throttle:30,1'])->name('api.studio.')->group(function () {
    Route::post('import',          [\App\Http\Controllers\Api\ThemePackStudioController::class, 'import'])->name('import');
    Route::post('validate',        [\App\Http\Controllers\Api\ThemePackStudioController::class, 'validate'])->name('validate');
    Route::get('preview/{uuid}',   [\App\Http\Controllers\Api\ThemePackStudioController::class, 'preview'])->name('preview');
    Route::post('install/{uuid}',  [\App\Http\Controllers\Api\ThemePackStudioController::class, 'install'])->name('install');
    Route::post('publish/{uuid}',  [\App\Http\Controllers\Api\ThemePackStudioController::class, 'publish'])->name('publish');
});
