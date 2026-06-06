<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Education\Http\Controllers\Api\EducationAnalyticsController;
use SaaSPlatform\Modules\Education\Http\Controllers\Api\EducationGeneratedContentController;
use SaaSPlatform\Modules\Education\Http\Controllers\Api\EducationReviewController;
use SaaSPlatform\Modules\Education\Http\Controllers\Api\EducationProfileController;
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
