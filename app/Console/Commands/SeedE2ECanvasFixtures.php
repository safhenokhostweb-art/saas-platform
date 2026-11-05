<?php

namespace App\Console\Commands;

use App\Models\Extension;
use App\Models\Role;
use App\Models\Theme;
use App\Models\User;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

/**
 * Test-infrastructure-only fixture seeder for the Playwright E2E harness
 * covering the Phase 3G-B-6 Theme Canvas Extension-authoring feature.
 *
 * Not part of the shipped application feature — it exists solely to seed a
 * real admin user + commercial Extension/ExtensionRelease + Theme into a
 * local dev database so `tests/browser/*.spec.ts` can drive a real browser
 * session against `php artisan serve`. Every row it creates is
 * clearly-namespaced (mobile/slug prefixed or fixed to an `e2etest`/
 * `e2e-test-*` identity) and idempotent (re-running deletes and recreates
 * its own prior fixtures only — never touches unrelated data).
 *
 * Usage: php artisan e2e:seed-canvas-fixtures
 */
class SeedE2ECanvasFixtures extends Command
{
    protected $signature = 'e2e:seed-canvas-fixtures';

    protected $description = 'Seed admin user + Extension/ExtensionRelease + Theme fixtures for the Playwright Theme Canvas E2E suite (test infra only).';

    public function handle(ExtensionReleaseService $extService, ThemeCommercialQualityGate $qualityGate, ThemeReleaseService $themeReleaseService): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error(
                'e2e:seed-canvas-fixtures refuses to run outside local/testing environments — '
                . 'it creates a real super-admin user with a hardcoded, publicly-known password. '
                . 'Current environment: ' . app()->environment() . '.'
            );

            return self::FAILURE;
        }

        // Browser-backed quality checks must reach the same local HTTP
        // origin as the Playwright harness. Keep production URL generation
        // untouched; this command is local/testing-only fixture machinery.
        if ($browserBaseUrl = env('PLAYWRIGHT_BASE_URL')) {
            $browserBaseUrl = rtrim($browserBaseUrl, '/');
            config(['app.url' => $browserBaseUrl]);
            URL::forceRootUrl($browserBaseUrl);
        }

        $mobile = '09120000091';

        // withTrashed(): User uses SoftDeletes, so a prior run's soft-deleted
        // row would otherwise still collide with the mobile unique index.
        $existingUser = User::withTrashed()->where('mobile', $mobile)->first();
        if ($existingUser) {
            $existingUser->forceDelete();
        }

        $admin = User::create([
            'mobile' => $mobile,
            'password' => Hash::make('E2eTestPass!123'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        $role = Role::where('slug', Role::SUPER_ADMIN)->first();
        if ($role) {
            $admin->roles()->attach($role->id);
        }

        $vendorSlug = 'e2etest';
        $packageSlug = 'gallery-suite';

        $existingExt = Extension::where('vendor_slug', $vendorSlug)->where('package_slug', $packageSlug)->first();
        if ($existingExt) {
            DB::table('extension_releases')->where('extension_id', $existingExt->id)->delete();
            DB::table('extensions')->where('id', $existingExt->id)->delete();
        }

        $ext = $extService->createExtension(
            $vendorSlug,
            $packageSlug,
            'E2E Test Gallery Suite',
            'Fixture extension for Playwright E2E harness — safe to delete.'
        );

        $manifest = [
            'elements' => [
                [
                    'type' => 'ext:e2etest:gallery',
                    'label' => 'گالری E2E',
                    'category' => 'content',
                    'icon' => 'gallery',
                    'is_container' => false,
                    'controls' => [
                        'title' => ['control_type' => 'text', 'label' => 'عنوان', 'default' => ''],
                        'limit' => ['control_type' => 'number', 'label' => 'تعداد', 'default' => 6],
                        'featured' => ['control_type' => 'toggle', 'label' => 'ویژه', 'default' => false],
                        'layout' => ['control_type' => 'select', 'label' => 'چیدمان', 'default' => 'grid', 'options' => ['grid' => 'گرید', 'list' => 'لیست']],
                        'tint' => ['control_type' => 'color', 'label' => 'رنگ', 'default' => '#4f46e5'],
                        'cover' => ['control_type' => 'media', 'label' => 'تصویر جلد', 'default' => ''],
                    ],
                ],
                [
                    'type' => 'ext:e2etest:card_group',
                    'label' => 'گروه کارت E2E',
                    'category' => 'content',
                    'icon' => 'card_group',
                    'is_container' => true,
                    'controls' => [
                        'heading' => ['control_type' => 'text', 'label' => 'عنوان گروه', 'default' => ''],
                    ],
                ],
            ],
        ];

        $release = $extService->release($ext, '1.0.0', 1, $manifest);

        $themeSlug = 'e2e-test-theme';
        $existingTheme = Theme::where('slug', $themeSlug)->first();
        if ($existingTheme) {
            // A fixture that has completed the real Canvas publish proof now
            // owns immutable ThemeRelease ledger rows, whose FK correctly
            // prevents deleting the parent Theme. Preserve that evidence and
            // free only the deterministic fixture slug for the next clean run.
            if ($existingTheme->releases()->exists()) {
                $existingTheme->update([
                    'slug' => "{$themeSlug}-archived-{$existingTheme->id}",
                ]);
            } else {
                $existingTheme->delete();
            }
        }

        $theme = Theme::create([
            'name' => 'قالب تست E2E پلی‌رایت',
            'slug' => $themeSlug,
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [['type' => 'text', 'props' => ['text' => 'E2E initial header']]],
            'footer_blocks' => [['type' => 'text', 'props' => ['text' => 'E2E initial footer']]],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [['type' => 'hero', 'props' => ['title' => 'E2E initial page']]]]],
            'global_styles' => [],
            'settings' => [
                'extension_selections' => [
                    'e2etest/gallery-suite' => ['version' => '1.0.0'],
                ],
            ],
        ]);

        $fixtureThemeRelease = $qualityGate->publishIfCompliant(
            $theme,
            $themeReleaseService,
            '1.0.0',
            'E2E fixture prerequisite',
            null,
            null,
            null,
            $admin->id
        );
        $theme->refresh();
        $fixtureQualityReport = $fixtureThemeRelease->qualityReports()
            ->where('status', \App\Models\ThemeQualityReport::STATUS_PASSED)
            ->latest('id')
            ->first();

        if (
            ! $fixtureQualityReport
            || $theme->current_release_id !== $fixtureThemeRelease->id
            || ! $theme->isCommerciallyEligible()
        ) {
            throw new \RuntimeException('E2E Theme prerequisite release did not establish canonical commercial eligibility.');
        }

        // Phase 3G-B-6B (ADR-048) — a SECOND, independent commercial
        // Extension/Theme pair dedicated to the real-release-backed asset
        // -distribution browser proof (tests/browser/theme-canvas-
        // extension-assets.spec.ts): TWO real ExtensionReleases (1.0.0 and
        // 2.0.0), each with its own version-namespaced, materially
        // materialized CSS/JS (distinct marker colors), published through
        // the exact same ExtensionReleaseService::release() path a real
        // commercial publication would use — never a hand-placed fixture
        // file. A dedicated Theme is pinned to 1.0.0 (with the element
        // already placed) so the browser proof can show that publishing
        // 2.0.0 — which becomes current_release_id — never changes what
        // that Theme's preview resolves or serves.
        $assetVendor = 'e2etest';
        $assetPackage = 'asset-suite';

        $existingAssetExt = Extension::where('vendor_slug', $assetVendor)->where('package_slug', $assetPackage)->first();
        if ($existingAssetExt) {
            DB::table('extension_releases')->where('extension_id', $existingAssetExt->id)->delete();
            DB::table('extensions')->where('id', $existingAssetExt->id)->delete();
        }

        $assetExt = $extService->createExtension(
            $assetVendor,
            $assetPackage,
            'E2E Asset Distribution Suite',
            'Fixture extension for the B-6B asset-distribution Playwright proof — safe to delete.'
        );

        $tempAssetFile = function (string $contents, string $extension): string {
            $path = tempnam(\App\Services\ThemeBuilder\Extension\ExtensionAssetMaterializer::stagingRoot(), 'e2e-ext-asset-') . '.' . $extension;
            file_put_contents($path, $contents);

            return $path;
        };

        $prefixV1 = "/vendor/{$assetVendor}/{$assetPackage}/1.0.0/";
        $releaseV1 = $extService->release($assetExt, '1.0.0', 1, [
            'elements' => [[
                'type' => 'ext:e2etest:asset_widget',
                'label' => 'ابزارک دارایی E2E',
                'category' => 'content',
                'is_container' => false,
                'controls' => [],
                'has_assets' => true,
                'assets' => [
                    'css' => ["{$prefixV1}widget.css"],
                    'js' => ["{$prefixV1}widget.js"],
                ],
            ]],
        ], null, [
            "{$prefixV1}widget.css" => $tempAssetFile(
                "body::after{content:\"\";position:fixed;top:0;left:0;width:8px;height:8px;background-color:rgb(18,52,86);z-index:99999;pointer-events:none;}",
                'css'
            ),
            "{$prefixV1}widget.js" => $tempAssetFile(
                "(function(){document.body.setAttribute('data-e2e-ext-js-loaded','v1');})();",
                'js'
            ),
        ]);

        $prefixV2 = "/vendor/{$assetVendor}/{$assetPackage}/2.0.0/";
        $releaseV2 = $extService->release($assetExt->fresh(), '2.0.0', 1, [
            'elements' => [[
                'type' => 'ext:e2etest:asset_widget',
                'label' => 'ابزارک دارایی E2E',
                'category' => 'content',
                'is_container' => false,
                'controls' => [],
                'has_assets' => true,
                'assets' => [
                    'css' => ["{$prefixV2}widget.css"],
                    'js' => ["{$prefixV2}widget.js"],
                ],
            ]],
        ], null, [
            "{$prefixV2}widget.css" => $tempAssetFile(
                "body::after{content:\"\";position:fixed;top:0;left:0;width:8px;height:8px;background-color:rgb(220,38,38);z-index:99999;pointer-events:none;}",
                'css'
            ),
            "{$prefixV2}widget.js" => $tempAssetFile(
                "(function(){document.body.setAttribute('data-e2e-ext-js-loaded','v2');})();",
                'js'
            ),
        ]);

        $assetThemeSlug = 'e2e-test-asset-theme';
        $existingAssetTheme = Theme::where('slug', $assetThemeSlug)->first();
        if ($existingAssetTheme) {
            // Same rule as the Canvas fixture theme above: release ledger
            // rows block the delete, so keep them and free the slug.
            if ($existingAssetTheme->releases()->exists()) {
                $existingAssetTheme->update([
                    'slug' => "{$assetThemeSlug}-archived-{$existingAssetTheme->id}",
                ]);
            } else {
                $existingAssetTheme->delete();
            }
        }

        // Pinned to 1.0.0 with the element already placed — current_release_id
        // is 2.0.0 (just published above), proving the pin, not the pointer,
        // governs what this Theme resolves and serves.
        $assetTheme = Theme::create([
            'name' => 'قالب تست دارایی E2E پلی‌رایت',
            'slug' => $assetThemeSlug,
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
            'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [
                ['id' => 'asset-widget-1', 'type' => 'ext:e2etest:asset_widget', 'props' => []],
            ]]],
            'global_styles' => [],
            'settings' => [
                'extension_selections' => [
                    "{$assetVendor}/{$assetPackage}" => ['version' => '1.0.0'],
                ],
            ],
        ]);

        $this->line(json_encode([
            'admin_user_id' => $admin->id,
            'admin_mobile' => $mobile,
            'admin_password' => 'E2eTestPass!123',
            'extension_id' => $ext->id,
            'extension_identifier' => $ext->identifier(),
            'release_id' => $release->id,
            'release_version' => $release->version,
            'theme_id' => $theme->id,
            'theme_slug' => $theme->slug,
            'theme_release_id' => $fixtureThemeRelease->id,
            'theme_release_version' => $fixtureThemeRelease->version,
            'theme_quality_report_id' => $fixtureQualityReport->id,
            'theme_quality_report_status' => $fixtureQualityReport->status,
            'theme_commercially_eligible' => true,
            'asset_theme_id' => $assetTheme->id,
            'asset_theme_slug' => $assetTheme->slug,
            'asset_extension_identifier' => $assetExt->identifier(),
            'asset_release_v1_id' => $releaseV1->id,
            'asset_release_v2_id' => $releaseV2->id,
            'asset_v1_css_path' => "{$prefixV1}widget.css",
            'asset_v1_js_path' => "{$prefixV1}widget.js",
            'asset_v2_css_path' => "{$prefixV2}widget.css",
            'asset_v2_js_path' => "{$prefixV2}widget.js",
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
