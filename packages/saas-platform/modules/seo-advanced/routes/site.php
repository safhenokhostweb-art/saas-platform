<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\SeoAdvanced\Http\Controllers\Site\SeoAdvancedCustomerReportController;

/**
 * Phase 18-D — Part 5: Customer Portal routes — mirrors the Tickets
 * module's own Routes/site.php convention (prefix + auth-only middleware,
 * no siteId route parameter — see SeoAdvancedCustomerReportController's
 * docblock for why). Loaded separately from routes/web.php so the Admin
 * and Customer route sets stay clearly distinct, matching Phase 18-C.
 */
Route::middleware(['web', 'auth'])
    ->prefix('seo-advanced/report')
    ->name('seo-advanced.site.')
    ->group(function () {
        Route::get('/', [SeoAdvancedCustomerReportController::class, 'show'])->name('report');
    });
