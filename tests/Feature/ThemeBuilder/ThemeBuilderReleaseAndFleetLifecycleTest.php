<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Customer;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\ThemeFleetUpdate;
use App\Models\ThemeLicense;
use App\Models\ThemeRelease;
use App\Models\User;
use App\Services\ThemeBuilder\ThemeBuilderService;
use App\Services\ThemeBuilder\ThemeFleetUpgradeService;
use App\Services\ThemeBuilder\ThemeInstallGateway;
use Database\Seeders\SolarIndustryThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ThemeBuilderReleaseAndFleetLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $role = \App\Models\Role::firstOrCreate(
            ['slug' => \App\Models\Role::SUPER_ADMIN],
            ['name' => 'Super Admin']
        );
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    public function test_publish_release_persists_snapshot_and_seeder_does_not_overwrite_it(): void
    {
        $this->seed(SolarIndustryThemeSeeder::class);
        $theme = Theme::where('slug', SolarIndustryThemeSeeder::SLUG)->whereNull('site_id')->firstOrFail();

        // Customize theme in Theme Builder
        $pages = $theme->pages;
        $pages['home']['title'] = 'صفحه اصلی ویرایش‌شده در Theme Builder';
        $theme->update(['pages' => $pages]);

        $admin = $this->createAdmin();
        $resp = $this->actingAs($admin)->postJson(route('admin.theme-builder.publish', $theme));
        $resp->assertOk()->assertJsonPath('success', true);

        $theme->refresh();
        $this->assertNotNull($theme->current_release_id);
        $release = ThemeRelease::findOrFail($theme->current_release_id);
        $this->assertSame(
            'صفحه اصلی ویرایش‌شده در Theme Builder',
            $release->snapshot['pages']['home']['title'] ?? null
        );

        // Re-running seeder must NOT overwrite Theme Builder published content
        $this->seed(SolarIndustryThemeSeeder::class);
        $theme->refresh();
        $this->assertSame('صفحه اصلی ویرایش‌شده در Theme Builder', $theme->pages['home']['title'] ?? null);
        $this->assertSame($release->id, $theme->current_release_id);
    }

    public function test_install_gateway_uses_current_release_snapshot_and_fleet_upgrade_dispatches_newer_release(): void
    {
        $this->seed(SolarIndustryThemeSeeder::class);
        $theme = Theme::where('slug', SolarIndustryThemeSeeder::SLUG)->whereNull('site_id')->firstOrFail();

        $admin = $this->createAdmin();
        $this->actingAs($admin)->postJson(route('admin.theme-builder.publish', $theme))->assertOk();
        $theme->refresh();
        $releaseV1 = $theme->currentRelease;

        // Mutate live draft field AFTER release v1 without publishing:
        // ThemeInstallGateway::install() must still push v1's frozen snapshot!
        $draftPages = $theme->pages;
        $draftPages['home']['title'] = 'پیش‌نویس منتشرنشده';
        $theme->update(['pages' => $draftPages]);

        $customer = Customer::create(['name' => 'مشتری تست', 'mobile' => '09120000001']);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت مشتری',
            'domain' => 'tenant-lifecycle.test',
            'theme_id' => $theme->id,
            'status' => 'active',
        ]);

        Http::fake([
            'https://tenant-lifecycle.test/builder/api/theme/install' => Http::response([
                'success' => true,
                'revision_id' => 1,
                'revision_number' => 1,
                'pages_installed' => count($releaseV1->snapshot['pages']),
            ], 200),
            'https://tenant-lifecycle.test/builder/api/theme/upgrade' => Http::response([
                'success' => true,
                'revision_number' => 2,
                'reconciliation_metrics' => ['preserved_customer_pages' => 1, 'deposited_templates' => 2],
            ], 200),
        ]);

        $installResult = app(ThemeInstallGateway::class)->install($theme->fresh('currentRelease'), $site);
        $this->assertTrue($installResult->success);

        Http::assertSent(function ($request) use ($releaseV1) {
            if (!str_ends_with($request->url(), '/builder/api/theme/install')) {
                return false;
            }
            $data = $request->data();
            return ($data['source_release_id'] ?? null) === $releaseV1->id
                && ($data['pages']['home']['title'] ?? null) === ($releaseV1->snapshot['pages']['home']['title'] ?? null)
                && ($data['pages']['home']['title'] ?? null) !== 'پیش‌نویس منتشرنشده';
        });

        // Record v1 assignment & license as onboarding/install does
        ThemeAssignment::create([
            'site_id' => $site->id,
            'theme_id' => $theme->id,
            'theme_release_id' => $releaseV1->id,
            'domain' => $site->domain,
            'source' => ThemeAssignment::SOURCE_ADMIN,
            'status' => ThemeAssignment::STATUS_ASSIGNED,
        ]);
        ThemeLicense::create([
            'site_id' => $site->id,
            'theme_id' => $theme->id,
            'license_key' => 'LIC-TEST-' . uniqid(),
            'status' => 'active',
            'purchased_at' => now(),
            'installed_release_id' => $releaseV1->id,
            'licensed_release_id' => $releaseV1->id,
        ]);

        // Publish v2 from Theme Builder
        $v2Pages = $theme->pages;
        $v2Pages['home']['title'] = 'نسخه رسمی ۲';
        $theme->update(['pages' => $v2Pages]);
        $this->actingAs($admin)->postJson(route('admin.theme-builder.publish', $theme))->assertOk();
        $theme->refresh();
        $releaseV2 = $theme->currentRelease;
        $this->assertNotSame($releaseV1->id, $releaseV2->id);

        // Fleet discovery finds outdated site & dispatchUpgrade applies v2 non-destructively
        $fleetService = app(ThemeFleetUpgradeService::class);
        $discovered = $fleetService->discoverAvailableUpdates();
        $this->assertNotEmpty($discovered['themes']);
        $this->assertSame($releaseV2->id, $discovered['themes'][0]['available_release_id']);

        $update = $fleetService->dispatchUpgrade($site, $releaseV2, $admin->id);
        $this->assertSame(ThemeFleetUpdate::STATUS_COMPLETED, $update->status);
        $this->assertSame($releaseV2->id, $update->to_release_id);
        $this->assertDatabaseHas('theme_assignments', [
            'site_id' => $site->id,
            'theme_id' => $theme->id,
            'theme_release_id' => $releaseV2->id,
        ]);
    }

    public function test_site_without_theme_id_resolves_to_null_and_default_fallback_payload(): void
    {
        $this->seed(SolarIndustryThemeSeeder::class);
        $theme = Theme::where('slug', SolarIndustryThemeSeeder::SLUG)->whereNull('site_id')->firstOrFail();
        $admin = $this->createAdmin();
        $this->actingAs($admin)->postJson(route('admin.theme-builder.publish', $theme))->assertOk();

        $customer = Customer::create(['name' => 'مشتری بدون قالب', 'mobile' => '09120000002']);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت بدون قالب',
            'domain' => 'no-theme-site.test',
            'theme_id' => null,
            'status' => 'active',
        ]);

        $service = app(ThemeBuilderService::class);
        $this->assertNull($service->resolveForSite($site->id));
        $payload = $service->payloadForSite($site->id);
        $this->assertSame('default', $payload['meta']['id']);
        $this->assertSame('بدون قالب (تم پیش‌فرض پایه)', $payload['meta']['name']);
    }
}
