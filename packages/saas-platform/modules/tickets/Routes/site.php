<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Site\TicketPortalController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Site\TicketSubmissionController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Site\TicketSiteAttachmentController;

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

        // Static routes first (before wildcard /{id})
        Route::get('/',        [TicketPortalController::class,   'index'])->name('index');
        Route::get('/create',  [TicketSubmissionController::class, 'create'])->name('create');
        Route::post('/',       [TicketSubmissionController::class, 'store'])->name('store');

        // Private-disk attachment streaming
        Route::get('/attachments/{path}', [TicketSiteAttachmentController::class, 'download'])
            ->where('path', '.*')
            ->name('attachment.download');

        // Wildcard ticket routes (integers only)
        Route::get('/{id}',          [TicketPortalController::class,   'show'])->whereNumber('id')->name('show');
        Route::post('/{id}/reply',   [TicketSubmissionController::class, 'storeReply'])->whereNumber('id')->name('replies.store');
    });
