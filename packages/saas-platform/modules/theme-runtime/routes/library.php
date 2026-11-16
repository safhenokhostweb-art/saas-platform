<?php

use Illuminate\Support\Facades\Route;
use SaaSPlatform\ThemeRuntime\Http\TemplateLibraryMediaController;
use SaaSPlatform\ThemeRuntime\Http\VendorAssetController;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;
use SaaSPlatform\ThemeRuntime\VendorAssets;

// Canvas overhaul Unit 5 (ADR-075): public, sessionless, immutable assets.
Route::get('theme-runtime/library/{file}', TemplateLibraryMediaController::class)
    ->where('file', '[a-z0-9-]+\.webp')
    ->name(TemplateLibrary::MEDIA_ROUTE);

// Third-party browser assets vendored in the package (VendorAssets): pinned,
// integrity-checked, never loaded from a public CDN.
Route::get('theme-runtime/vendor/{file}', VendorAssetController::class)
    ->where('file', '[a-z0-9.-]+\.js')
    ->name(VendorAssets::ROUTE);
