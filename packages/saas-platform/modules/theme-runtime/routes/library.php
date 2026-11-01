<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\ThemeRuntime\Http\TemplateLibraryMediaController;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;

// Canvas overhaul Unit 5 (ADR-075): public, sessionless, immutable assets.
Route::get('theme-runtime/library/{file}', TemplateLibraryMediaController::class)
    ->where('file', '[a-z0-9-]+\.webp')
    ->name(TemplateLibrary::MEDIA_ROUTE);
