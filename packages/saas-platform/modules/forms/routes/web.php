<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormSubmissionController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormSiteController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormFrontendController;

/*
|--------------------------------------------------------------------------
| Forms Module Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the FormsServiceProvider.
|
*/

// Frontend routes (public form display and submission)
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

    // Form fields
    Route::post('forms/{form}/fields', [FormController::class, 'storeField'])->name('forms.fields.store');
    Route::delete('forms/fields/{field}', [FormController::class, 'destroyField'])->name('forms.fields.destroy');
    Route::patch('forms/fields/{field}/toggle', [FormController::class, 'toggleField'])->name('forms.fields.toggle');
    Route::post('forms/{form}/fields/sort', [FormController::class, 'sortFields'])->name('forms.fields.sort');

    // Form site assignment
    Route::get('forms/{form}/sites', [FormSiteController::class, 'edit'])->name('forms.sites.edit');
    Route::put('forms/{form}/sites', [FormSiteController::class, 'update'])->name('forms.sites.update');
    Route::post('forms/{form}/sites/{site}/toggle', [FormSiteController::class, 'toggleSite'])->name('forms.sites.toggle');

    // Form submissions
    Route::get('forms/submissions', [FormSubmissionController::class, 'index'])->name('forms.submissions.index');
    Route::get('forms/submissions/export', [FormSubmissionController::class, 'export'])->name('forms.submissions.export');
    Route::post('forms/submissions/column-order', [FormSubmissionController::class, 'updateColumnOrder'])->name('forms.submissions.column-order');
});
