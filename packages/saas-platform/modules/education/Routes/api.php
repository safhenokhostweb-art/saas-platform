<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Education\Http\Controllers\Api\MobileEducationController;

Route::middleware(['api'])->prefix('api/v1/education')->name('api.education.')->group(function () {
    Route::get('/articles',           [MobileEducationController::class, 'articles'])->name('articles');
    Route::get('/articles/{id}',      [MobileEducationController::class, 'show'])->name('articles.show');
    Route::get('/search',             [MobileEducationController::class, 'search'])->name('search');
    Route::get('/learning-paths',     [MobileEducationController::class, 'learningPaths'])->name('learning-paths');
    Route::post('/articles/{id}/progress', [MobileEducationController::class, 'markProgress'])->name('articles.progress');
});
