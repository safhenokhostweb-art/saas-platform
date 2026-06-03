<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Education\Http\Controllers\Admin\EducationCenterController;
use SaaSPlatform\Modules\Education\Http\Controllers\Admin\EducationArticleController;
use SaaSPlatform\Modules\Education\Http\Controllers\Admin\EducationCategoryController;
use SaaSPlatform\Modules\Education\Http\Controllers\Admin\EducationWalkthroughController;
use SaaSPlatform\Modules\Education\Http\Controllers\Admin\EducationLearningPathController;
use SaaSPlatform\Modules\Education\Http\Controllers\Admin\EducationReleaseNoteController;
use SaaSPlatform\Modules\Education\Http\Controllers\Admin\EducationFeatureController;
use SaaSPlatform\Modules\Education\Http\Controllers\Admin\EducationRecommendationController;
use SaaSPlatform\Modules\Education\Http\Controllers\Admin\EducationAdoptionController;

Route::middleware(['web', 'auth'])->prefix('admin/education')->name('admin.education.')->group(function () {

    // Dashboard
    Route::get('/', [EducationCenterController::class, 'index'])->name('index');

    // Articles
    Route::prefix('articles')->name('articles.')->group(function () {
        Route::get('/',                      [EducationArticleController::class, 'index'])->name('index');
        Route::get('/create',                [EducationArticleController::class, 'create'])->name('create');
        Route::post('/',                     [EducationArticleController::class, 'store'])->name('store');
        Route::get('/{article}',             [EducationArticleController::class, 'show'])->name('show');
        Route::get('/{article}/edit',        [EducationArticleController::class, 'edit'])->name('edit');
        Route::put('/{article}',             [EducationArticleController::class, 'update'])->name('update');
        Route::delete('/{article}',          [EducationArticleController::class, 'destroy'])->name('destroy');
        Route::post('/{article}/publish',    [EducationArticleController::class, 'publish'])->name('publish');
    });

    // Categories
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/',               [EducationCategoryController::class, 'index'])->name('index');
        Route::post('/',              [EducationCategoryController::class, 'store'])->name('store');
        Route::put('/{category}',     [EducationCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}',  [EducationCategoryController::class, 'destroy'])->name('destroy');
    });

    // Walkthroughs
    Route::prefix('walkthroughs')->name('walkthroughs.')->group(function () {
        Route::get('/',                      [EducationWalkthroughController::class, 'index'])->name('index');
        Route::get('/create',                [EducationWalkthroughController::class, 'create'])->name('create');
        Route::post('/',                     [EducationWalkthroughController::class, 'store'])->name('store');
        Route::get('/{walkthrough}/edit',    [EducationWalkthroughController::class, 'edit'])->name('edit');
        Route::put('/{walkthrough}',         [EducationWalkthroughController::class, 'update'])->name('update');
        Route::delete('/{walkthrough}',      [EducationWalkthroughController::class, 'destroy'])->name('destroy');
    });

    // Learning Paths
    Route::prefix('learning-paths')->name('learning-paths.')->group(function () {
        Route::get('/',                        [EducationLearningPathController::class, 'index'])->name('index');
        Route::get('/create',                  [EducationLearningPathController::class, 'create'])->name('create');
        Route::post('/',                       [EducationLearningPathController::class, 'store'])->name('store');
        Route::get('/{learningPath}/edit',     [EducationLearningPathController::class, 'edit'])->name('edit');
        Route::put('/{learningPath}',          [EducationLearningPathController::class, 'update'])->name('update');
        Route::delete('/{learningPath}',       [EducationLearningPathController::class, 'destroy'])->name('destroy');
    });

    // Release Notes
    Route::prefix('release-notes')->name('release-notes.')->group(function () {
        Route::get('/',                       [EducationReleaseNoteController::class, 'index'])->name('index');
        Route::get('/create',                 [EducationReleaseNoteController::class, 'create'])->name('create');
        Route::post('/',                      [EducationReleaseNoteController::class, 'store'])->name('store');
        Route::get('/{releaseNote}/edit',     [EducationReleaseNoteController::class, 'edit'])->name('edit');
        Route::put('/{releaseNote}',          [EducationReleaseNoteController::class, 'update'])->name('update');
        Route::delete('/{releaseNote}',       [EducationReleaseNoteController::class, 'destroy'])->name('destroy');
    });

    // Phase EDU-11 — Features
    Route::prefix('features')->name('features.')->group(function () {
        Route::get('/',                               [EducationFeatureController::class, 'index'])->name('index');
        Route::post('/discover',                      [EducationFeatureController::class, 'runDiscovery'])->name('discover');
        Route::post('/{feature}/generate-doc',        [EducationFeatureController::class, 'generateDoc'])->name('generate-doc');
        Route::post('/{feature}/regenerate-doc',      [EducationFeatureController::class, 'regenerateDoc'])->name('regenerate-doc');
    });

    // Phase EDU-11 — Recommendations
    Route::prefix('recommendations')->name('recommendations.')->group(function () {
        Route::get('/',                   [EducationRecommendationController::class, 'index'])->name('index');
        Route::post('/generate',          [EducationRecommendationController::class, 'generate'])->name('generate');
        Route::post('/{id}/dismiss',      [EducationRecommendationController::class, 'dismiss'])->name('dismiss');
        Route::post('/{id}/complete',     [EducationRecommendationController::class, 'complete'])->name('complete');
    });

    // Phase EDU-11 — Adoption
    Route::get('adoption', [EducationAdoptionController::class, 'index'])->name('adoption.index');

    // Legacy KB compat
    if (config('education.legacy_kb_compat', true)) {
        Route::redirect('kb',            '/admin/education/articles',        301);
        Route::redirect('kb/create',     '/admin/education/articles/create', 301);
        Route::redirect('kb/categories', '/admin/education/categories',      301);
    }
});
