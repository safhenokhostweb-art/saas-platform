<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Api\TicketApiController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Api\MobilePushController;

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
        Route::patch('/{id}/status',  [TicketApiController::class, 'updateStatus'])->name('status.update');
        Route::post('/{id}/vote',     [TicketApiController::class, 'vote'])->name('vote');

        // Section J: FCM token registration
        Route::post('/push/register',    [MobilePushController::class, 'register'])->name('push.register');
        Route::delete('/push/unregister',[MobilePushController::class, 'unregister'])->name('push.unregister');
    });
