<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Site\TicketPortalController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Site\TicketSubmissionController;

/*
|--------------------------------------------------------------------------
| Tickets Module — Site (Customer Portal) Routes (PHASE 7.1 Canonical)
|--------------------------------------------------------------------------
| Prefix: /tickets (customer-facing, within tenant context)
| Middleware: web, auth (tenant user)
|
| These routes NEVER expose admin/platform functionality.
| Site scoping is enforced in services, not middleware.
*/

Route::middleware(['web', 'auth'])
    ->prefix('tickets')
    ->name('tickets.site.')
    ->group(function () {

        // Listing and viewing
        Route::get('/',          [TicketPortalController::class, 'index'])->name('index');
        Route::get('/{id}',      [TicketPortalController::class, 'show'])->name('show');

        // Submission
        Route::get('/create',    [TicketSubmissionController::class, 'create'])->name('create');
        Route::post('/',         [TicketSubmissionController::class, 'store'])->name('store');
        Route::post('/{id}/reply', [TicketSubmissionController::class, 'storeReply'])->name('replies.store');
    });
