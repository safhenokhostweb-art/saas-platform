<?php

use App\Models\Customer;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeQualityReport;
use App\Models\User;
use App\Services\LicenseServerService;
use App\Services\ThemeBuilder\ThemeBuilderService;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.mysql.database');
if ($database !== 'saas_platform_theme_assignment_staging' || app()->environment('production')) {
    fwrite(STDERR, "Refusing Theme Assignment browser setup for database [{$database}].\n");
    exit(1);
}

Artisan::call('migrate:fresh', ['--force' => true]);

$adminMobile = '09120000091';
$adminPassword = 'theme-assignment-browser-pass';
$admin = User::create([
    'mobile' => $adminMobile,
    'password' => Hash::make($adminPassword),
    'is_admin' => true,
    'is_active' => true,
]);

$makeSite = static function (string $name, string $domain): Site {
    $customer = Customer::create([
        'name' => "{$name} Owner",
        'mobile' => '09' . random_int(100000000, 999999999),
    ]);

    return Site::create([
        'customer_id' => $customer->id,
        'title' => $name,
        'domain' => $domain,
        'is_active' => true,
        'lifecycle_status' => Site::LIFECYCLE_ACTIVE,
    ]);
};

$siteA = $makeSite('Assignment Site A', '127.0.0.1');
$siteB = $makeSite('Assignment Site B', 'site-b.local');

$builder = app(ThemeBuilderService::class);
$makeTheme = static function (string $name, string $slug, string $heading) use ($builder): Theme {
    $theme = Theme::create([
        'name' => $name,
        'slug' => $slug,
        'description' => "Browser acceptance fixture for {$name}",
        'type' => Theme::TYPE_FULL,
        'source' => Theme::SOURCE_MANUAL,
        'category' => Theme::CATEGORY_LANDING,
        'status' => Theme::STATUS_DRAFT,
        'is_marketplace' => true,
        'marketplace_price' => 0,
        'license_type' => Theme::LICENSE_FREE,
        'global_styles' => $builder->defaultGlobalStyles(),
        'header_blocks' => [],
        'footer_blocks' => [],
        'pages' => ['home' => [
            'title' => 'Home',
            'slug' => '',
            'is_home' => true,
            'blocks' => [[
                'id' => $slug . '-heading',
                'type' => 'heading',
                'props' => ['text' => $heading, 'level' => 1],
            ]],
        ]],
    ]);

    // This isolated browser fixture is itself the evidence for responsive
    // rendering, so it cannot run the network-based commercial gate before
    // the local Platform server exists. Create the real immutable release
    // through its canonical writer and attach explicit passing fixture
    // evidence; production publishing still goes through the full gate.
    $release = app(ThemeReleaseService::class)->release(
        $theme,
        '1.0.0',
        'Real Theme Assignment browser fixture.',
    );
    ThemeQualityReport::create([
        'theme_id' => $theme->id,
        'theme_release_id' => $release->id,
        'version' => $release->version,
        'status' => ThemeQualityReport::STATUS_PASSED,
        'checks' => [['code' => 'browser_fixture', 'status' => 'passed', 'severity' => 'blocking']],
        'evaluated_at' => now(),
    ]);
    $theme->update(['status' => Theme::STATUS_PUBLISHED, 'published_at' => now()]);

    return $theme->fresh();
};

$themeA = $makeTheme('Assigned Real Theme', 'assigned-real-theme', 'Assigned Real Theme is active');
$themeB = $makeTheme('Independent Real Theme', 'independent-real-theme', 'Independent Real Theme is active');

// Site B needs platform access so the browser can prove that a valid site
// without a Theme assignment has no Theme entitlement. Site A deliberately
// starts with no PlatformLicense: the admin assignment must create it.
$siteBKey = app(LicenseServerService::class)->issue($siteB, 'site-b.local', now()->addYear());
$siteB->update(['license_key' => $siteBKey]);

echo 'THEME_ASSIGNMENT_FIXTURE:' . json_encode([
    'admin_mobile' => $adminMobile,
    'admin_password' => $adminPassword,
    'admin_id' => $admin->id,
    'site_a_id' => $siteA->id,
    'site_b_id' => $siteB->id,
    'site_b_license_key' => $siteBKey,
    'theme_a_id' => $themeA->id,
    'theme_b_id' => $themeB->id,
    'theme_a_release_id' => $themeA->current_release_id,
    'theme_b_release_id' => $themeB->current_release_id,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
