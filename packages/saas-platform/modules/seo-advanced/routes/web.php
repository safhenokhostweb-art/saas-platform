<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\SeoAdvanced\Http\Controllers\SeoAdvancedAdminController;
use SaaSPlatform\Modules\SeoAdvanced\Http\Controllers\SeoAdvancedAiAssistantController;
use SaaSPlatform\Modules\SeoAdvanced\Http\Controllers\SeoAdvancedRuntimeController;
use SaaSPlatform\Modules\SeoAdvanced\Http\Controllers\SeoAdvancedSearchConsoleController;

/**
 * Phase 18 — Part 7/8: entirely the module's own routes, loaded via
 * SeoAdvancedServiceProvider::boot()'s loadRoutesFrom() — never added to
 * any Core routes file.
 */

// Customer Runtime (Part 8) — public, no auth.
Route::get('/seo-advanced/{siteId}/sitemap.xml', [SeoAdvancedRuntimeController::class, 'sitemap'])
    ->where('siteId', '[0-9]+')->name('seo-advanced.runtime.sitemap');
Route::get('/seo-advanced/{siteId}/robots.txt', [SeoAdvancedRuntimeController::class, 'robots'])
    ->where('siteId', '[0-9]+')->name('seo-advanced.runtime.robots');

// Admin (Part 7) — this module's own panel; mirrors the ['auth','admin']
// convention every other admin route in the host app already uses,
// without ever registering inside Core's own route files.
Route::middleware(['web', 'auth', 'admin'])->prefix('admin/seo-advanced/{siteId}')->name('seo-advanced.admin.')->group(function () {
    Route::get('/', [SeoAdvancedAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/meta', [SeoAdvancedAdminController::class, 'metaEdit'])->name('meta');
    Route::put('/meta', [SeoAdvancedAdminController::class, 'metaUpdate'])->name('meta.update');
    Route::get('/sitemap', [SeoAdvancedAdminController::class, 'sitemap'])->name('sitemap');
    Route::get('/robots', [SeoAdvancedAdminController::class, 'robotsPreview'])->name('robots');
    Route::get('/schema', [SeoAdvancedAdminController::class, 'schemaPreview'])->name('schema');
    Route::post('/schema/build', [SeoAdvancedAdminController::class, 'schemaBuilderPreview'])->name('schema.build');
    Route::get('/redirects', [SeoAdvancedAdminController::class, 'redirects'])->name('redirects');
    Route::post('/redirects', [SeoAdvancedAdminController::class, 'storeRedirect'])->name('redirects.store');
    Route::get('/redirects/{redirect}/edit', [SeoAdvancedAdminController::class, 'editRedirect'])->name('redirects.edit');
    Route::put('/redirects/{redirect}', [SeoAdvancedAdminController::class, 'updateRedirect'])->name('redirects.update');
    Route::delete('/redirects/{redirect}', [SeoAdvancedAdminController::class, 'destroyRedirect'])->name('redirects.destroy');
    Route::get('/audit', [SeoAdvancedAdminController::class, 'audit'])->name('audit');
    Route::post('/audit', [SeoAdvancedAdminController::class, 'runAudit'])->name('audit.run');
    Route::get('/report', [SeoAdvancedAdminController::class, 'report'])->name('report');
    Route::get('/keywords', [SeoAdvancedAdminController::class, 'keywords'])->name('keywords');
    Route::post('/keywords', [SeoAdvancedAdminController::class, 'storeKeyword'])->name('keywords.store');
    Route::post('/keywords/{keyword}/position', [SeoAdvancedAdminController::class, 'recordKeywordPosition'])->name('keywords.position');
    Route::delete('/keywords/{keyword}', [SeoAdvancedAdminController::class, 'destroyKeyword'])->name('keywords.destroy');

    Route::get('/search-console', [SeoAdvancedSearchConsoleController::class, 'show'])->name('search-console');
    Route::get('/search-console/connect', [SeoAdvancedSearchConsoleController::class, 'connect'])->name('search-console.connect');
    Route::get('/search-console/callback', [SeoAdvancedSearchConsoleController::class, 'callback'])->name('search-console.callback');
    Route::get('/search-console/properties', [SeoAdvancedSearchConsoleController::class, 'properties'])->name('search-console.properties');
    Route::post('/search-console/select-property', [SeoAdvancedSearchConsoleController::class, 'selectProperty'])->name('search-console.select-property');
    Route::post('/search-console/disconnect', [SeoAdvancedSearchConsoleController::class, 'disconnect'])->name('search-console.disconnect');
    Route::get('/search-console/performance', [SeoAdvancedSearchConsoleController::class, 'performance'])->name('search-console.performance');

    Route::get('/ai-assistant', [SeoAdvancedAiAssistantController::class, 'show'])->name('ai-assistant');
    Route::post('/ai-assistant', [SeoAdvancedAiAssistantController::class, 'analyze'])->name('ai-assistant.analyze');
});
