<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Api\TicketApiController;

/*
|--------------------------------------------------------------------------
| Tickets Module — API Routes (PHASE 7.1 Canonical)
|--------------------------------------------------------------------------
| Prefix: /api/tickets
| Middleware: api, auth:sanctum
*/

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/tickets')
    ->name('tickets.api.')
    ->group(function () {

        Route::get('/',               [TicketApiController::class, 'index'])->name('index');
        Route::post('/',              [TicketApiController::class, 'store'])->name('store');
        Route::get('/stats',          [TicketApiController::class, 'stats'])->name('stats');
        Route::get('/{id}',           [TicketApiController::class, 'show'])->name('show');
        Route::post('/{id}/replies',  [TicketApiController::class, 'addReply'])->name('replies.store');
    });
