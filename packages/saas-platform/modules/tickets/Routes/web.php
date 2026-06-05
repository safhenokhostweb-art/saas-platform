<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Tickets\Http\Controllers\TicketsController;

/*
|--------------------------------------------------------------------------
| Tickets Module Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the TicketsServiceProvider.
| They are prefixed with 'admin' and have the 'admin' middleware.
|
*/

Route::middleware(['web', 'auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('tickets', TicketsController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

    Route::patch('tickets/{ticket}/status', [TicketsController::class, 'updateStatus'])->name('tickets.update-status');
    Route::post('tickets/{ticket}/replies', [TicketsController::class, 'storeReply'])->name('tickets.replies.store');
    Route::patch('tickets/{ticket}/assign', [TicketsController::class, 'assign'])->name('tickets.assign');
});
