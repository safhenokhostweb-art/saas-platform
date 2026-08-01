<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Crm\Http\Controllers\Site\CrmCustomerDashboardController;

/**
 * Phase 19/20 — Customer Portal routes. Mirrors the Tickets module's own
 * Routes/site.php convention (web,auth middleware, no admin gate, site
 * scoping enforced in the controller via the authenticated user's own
 * site — never a route parameter, see ResolvesCurrentSite).
 */
Route::middleware(['web', 'auth'])
    ->prefix('crm')
    ->name('crm.site.')
    ->group(function () {
        Route::get('/', [CrmCustomerDashboardController::class, 'show'])->name('dashboard');
    });
