<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmActivityController;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmAttachmentController;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmCompanyController;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmContactController;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmDashboardController;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmImportExportController;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmLeadController;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmOpportunityController;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmPipelineController;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmProfileController;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmReportController;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmTagController;
use SaaSPlatform\Modules\Crm\Http\Controllers\Admin\CrmTaskController;

/**
 * Phase 19 — Part 4-8: Admin routes — mirrors the Tickets module's own
 * Routes/admin.php convention (web,auth,admin middleware,
 * admin/{module}/{siteId} prefix) — never registered inside Core's own
 * route files.
 */
Route::middleware(['web', 'auth', 'admin'])->prefix('admin/crm/{siteId}')->name('crm.admin.')->group(function () {
    Route::get('/', [CrmDashboardController::class, 'show'])->name('dashboard');
    Route::get('/reports', [CrmReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/export', [CrmImportExportController::class, 'exportReport'])->name('reports.export');

    Route::get('/import/{entity}', [CrmImportExportController::class, 'showImport'])->whereIn('entity', ['contact', 'company', 'lead'])->name('import.show');
    Route::post('/import/{entity}/preview', [CrmImportExportController::class, 'preview'])->whereIn('entity', ['contact', 'company', 'lead'])->name('import.preview');
    Route::post('/import/{entity}/commit', [CrmImportExportController::class, 'commit'])->whereIn('entity', ['contact', 'company', 'lead'])->name('import.commit');

    Route::get('/export/contacts', [CrmImportExportController::class, 'exportContacts'])->name('export.contacts');
    Route::get('/export/opportunities', [CrmImportExportController::class, 'exportOpportunities'])->name('export.opportunities');

    Route::get('/companies', [CrmCompanyController::class, 'index'])->name('companies.index');
    Route::post('/companies', [CrmCompanyController::class, 'store'])->name('companies.store');
    Route::put('/companies/{company}', [CrmCompanyController::class, 'update'])->name('companies.update');
    Route::delete('/companies/{company}', [CrmCompanyController::class, 'destroy'])->name('companies.destroy');
    Route::post('/companies/{company}/favorite', [CrmCompanyController::class, 'toggleFavorite'])->name('companies.favorite');
    Route::put('/companies/{company}/tags', [CrmCompanyController::class, 'syncTags'])->name('companies.tags');
    Route::post('/companies/bulk', [CrmCompanyController::class, 'bulk'])->name('companies.bulk');

    Route::get('/contacts', [CrmContactController::class, 'index'])->name('contacts.index');
    Route::post('/contacts', [CrmContactController::class, 'store'])->name('contacts.store');
    Route::put('/contacts/{contact}', [CrmContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [CrmContactController::class, 'destroy'])->name('contacts.destroy');
    Route::post('/contacts/{contact}/favorite', [CrmContactController::class, 'toggleFavorite'])->name('contacts.favorite');
    Route::put('/contacts/{contact}/tags', [CrmContactController::class, 'syncTags'])->name('contacts.tags');
    Route::post('/contacts/bulk', [CrmContactController::class, 'bulk'])->name('contacts.bulk');
    Route::get('/contacts/{contact}/profile', [CrmProfileController::class, 'show'])->name('contacts.profile');

    Route::get('/leads', [CrmLeadController::class, 'index'])->name('leads.index');
    Route::post('/leads', [CrmLeadController::class, 'store'])->name('leads.store');
    Route::put('/leads/{lead}', [CrmLeadController::class, 'update'])->name('leads.update');
    Route::delete('/leads/{lead}', [CrmLeadController::class, 'destroy'])->name('leads.destroy');
    Route::post('/leads/{lead}/convert', [CrmLeadController::class, 'convert'])->name('leads.convert');
    Route::post('/leads/bulk', [CrmLeadController::class, 'bulk'])->name('leads.bulk');

    Route::get('/opportunities', [CrmOpportunityController::class, 'index'])->name('opportunities.index');
    Route::get('/opportunities/kanban', [CrmOpportunityController::class, 'kanban'])->name('opportunities.kanban');
    Route::get('/opportunities/forecast', [CrmOpportunityController::class, 'forecast'])->name('opportunities.forecast');
    Route::post('/opportunities', [CrmOpportunityController::class, 'store'])->name('opportunities.store');
    Route::put('/opportunities/{opportunity}', [CrmOpportunityController::class, 'update'])->name('opportunities.update');
    Route::delete('/opportunities/{opportunity}', [CrmOpportunityController::class, 'destroy'])->name('opportunities.destroy');
    Route::post('/opportunities/{opportunity}/move-stage', [CrmOpportunityController::class, 'moveStage'])->name('opportunities.move-stage');
    Route::post('/opportunities/{opportunity}/won', [CrmOpportunityController::class, 'markWon'])->name('opportunities.won');
    Route::post('/opportunities/{opportunity}/lost', [CrmOpportunityController::class, 'markLost'])->name('opportunities.lost');
    Route::post('/opportunities/{opportunity}/favorite', [CrmOpportunityController::class, 'toggleFavorite'])->name('opportunities.favorite');
    Route::put('/opportunities/{opportunity}/tags', [CrmOpportunityController::class, 'syncTags'])->name('opportunities.tags');
    Route::post('/opportunities/bulk', [CrmOpportunityController::class, 'bulk'])->name('opportunities.bulk');

    Route::get('/pipelines', [CrmPipelineController::class, 'index'])->name('pipelines.index');
    Route::post('/pipelines', [CrmPipelineController::class, 'store'])->name('pipelines.store');
    Route::delete('/pipelines/{pipeline}', [CrmPipelineController::class, 'destroy'])->name('pipelines.destroy');
    Route::post('/pipelines/{pipeline}/stages', [CrmPipelineController::class, 'storeStage'])->name('pipelines.stages.store');
    Route::put('/pipelines/{pipeline}/stages/{stage}', [CrmPipelineController::class, 'updateStage'])->name('pipelines.stages.update');
    Route::delete('/pipelines/{pipeline}/stages/{stage}', [CrmPipelineController::class, 'destroyStage'])->name('pipelines.stages.destroy');

    Route::get('/tags', [CrmTagController::class, 'index'])->name('tags.index');
    Route::post('/tags', [CrmTagController::class, 'store'])->name('tags.store');
    Route::delete('/tags/{tag}', [CrmTagController::class, 'destroy'])->name('tags.destroy');

    Route::get('/tasks', [CrmTaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks/{task}/complete', [CrmTaskController::class, 'complete'])->name('tasks.complete');
    Route::delete('/tasks/{task}', [CrmTaskController::class, 'destroy'])->name('tasks.destroy');

    // Polymorphic — {subjectType} is a short alias (see config('crm.subject_types')), never a raw class name.
    Route::prefix('/{subjectType}/{subjectId}')->where(['subjectId' => '[0-9]+'])->group(function () {
        Route::get('/activities', [CrmActivityController::class, 'index'])->name('activities.index');
        Route::post('/activities', [CrmActivityController::class, 'store'])->name('activities.store');
        Route::post('/tasks', [CrmTaskController::class, 'store'])->name('subject-tasks.store');
        Route::post('/attachments', [CrmAttachmentController::class, 'store'])->name('attachments.store');
    });

    Route::delete('/activities/{activity}', [CrmActivityController::class, 'destroy'])->name('activities.destroy');
    Route::delete('/attachments/{attachment}', [CrmAttachmentController::class, 'destroy'])->name('attachments.destroy');
});
