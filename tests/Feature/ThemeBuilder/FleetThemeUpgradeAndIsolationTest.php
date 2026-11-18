<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationPackage;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\ThemeFleetUpdate;
use App\Models\ThemeLicense;
use App\Models\ThemeRelease;
use App\Services\ThemeBuilder\ThemeFleetUpgradeService;
use App\Services\ThemeBuilder\ThemeInstallGateway;
use App\Services\ThemeBuilder\ThemeInstallResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;
use Tests\TestCase;

class FleetThemeUpgradeAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_distribution_model_scopes(): void
    {
        $m1 = Theme::create([
            'name'               => 'Marketplace Theme',
            'slug'               => 'm-theme',
            'distribution_model' => Theme::DISTRIBUTION_MARKETPLACE,
            'status'             => Theme::STATUS_PUBLISHED,
        ]);

        $m2 = Theme::create([
            'name'               => 'Private Theme',
            'slug'               => 'p-theme',
            'distribution_model' => Theme::DISTRIBUTION_PRIVATE_LICENSE,
            'status'             => Theme::STATUS_PUBLISHED,
        ]);

        $m3 = Theme::create([
            'name'               => 'Free Theme',
            'slug'               => 'f-theme',
            'distribution_model' => Theme::DISTRIBUTION_FREE,
            'status'             => Theme::STATUS_PUBLISHED,
        ]);

        $this->assertTrue(Theme::marketplace()->where('id', $m1->id)->exists());
        $this->assertFalse(Theme::marketplace()->where('id', $m2->id)->exists());

        $this->assertTrue(Theme::privateLicense()->where('id', $m2->id)->exists());
        $this->assertFalse(Theme::privateLicense()->where('id', $m1->id)->exists());

        $this->assertTrue(Theme::freeDistribution()->where('id', $m3->id)->exists());
    }

    public function test_template_library_isolates_by_theme_slug(): void
    {
        $allCards = TemplateLibrary::list();
        $this->assertNotEmpty($allCards);

        // When requesting for food-mart, solar templates must NOT appear
        $foodCards = TemplateLibrary::list(activeThemeSlug: 'food-mart');
        foreach ($foodCards as $card) {
            $this->assertNotSame('solar-industry', $card['theme_slug'] ?? null, "Solar template {$card['id']} must not bleed into food-mart!");
        }

        // When requesting for solar-industry, solar templates MUST appear
        $solarCards = TemplateLibrary::list(activeThemeSlug: 'solar-industry');
        $hasSolar = false;
        foreach ($solarCards as $card) {
            if (($card['theme_slug'] ?? null) === 'solar-industry') {
                $hasSolar = true;
                break;
            }
        }
        $this->assertTrue($hasSolar, "Solar templates must appear for solar-industry active theme");
    }

    public function test_fleet_discovery_identifies_theme_and_package_updates(): void
    {
        $theme = Theme::create([
            'name'               => 'Solar Industry',
            'slug'               => 'solar-industry',
            'distribution_model' => Theme::DISTRIBUTION_MARKETPLACE,
            'status'             => Theme::STATUS_PUBLISHED,
        ]);

        $rel1 = ThemeRelease::create([
            'theme_id'     => $theme->id,
            'version'      => '1.0.0',
            'changelog'    => 'v1.0.0 release',
            'status'       => ThemeRelease::STATUS_SUPERSEDED,
            'snapshot'     => ['pages' => []],
            'published_at' => now()->subDays(5),
        ]);

        $rel2 = ThemeRelease::create([
            'theme_id'     => $theme->id,
            'version'      => '1.1.0',
            'changelog'    => 'v1.1.0 release',
            'status'       => ThemeRelease::STATUS_PUBLISHED,
            'snapshot'     => ['pages' => []],
            'published_at' => now(),
        ]);

        $theme->forceFill(['current_release_id' => $rel2->id])->save();

        $customer = Customer::create([
            'name'   => 'Solar Owner',
            'mobile' => '09123456781',
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'Solar Customer',
            'domain'      => 'solar.example.com',
            'theme_id'    => $theme->id,
        ]);

        // License and assignment at v1.0.0
        ThemeLicense::grant($site->id, $theme->id);
        ThemeLicense::where('site_id', $site->id)->where('theme_id', $theme->id)->update([
            'installed_release_id' => $rel1->id,
        ]);

        ThemeAssignment::create([
            'site_id'          => $site->id,
            'theme_id'         => $theme->id,
            'theme_release_id' => $rel1->id,
            'domain'           => 'solar.example.com',
            'source'           => ThemeAssignment::SOURCE_ADMIN,
            'status'           => ThemeAssignment::STATUS_ASSIGNED,
        ]);

        // Core package
        CustomerInstallationPackage::create([
            'version'      => '1.2.0',
            'arvan_key'    => 'packages/core-1.2.0.tar.gz',
            'sha256'       => str_repeat('a', 64),
            'size_bytes'   => 1024000,
            'is_active'    => true,
            'released_at'  => now(),
        ]);

        CustomerInstallation::create([
            'site_id'          => $site->id,
            'remote_server_id' => 1,
            'installer_user_id'=> 1,
            'status'           => 'complete',
            'customer_company' => 'Solar Co',
            'customer_email'   => 'user@solar.com',
            'customer_domain'  => 'solar.example.com',
            'package_version'  => '1.1.0',
        ]);

        $service = app(ThemeFleetUpgradeService::class);
        $discovered = $service->discoverAvailableUpdates();

        $this->assertCount(1, $discovered['themes']);
        $this->assertSame($site->id, $discovered['themes'][0]['site_id']);
        $this->assertSame('1.0.0', $discovered['themes'][0]['installed_version']);
        $this->assertSame('1.1.0', $discovered['themes'][0]['available_version']);
        $this->assertTrue($discovered['themes'][0]['eligible']);

        $this->assertCount(1, $discovered['packages']);
        $this->assertSame('1.1.0', $discovered['packages'][0]['current_version']);
        $this->assertSame('1.2.0', $discovered['packages'][0]['available_version']);
    }

    public function test_eligibility_guard_prevents_mismatched_theme_or_missing_license(): void
    {
        $themeA = Theme::create([
            'name'               => 'Solar Industry',
            'slug'               => 'solar-industry',
            'distribution_model' => Theme::DISTRIBUTION_PRIVATE_LICENSE,
            'status'             => Theme::STATUS_PUBLISHED,
        ]);

        $themeB = Theme::create([
            'name'               => 'Food Mart',
            'slug'               => 'food-mart',
            'distribution_model' => Theme::DISTRIBUTION_MARKETPLACE,
            'status'             => Theme::STATUS_PUBLISHED,
        ]);

        $relA = ThemeRelease::create([
            'theme_id'     => $themeA->id,
            'version'      => '1.1.0',
            'changelog'    => 'v1.1.0 release',
            'status'       => ThemeRelease::STATUS_PUBLISHED,
            'snapshot'     => ['pages' => []],
            'published_at' => now(),
        ]);

        $customer = Customer::create([
            'name'   => 'Food Owner',
            'mobile' => '09123456782',
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'Food Site',
            'domain'      => 'food.example.com',
            'theme_id'    => $themeB->id,
        ]);

        $service = app(ThemeFleetUpgradeService::class);

        // Guard 1: Lineage mismatch
        $check1 = $service->checkEligibility($site, $themeA, $relA);
        $this->assertFalse($check1['eligible']);
        $this->assertStringContainsString('قالب نصب‌شده روی این سایت با قالب هدف انتخابی همخوانی ندارد', $check1['reason']);

        // Guard 2: Missing private license
        $site->update(['theme_id' => $themeA->id]);
        $check2 = $service->checkEligibility($site, $themeA, $relA);
        $this->assertFalse($check2['eligible']);
        $this->assertStringContainsString('سایت فاقد لایسنس معتبر', $check2['reason']);

        // When license granted -> eligible
        ThemeLicense::grant($site->id, $themeA->id);
        $check3 = $service->checkEligibility($site, $themeA, $relA);
        $this->assertTrue($check3['eligible']);
    }

    public function test_dispatch_upgrade_records_fleet_update_and_updates_assignment(): void
    {
        $theme = Theme::create([
            'name'               => 'Solar Industry',
            'slug'               => 'solar-industry',
            'distribution_model' => Theme::DISTRIBUTION_MARKETPLACE,
            'status'             => Theme::STATUS_PUBLISHED,
        ]);

        $release = ThemeRelease::create([
            'theme_id'     => $theme->id,
            'version'      => '1.1.0',
            'changelog'    => 'v1.1.0 release',
            'status'       => ThemeRelease::STATUS_PUBLISHED,
            'snapshot'     => ['pages' => []],
            'published_at' => now(),
        ]);

        $customer = Customer::create([
            'name'   => 'Solar Owner 2',
            'mobile' => '09123456783',
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'Solar Site',
            'domain'      => 'solar.example.com',
            'theme_id'    => $theme->id,
        ]);

        // Mock gateway upgrade response
        $mockGateway = $this->createMock(ThemeInstallGateway::class);
        $mockGateway->expects($this->once())
            ->method('upgrade')
            ->with($site, $release)
            ->willReturn(ThemeInstallResult::ok([
                'revision_number'        => 5,
                'reconciliation_metrics' => ['preserved_count' => 8, 'additions_count' => 2],
            ]));

        $service = new ThemeFleetUpgradeService($mockGateway);
        $update = $service->dispatchUpgrade($site, $release);

        $this->assertSame(ThemeFleetUpdate::STATUS_COMPLETED, $update->status);
        $this->assertSame(5, $update->revision_number);
        $this->assertSame(8, $update->reconciliation_summary['preserved_count']);

        // Check assignment updated
        $assignment = ThemeAssignment::where('site_id', $site->id)->where('theme_id', $theme->id)->first();
        $this->assertNotNull($assignment);
        $this->assertSame($release->id, $assignment->theme_release_id);
    }
}
