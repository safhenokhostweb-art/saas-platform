<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LicenseController as ApiLicenseController;
use App\Http\Controllers\Api\UpdateController as ApiUpdateController;
use App\Http\Controllers\Api\SiteConfigController as ApiSiteConfigController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Api\SupportTicketController as ApiSupportTicketController;

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
