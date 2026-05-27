<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormSubmissionController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormSiteController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormFrontendController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormBuilderController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormAnalyticsController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormAutomationController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormTemplateController;

/*
|--------------------------------------------------------------------------
| Forms Module Routes
|--------------------------------------------------------------------------
*/

// Frontend routes (public)
Route::middleware(['web', 'module:forms'])->group(function () {
    Route::get('/forms', [FormFrontendController::class, 'index'])->name('forms.index');
    Route::get('/forms/{slug}', [FormFrontendController::class, 'show'])->name('forms.show');
    Route::post('/forms/{slug}', [FormFrontendController::class, 'submit'])
        ->middleware('throttle:forms-submit')
        ->name('forms.submit');
});

// Admin routes
Route::middleware(['web', 'auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    // Forms CRUD
    Route::resource('forms', FormController::class)->except(['show']);

    // Form preview
    Route::get('forms/{form}/preview', [FormController::class, 'preview'])->name('forms.preview');

    // Duplicate form (Phase 9.1)
    Route::post('forms/{form}/duplicate', [FormController::class, 'duplicate'])->name('forms.duplicate');

    // Form fields (legacy inline add/delete — still used by settings panel)
    Route::post('forms/{form}/fields', [FormController::class, 'storeField'])->name('forms.fields.store');
    Route::delete('forms/fields/{field}', [FormController::class, 'destroyField'])->name('forms.fields.destroy');
    Route::patch('forms/fields/{field}/toggle', [FormController::class, 'toggleField'])->name('forms.fields.toggle');
    Route::post('forms/{form}/fields/sort', [FormController::class, 'sortFields'])->name('forms.fields.sort');

    // Enterprise Builder (AJAX API)
    Route::get('forms/{form}/builder', [FormBuilderController::class, 'show'])->name('forms.builder');
    Route::post('forms/{form}/builder/fields', [FormBuilderController::class, 'storeField'])->name('forms.builder.fields.store');
    Route::put('forms/{form}/builder/fields/{field}', [FormBuilderController::class, 'updateField'])->name('forms.builder.fields.update');
    Route::delete('forms/{form}/builder/fields/{field}', [FormBuilderController::class, 'destroyField'])->name('forms.builder.fields.destroy');
    Route::post('forms/{form}/builder/fields/{field}/duplicate', [FormBuilderController::class, 'duplicateField'])->name('forms.builder.fields.duplicate');
    Route::post('forms/{form}/builder/sort', [FormBuilderController::class, 'sortFields'])->name('forms.builder.sort');
    Route::put('forms/{form}/builder/settings', [FormBuilderController::class, 'updateSettings'])->name('forms.builder.settings');

    // Form site assignment
    Route::get('forms/{form}/sites', [FormSiteController::class, 'edit'])->name('forms.sites.edit');
    Route::put('forms/{form}/sites', [FormSiteController::class, 'update'])->name('forms.sites.update');
    Route::post('forms/{form}/sites/{site}/toggle', [FormSiteController::class, 'toggleSite'])->name('forms.sites.toggle');

    // Form submissions
    Route::get('forms/submissions', [FormSubmissionController::class, 'index'])->name('forms.submissions.index');
    Route::get('forms/submissions/export', [FormSubmissionController::class, 'export'])->name('forms.submissions.export');
    Route::post('forms/submissions/column-order', [FormSubmissionController::class, 'updateColumnOrder'])->name('forms.submissions.column-order');

    // Analytics
    Route::get('forms/{form}/analytics', [FormAnalyticsController::class, 'show'])->name('forms.analytics');

    // Automation
    Route::get('forms/{form}/automation', [FormAutomationController::class, 'index'])->name('forms.automation.index');
    Route::post('forms/{form}/automation', [FormAutomationController::class, 'store'])->name('forms.automation.store');
    Route::put('forms/{form}/automation/{automation}', [FormAutomationController::class, 'update'])->name('forms.automation.update');
    Route::delete('forms/{form}/automation/{automation}', [FormAutomationController::class, 'destroy'])->name('forms.automation.destroy');
    Route::patch('forms/{form}/automation/{automation}/toggle', [FormAutomationController::class, 'toggle'])->name('forms.automation.toggle');

    // Templates
    Route::get('forms/templates', [FormTemplateController::class, 'index'])->name('forms.templates.index');
    Route::post('forms/{form}/save-as-template', [FormTemplateController::class, 'store'])->name('forms.templates.store');
    Route::post('forms/{form}/apply-template', [FormTemplateController::class, 'apply'])->name('forms.templates.apply');
    Route::delete('forms/templates/{template}', [FormTemplateController::class, 'destroy'])->name('forms.templates.destroy');
});
