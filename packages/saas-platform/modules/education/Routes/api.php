<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Education\Http\Controllers\Api\EducationAnalyticsController;
use SaaSPlatform\Modules\Education\Http\Controllers\Api\EducationGeneratedContentController;
use SaaSPlatform\Modules\Education\Http\Controllers\Api\EducationReviewController;
use SaaSPlatform\Modules\Education\Http\Controllers\Api\EducationProfileController;
use SaaSPlatform\Modules\Education\Http\Controllers\Api\EducationSiteAdminController;
use SaaSPlatform\Modules\Education\Http\Controllers\Api\EducationSyncApiController;
use SaaSPlatform\Modules\Education\Http\Controllers\Api\MobileEducationController;

Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/education')->name('api.education.')->group(function () {
    Route::get('/articles',           [MobileEducationController::class, 'articles'])->name('articles');
    Route::get('/articles/{id}',      [MobileEducationController::class, 'show'])->name('articles.show');
    Route::get('/search',             [MobileEducationController::class, 'search'])->name('search');
    Route::get('/learning-paths',     [MobileEducationController::class, 'learningPaths'])->name('learning-paths');
    Route::post('/articles/{id}/progress', [MobileEducationController::class, 'markProgress'])->name('articles.progress');

    // EDU-14A — AI Learning Profile (user profile + gaps + scored recommendations)
    Route::get('/profile', [EducationProfileController::class, 'show'])->name('profile');

    // EDU-14B — Intelligence Orchestration Layer: combined analytics snapshot
    Route::get('/analytics', [EducationAnalyticsController::class, 'show'])->name('analytics');

    // EDU-15 — AI Content Generation: draft overview for review workflow
    Route::get('/generated-content', [EducationGeneratedContentController::class, 'index'])->name('generated-content');

    // EDU-16 — Human Review: approve and reject AI-generated drafts
    Route::post('/generated-content/{id}/approve', [EducationReviewController::class, 'approve'])->name('generated-content.approve');
    Route::post('/generated-content/{id}/reject',  [EducationReviewController::class, 'reject'])->name('generated-content.reject');
});

// Customer-Site Sync API (Rule 12 — Differential Sync)
// Authenticated via platform API key or sanctum
Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/education/sync')->name('api.education.sync.')->group(function () {
    // Manifest — hash index for differential check
    Route::get('/{siteId}/manifest',       [EducationSyncApiController::class, 'manifest'])->name('manifest');

    // Diff — compare client manifest against server, returns new/updated/removed
    Route::post('/{siteId}/diff',          [EducationSyncApiController::class, 'diff'])->name('diff');

    // Content endpoints — return eligible content for site
    Route::get('/{siteId}/articles',       [EducationSyncApiController::class, 'articles'])->name('articles');
    Route::get('/{siteId}/walkthroughs',   [EducationSyncApiController::class, 'walkthroughs'])->name('walkthroughs');
    Route::get('/{siteId}/learning-paths', [EducationSyncApiController::class, 'learningPaths'])->name('learning-paths');

    // Trigger sync (used by admin panel or scheduler)
    Route::post('/{siteId}/trigger',       [EducationSyncApiController::class, 'trigger'])->name('trigger');
});

// Customer-Site Admin CRUD API — site-scoped create/edit/delete
Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/education/site-admin')->name('api.education.site-admin.')->group(function () {
    // Articles
    Route::get('/{siteId}/articles',                  [EducationSiteAdminController::class, 'articleIndex'])->name('articles.index');
    Route::post('/{siteId}/articles',                 [EducationSiteAdminController::class, 'articleStore'])->name('articles.store');
    Route::put('/{siteId}/articles/{articleId}',      [EducationSiteAdminController::class, 'articleUpdate'])->name('articles.update');
    Route::delete('/{siteId}/articles/{articleId}',   [EducationSiteAdminController::class, 'articleDestroy'])->name('articles.destroy');
    Route::post('/{siteId}/articles/{articleId}/publish', [EducationSiteAdminController::class, 'articlePublish'])->name('articles.publish');

    // Walkthroughs
    Route::get('/{siteId}/walkthroughs',                      [EducationSiteAdminController::class, 'walkthroughIndex'])->name('walkthroughs.index');
    Route::post('/{siteId}/walkthroughs',                     [EducationSiteAdminController::class, 'walkthroughStore'])->name('walkthroughs.store');
    Route::put('/{siteId}/walkthroughs/{walkthroughId}',      [EducationSiteAdminController::class, 'walkthroughUpdate'])->name('walkthroughs.update');
    Route::delete('/{siteId}/walkthroughs/{walkthroughId}',   [EducationSiteAdminController::class, 'walkthroughDestroy'])->name('walkthroughs.destroy');

    // Learning Paths
    Route::get('/{siteId}/learning-paths',              [EducationSiteAdminController::class, 'learningPathIndex'])->name('learning-paths.index');
    Route::post('/{siteId}/learning-paths',             [EducationSiteAdminController::class, 'learningPathStore'])->name('learning-paths.store');
    Route::put('/{siteId}/learning-paths/{pathId}',     [EducationSiteAdminController::class, 'learningPathUpdate'])->name('learning-paths.update');
    Route::delete('/{siteId}/learning-paths/{pathId}',  [EducationSiteAdminController::class, 'learningPathDestroy'])->name('learning-paths.destroy');
});
