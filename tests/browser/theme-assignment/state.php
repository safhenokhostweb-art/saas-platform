<?php

use App\Models\Site;
use App\Models\ThemeAssignment;
use App\Models\ThemeLicense;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.mysql.database');
if ($database !== 'saas_platform_theme_assignment_staging' || app()->environment('production')) {
    fwrite(STDERR, "Refusing Theme Assignment state export for database [{$database}].\n");
    exit(1);
}

$siteA = Site::findOrFail((int) getenv('THEME_ASSIGNMENT_SITE_A'));
$siteB = Site::findOrFail((int) getenv('THEME_ASSIGNMENT_SITE_B'));

echo 'THEME_ASSIGNMENT_STATE:' . json_encode([
    'site_a_license_key' => $siteA->license_key,
    'site_b_license_key' => $siteB->license_key,
    'site_a_theme_id' => $siteA->theme_id,
    'site_b_theme_id' => $siteB->theme_id,
    'site_a_platform_licenses' => $siteA->platformLicenses()->count(),
    'site_b_platform_licenses' => $siteB->platformLicenses()->count(),
    'site_a_theme_licenses' => ThemeLicense::where('site_id', $siteA->id)->count(),
    'site_b_theme_licenses' => ThemeLicense::where('site_id', $siteB->id)->count(),
    'site_a_assignments' => ThemeAssignment::where('site_id', $siteA->id)->count(),
    'site_b_assignments' => ThemeAssignment::where('site_id', $siteB->id)->count(),
    'cross_site_theme_licenses' => ThemeLicense::where('site_id', $siteB->id)
        ->whereIn('theme_id', ThemeLicense::where('site_id', $siteA->id)->select('theme_id'))
        ->count(),
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
