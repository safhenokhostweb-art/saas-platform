<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketReplyController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketStatusController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketAssignmentController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketPriorityController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketSlaController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketSettingsController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketSmsController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketSiteAssignController;
use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketAdminAttachmentController;

/*
|--------------------------------------------------------------------------
| Tickets Module — Admin Routes (PHASE 7.1 Canonical + Phase 8.3 SMS/Settings)
|--------------------------------------------------------------------------
| Prefix: /admin/tickets
| Middleware: web, auth, admin
*/

Route::middleware(['web', 'auth', 'admin'])
    ->prefix('admin/tickets')
    ->name('tickets.admin.')
    ->group(function () {

        // ─── Settings ────────────────────────────────────────────────
        Route::get('/settings',  [TicketSettingsController::class, 'index'])->name('settings');
        Route::patch('/settings',[TicketSettingsController::class, 'update'])->name('settings.update');

        // ─── SLA dashboard (feature-flagged) ─────────────────────────
        Route::get('/sla/dashboard', [TicketSlaController::class, 'index'])->name('sla.index');

        // ─── Core ticket CRUD ────────────────────────────────────────
        Route::get('/',           [TicketController::class, 'index'])->name('index');
        Route::get('/create',     [TicketController::class, 'create'])->name('create');
        Route::post('/',          [TicketController::class, 'store'])->name('store');
        Route::post('/bulk',      [TicketController::class, 'bulkAction'])->name('bulk');
        Route::get('/{id}',       [TicketController::class, 'show'])->name('show');
        Route::get('/{id}/poll',  [TicketController::class, 'poll'])->name('poll');

        // ─── Status / Assignment / Priority ──────────────────────────
        Route::patch('/{id}/status',   [TicketStatusController::class,    'update'])->name('status.update');
        Route::patch('/{id}/assign',   [TicketAssignmentController::class, 'update'])->name('assign');
        Route::patch('/{id}/priority', [TicketPriorityController::class,   'update'])->name('priority.update');
        Route::patch('/{id}/site',     [TicketSiteAssignController::class, 'update'])->name('site.update');

        // ─── Replies ─────────────────────────────────────────────────
        Route::post('/{id}/replies', [TicketReplyController::class, 'store'])->name('replies.store');

        // ─── SMS ─────────────────────────────────────────────────────
        Route::post('/{id}/sms',          [TicketSmsController::class, 'send'])->name('sms.send');
        Route::get('/{id}/sms/templates', [TicketSmsController::class, 'templates'])->name('sms.templates');

        // ─── Attachments (private disk streaming) ────────────────────
        Route::get('/attachments/{path}', [TicketAdminAttachmentController::class, 'download'])
            ->where('path', '.*')
            ->name('attachment.download');

        // ─── Phase 9.4: Operational Analytics Dashboard ───────────
        Route::get('/analytics',      [\SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketAnalyticsController::class, 'index'])->name('analytics');
        Route::get('/analytics/data', [\SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketAnalyticsController::class, 'data'])->name('analytics.data');
    });
