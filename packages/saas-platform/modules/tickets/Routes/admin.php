<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketReplyController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketStatusController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketAssignmentController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketPriorityController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketSlaController;

/*
|--------------------------------------------------------------------------
| Tickets Module — Admin Routes (PHASE 7.1 Canonical)
|--------------------------------------------------------------------------
| Prefix: /admin/tickets
| Middleware: web, auth, admin (resolved by consuming project)
*/

Route::middleware(['web', 'auth', 'admin'])
    ->prefix('admin/tickets')
    ->name('tickets.admin.')
    ->group(function () {

        // Core ticket CRUD
        Route::get('/',        [TicketController::class, 'index'])->name('index');
        Route::get('/create',  [TicketController::class, 'create'])->name('create');
        Route::post('/',       [TicketController::class, 'store'])->name('store');
        Route::get('/{id}',    [TicketController::class, 'show'])->name('show');

        // Status transitions
        Route::patch('/{id}/status',   [TicketStatusController::class, 'update'])->name('status.update');

        // Assignment
        Route::patch('/{id}/assign',   [TicketAssignmentController::class, 'update'])->name('assign');

        // Priority
        Route::patch('/{id}/priority', [TicketPriorityController::class, 'update'])->name('priority.update');

        // Replies
        Route::post('/{id}/replies',   [TicketReplyController::class, 'store'])->name('replies.store');

        // SLA dashboard (feature-flagged)
        Route::get('/sla/dashboard',   [TicketSlaController::class, 'index'])->name('sla.index');
    });
