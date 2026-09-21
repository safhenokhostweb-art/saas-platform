<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Customer;
use App\Models\PlatformLicense;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\ThemeLicense;
use App\Models\User;
use App\Services\LicenseServerService;
use App\Services\PublishedThemeAssignmentService;
use App\Services\ThemeBuilder\ThemeBuilderService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PublishedThemeAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PublishedThemeAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PublishedThemeAssignmentService::class);
    }

    private function site(string $domain): Site
    {
        $customer = Customer::create([
            'name' => 'Customer ' . $domain,
            'mobile' => '09' . random_int(100000000, 999999999),
        ]);

        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'Site ' . $domain,
            'domain' => $domain,
            'is_active' => true,
        ]);
    }

    private function publishedTheme(string $slug, string $version = '1.0.0'): Theme
    {
        $theme = Theme::create([
            'name' => 'Theme ' . $slug,
            'slug' => $slug,
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_MANUAL,
            'category' => Theme::CATEGORY_LANDING,
            'status' => Theme::STATUS_DRAFT,
            'is_marketplace' => true,
            'license_type' => Theme::LICENSE_FREE,
            'global_styles' => app(ThemeBuilderService::class)->defaultGlobalStyles(),
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => [
                'title' => 'Home', 'slug' => '', 'is_home' => true,
                'blocks' => [['id' => 'heading', 'type' => 'heading', 'props' => ['text' => $slug, 'level' => 1]]],
            ]],
        ]);

        app(ThemeBuilderService::class)->publish($theme, $version, 'assignment test');

        return $theme->fresh();
    }

    public function test_initial_assignment_creates_all_three_scoped_records(): void
    {
        $site = $this->site('https://site-a.test/path');
        $theme = $this->publishedTheme('theme-a');

        $result = $this->service->assign($site, $theme);

        $this->assertTrue($result->platformLicenseCreated);
        $this->assertTrue($result->themeLicenseCreated);
        $this->assertTrue($result->assignmentCreated);
        $this->assertSame('site-a.test', $result->assignment->domain);
        $this->assertSame($theme->current_release_id, $result->assignment->theme_release_id);
        $this->assertSame($theme->current_release_id, $result->themeLicense->licensed_release_id);
        $this->assertNull($result->themeLicense->installed_release_id);
        $this->assertSame($theme->id, $site->fresh()->theme_id);
        $this->assertNotEmpty($site->fresh()->license_key);
    }

    public function test_repeated_assignment_is_idempotent(): void
    {
        $site = $this->site('repeat.test');
        $theme = $this->publishedTheme('repeat-theme');

        $first = $this->service->assign($site, $theme);
        $second = $this->service->assign($site->fresh(), $theme->fresh());

        $this->assertSame($first->assignment->id, $second->assignment->id);
        $this->assertSame($first->platformLicense->id, $second->platformLicense->id);
        $this->assertSame($first->themeLicense->id, $second->themeLicense->id);
        $this->assertFalse($second->assignmentCreated);
        $this->assertFalse($second->platformLicenseCreated);
        $this->assertFalse($second->themeLicenseCreated);
        $this->assertDatabaseCount('theme_assignments', 1);
        $this->assertDatabaseCount('theme_licenses', 1);
        $this->assertDatabaseCount('platform_licenses', 1);
    }

    public function test_marketplace_acquisition_reuses_platform_license_without_switching_active_theme(): void
    {
        $site = $this->site('marketplace-acquisition.test');
        $previousTheme = $this->publishedTheme('previous-active-theme');
        $marketplaceTheme = $this->publishedTheme('marketplace-theme');
        $site->update(['theme_id' => $previousTheme->id]);

        $platformKey = app(LicenseServerService::class)->issue($site, 'marketplace-acquisition.test', now()->addYear());
        $site->update(['license_key' => $platformKey]);

        $response = $this->postJson(
            "/api/theme-marketplace/{$marketplaceTheme->id}/initiate-purchase",
            ['redirect_back_url' => 'https://marketplace-acquisition.test/admin/themes'],
            [
                'Authorization' => 'Bearer ' . $platformKey,
                'X-Tenant-Domain' => 'marketplace-acquisition.test',
                'Accept' => 'application/json',
            ],
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('free', true)
            ->assertJsonPath('release_id', $marketplaceTheme->current_release_id);

        $this->assertSame($previousTheme->id, $site->fresh()->theme_id);
        $this->assertDatabaseCount('platform_licenses', 1);
        $this->assertDatabaseHas('theme_licenses', [
            'site_id' => $site->id,
            'theme_id' => $marketplaceTheme->id,
            'licensed_release_id' => $marketplaceTheme->current_release_id,
            'installed_release_id' => null,
        ]);
        $this->assertDatabaseHas('theme_assignments', [
            'site_id' => $site->id,
            'theme_id' => $marketplaceTheme->id,
            'theme_release_id' => $marketplaceTheme->current_release_id,
            'source' => ThemeAssignment::SOURCE_MARKETPLACE,
        ]);
    }

    public function test_admin_manual_grant_uses_the_canonical_assignment_workflow(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $site = $this->site('admin-assignment.test');
        $theme = $this->publishedTheme('admin-assigned-theme');

        $payload = ['site_id' => $site->id, 'theme_id' => $theme->id];
        $this->actingAs($admin)
            ->post(route('admin.theme-marketplace.grant-license'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->post(route('admin.theme-marketplace.grant-license'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($theme->id, $site->fresh()->theme_id);
        $this->assertDatabaseCount('platform_licenses', 1);
        $this->assertDatabaseCount('theme_licenses', 1);
        $this->assertDatabaseCount('theme_assignments', 1);
        $this->assertDatabaseHas('theme_assignments', [
            'site_id' => $site->id,
            'theme_id' => $theme->id,
            'theme_release_id' => $theme->current_release_id,
            'source' => ThemeAssignment::SOURCE_ADMIN,
            'assigned_by' => $admin->id,
        ]);
    }

    public function test_two_themes_and_two_sites_are_fully_isolated(): void
    {
        $siteA = $this->site('isolation-a.test');
        $siteB = $this->site('isolation-b.test');
        $themeA = $this->publishedTheme('isolated-theme-a');
        $themeB = $this->publishedTheme('isolated-theme-b');

        $a = $this->service->assign($siteA, $themeA);
        $b = $this->service->assign($siteB, $themeB);

        $this->assertNotSame($a->platformLicense->id, $b->platformLicense->id);
        $this->assertNotSame($a->themeLicense->license_key, $b->themeLicense->license_key);
        $this->assertDatabaseMissing('theme_assignments', ['site_id' => $siteA->id, 'theme_id' => $themeB->id]);
        $this->assertDatabaseMissing('theme_assignments', ['site_id' => $siteB->id, 'theme_id' => $themeA->id]);
    }

    public function test_draft_theme_is_rejected_without_any_partial_write(): void
    {
        $site = $this->site('draft.test');
        $theme = Theme::create(['name' => 'Draft', 'slug' => 'draft', 'status' => Theme::STATUS_DRAFT]);

        try {
            $this->service->assign($site, $theme);
            $this->fail('Draft Theme assignment should throw.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('پیش‌نویس', $exception->getMessage());
        }

        $this->assertDatabaseCount('theme_assignments', 0);
        $this->assertDatabaseCount('theme_licenses', 0);
        $this->assertDatabaseCount('platform_licenses', 0);
    }

    public function test_published_theme_without_a_release_is_rejected(): void
    {
        $site = $this->site('no-release.test');
        $theme = Theme::create(['name' => 'No release', 'slug' => 'no-release', 'status' => Theme::STATUS_PUBLISHED]);

        $this->expectException(DomainException::class);
        $this->service->assign($site, $theme);
    }

    public function test_new_release_reuses_license_and_preserves_assignment_lineage(): void
    {
        $site = $this->site('upgrade.test');
        $theme = $this->publishedTheme('upgrade-theme');
        $first = $this->service->assign($site, $theme);
        $firstReleaseId = $first->release->id;

        app(ThemeBuilderService::class)->publish($theme->fresh(), '2.0.0', 'new release');
        $second = $this->service->assign($site->fresh(), $theme->fresh());

        $this->assertNotSame($firstReleaseId, $second->release->id);
        $this->assertNotSame($first->assignment->id, $second->assignment->id);
        $this->assertSame($first->themeLicense->id, $second->themeLicense->id);
        $this->assertSame($second->release->id, $second->assignment->theme_release_id);
        $this->assertSame($second->release->id, $second->themeLicense->licensed_release_id);
        $this->assertNull($second->themeLicense->installed_release_id);
        $this->assertDatabaseHas('theme_assignments', [
            'id' => $first->assignment->id,
            'theme_release_id' => $firstReleaseId,
            'status' => ThemeAssignment::STATUS_SUPERSEDED,
        ]);
        $this->assertDatabaseHas('theme_assignments', [
            'id' => $second->assignment->id,
            'theme_release_id' => $second->release->id,
            'status' => ThemeAssignment::STATUS_ASSIGNED,
        ]);
        $this->assertDatabaseCount('theme_assignments', 2);
    }

    public function test_invalid_existing_platform_license_is_preserved_and_replaced(): void
    {
        $site = $this->site('invalid-license.test');
        $oldKey = app(LicenseServerService::class)->issue($site, 'invalid-license.test');
        $old = app(LicenseServerService::class)->findByKey($oldKey);
        $old->update(['revoked_at' => now()]);
        $site->update(['license_key' => $oldKey]);

        $result = $this->service->assign($site->fresh(), $this->publishedTheme('replacement-theme'));

        $this->assertTrue($result->platformLicenseCreated);
        $this->assertNotSame($old->id, $result->platformLicense->id);
        $this->assertNotNull($old->fresh()->revoked_at);
        $this->assertDatabaseCount('platform_licenses', 2);
    }

    public function test_expired_existing_platform_license_is_preserved_and_replaced(): void
    {
        $site = $this->site('expired-license.test');
        $oldKey = app(LicenseServerService::class)->issue($site, 'expired-license.test');
        $old = app(LicenseServerService::class)->findByKey($oldKey);
        $old->update(['expires_at' => now()->subDay()]);
        $site->update(['license_key' => $oldKey]);

        $result = $this->service->assign($site->fresh(), $this->publishedTheme('expired-replacement-theme'));

        $this->assertTrue($result->platformLicenseCreated);
        $this->assertNotSame($old->id, $result->platformLicense->id);
        $this->assertTrue($old->fresh()->isExpired());
        $this->assertDatabaseCount('platform_licenses', 2);
    }

    public function test_domain_mismatch_never_reuses_the_foreign_domain_license(): void
    {
        $site = $this->site('correct-domain.test');
        $wrongKey = app(LicenseServerService::class)->issue($site, 'wrong-domain.test');
        $wrong = app(LicenseServerService::class)->findByKey($wrongKey);
        $site->update(['license_key' => $wrongKey]);

        $result = $this->service->assign($site->fresh(), $this->publishedTheme('domain-theme'));

        $this->assertNotSame($wrong->id, $result->platformLicense->id);
        $this->assertSame('correct-domain.test', $result->platformLicense->domain_allowed);
        $this->assertFalse($wrong->isDomainAllowed('correct-domain.test'));
    }

    public function test_unassign_removes_only_the_target_assignment_and_retains_entitlements(): void
    {
        $site = $this->site('unassign.test');
        $themeA = $this->publishedTheme('unassign-a');
        $themeB = $this->publishedTheme('unassign-b');
        $this->service->assign($site, $themeA);
        $this->service->assign($site->fresh(), $themeB);

        $this->service->unassign($site->fresh(), $themeA);

        $this->assertDatabaseMissing('theme_assignments', ['site_id' => $site->id, 'theme_id' => $themeA->id]);
        $this->assertDatabaseHas('theme_assignments', ['site_id' => $site->id, 'theme_id' => $themeB->id]);
        $this->assertDatabaseHas('theme_licenses', ['site_id' => $site->id, 'theme_id' => $themeA->id]);
        $this->assertDatabaseHas('theme_licenses', ['site_id' => $site->id, 'theme_id' => $themeB->id]);
        $this->assertSame($themeB->id, $site->fresh()->theme_id);
    }

    public function test_revoke_removes_only_the_target_theme_entitlement(): void
    {
        $site = $this->site('revoke-scope.test');
        $themeA = $this->publishedTheme('revoke-theme-a');
        $themeB = $this->publishedTheme('revoke-theme-b');
        $assignmentA = $this->service->assign($site, $themeA);
        $assignmentB = $this->service->assign($site->fresh(), $themeB);

        $this->service->revoke($assignmentA->themeLicense);

        $this->assertDatabaseMissing('theme_licenses', ['id' => $assignmentA->themeLicense->id]);
        $this->assertDatabaseMissing('theme_assignments', ['id' => $assignmentA->assignment->id]);
        $this->assertDatabaseHas('theme_licenses', ['id' => $assignmentB->themeLicense->id]);
        $this->assertDatabaseHas('theme_assignments', ['id' => $assignmentB->assignment->id]);
        $this->assertSame($themeB->id, $site->fresh()->theme_id);
        $this->assertDatabaseCount('platform_licenses', 1);
    }

    public function test_mid_transaction_failure_rolls_back_every_created_record_and_site_key(): void
    {
        $site = $this->site('rollback.test');
        $theme = $this->publishedTheme('rollback-theme');
        ThemeAssignment::creating(static function (): void {
            throw new RuntimeException('simulated assignment persistence failure');
        });

        try {
            $this->service->assign($site, $theme);
            $this->fail('The simulated failure should propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated assignment persistence failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('theme_assignments', 0);
        $this->assertDatabaseCount('theme_licenses', 0);
        $this->assertDatabaseCount('platform_licenses', 0);
        $this->assertNull($site->fresh()->license_key);
        $this->assertNull($site->fresh()->theme_id);
    }
}
