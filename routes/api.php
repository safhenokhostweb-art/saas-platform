<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LicenseController as ApiLicenseController;
use App\Http\Controllers\Api\UpdateController as ApiUpdateController;
use App\Http\Controllers\Api\SiteConfigController as ApiSiteConfigController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Api\SupportTicketController as ApiSupportTicketController;
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
| Support Tickets API (برای customer-site)
|--------------------------------------------------------------------------
*/
Route::prefix('support-tickets')->middleware('throttle:30,1')->group(function () {
    Route::post('/', [ApiSupportTicketController::class, 'store'])->name('api.support-tickets.store');
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
