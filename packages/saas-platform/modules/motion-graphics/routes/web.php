<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\MotionGraphics\Http\Controllers\MotionGraphicAdminController;
use SaaSPlatform\Modules\MotionGraphics\Http\Controllers\MotionGraphicRuntimeController;

if (config('motion-graphics.admin_ui_enabled', false)) {
    Route::middleware(config('motion-graphics.admin_middleware', ['web']))
        ->prefix('motion-graphics-admin')
        ->name('motion-graphics.admin.')
        ->group(function (): void {
            Route::get('/', [MotionGraphicAdminController::class, 'index'])->name('index');
            Route::post('/', [MotionGraphicAdminController::class, 'store'])->name('store');
            Route::post('{motion}/versions', [MotionGraphicAdminController::class, 'createVersion'])->name('versions.store');
            Route::post('{motion}/ai-edit', [MotionGraphicAdminController::class, 'aiEdit'])->name('ai-edit');
            Route::post('{motion}/versions/{version}/approve', [MotionGraphicAdminController::class, 'approve'])->name('approve');
            Route::post('{motion}/versions/{version}/publish', [MotionGraphicAdminController::class, 'publish'])->name('publish');
            Route::post('{motion}/versions/{version}/rollback', [MotionGraphicAdminController::class, 'rollback'])->name('rollback');
            Route::post('{motion}/deployments', [MotionGraphicAdminController::class, 'deploy'])->name('deployments.store');
        });
}

if (config('motion-graphics.runtime_api_enabled', false)) {
    Route::middleware(config('motion-graphics.runtime_middleware', ['api']))
        ->prefix('motion-runtime')
        ->name('motion-graphics.runtime.')
        ->group(function (): void {
            Route::get('{slug}', [MotionGraphicRuntimeController::class, 'show'])->name('show');
        });
}
