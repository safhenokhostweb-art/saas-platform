<?php

namespace Tests\Feature\Onboarding;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ADR-088 — the unified onboarding wizard: entry points, live validation
 * endpoints, and the submission that starts the provisioning pipeline.
 */
class OnboardingWizardTest extends TestCase
{
    use OnboardingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->platformRootDomain();

        \App\Models\CustomerInstallationPackage::create([
            'version'       => '1.1.0',
            'arvan_key'     => 'packages/customer-site-v1.1.0.zip',
            'sha256'        => str_repeat('a', 64),
            'size_bytes'    => 1024000,
            'is_active'     => true,
            'released_at'   => now(),
            'release_notes' => 'Active test package',
        ]);
    }

    // ── entry points ───────────────────────────────────────────────────

    public function test_the_wizard_renders_with_platform_options(): void
    {
        $this->eligibleTheme();
        $this->server();

        $this->actingAs($this->admin())->get(route('admin.onboarding.create'))
            ->assertOk()
            ->assertSee('راه‌اندازی مشتری و سایت')
            ->assertSee(self::ROOT)
            ->assertSee('solar_industry_kit');
    }

    public function test_every_legacy_onboarding_page_permanently_redirects_to_the_wizard(): void
    {
        $admin = $this->admin();

        foreach (['admin.customers.create', 'admin.sites.create', 'admin.sites.wizard', 'admin.infrastructure.customer-deployment.wizard'] as $route) {
            $this->actingAs($admin)->get(route($route))
                ->assertStatus(301)
                ->assertRedirect(route('admin.onboarding.create'));
        }
    }

    public function test_a_customer_scoped_legacy_link_prefills_that_customers_mobile(): void
    {
        $customer = $this->customer('09127778899');

        $this->actingAs($this->admin())->get(route('admin.sites.create', ['customer_id' => $customer->id]))
            ->assertRedirect(route('admin.onboarding.create', ['mobile' => '09127778899']));
    }

    public function test_non_admins_cannot_reach_the_wizard_or_start_an_onboarding(): void
    {
        $user = User::create(['mobile' => '09120000001', 'password' => bcrypt('x'), 'is_admin' => false, 'is_active' => true]);

        $this->actingAs($user)->get(route('admin.onboarding.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.onboarding.store'), $this->submission($this->server()))->assertForbidden();
        $this->assertSame(0, CustomerInstallation::count());
    }

    // ── live validation ────────────────────────────────────────────────

    public function test_customer_lookup_returns_the_owner_and_every_site_of_a_mobile(): void
    {
        $customer = $this->customer('09121112233', 'رضا کریمی');
        $this->site($customer, 'one.' . self::ROOT);
        $this->site($customer, 'two.example.ir');

        $json = $this->actingAs($this->admin())
            ->getJson(route('admin.onboarding.lookup-customer', ['mobile' => '۰۹۱۲ ۱۱۱ ۲۲۳۳']))
            ->assertOk()->json();

        $this->assertTrue($json['valid']);
        $this->assertSame('09121112233', $json['mobile']);
        $this->assertSame($customer->id, $json['customer']['id']);
        $this->assertEqualsCanonicalizing(['one.' . self::ROOT, 'two.example.ir'], array_column($json['customer']['sites'], 'domain'));
    }

    public function test_customer_lookup_distinguishes_new_and_invalid_mobiles(): void
    {
        $admin = $this->admin();

        $new = $this->actingAs($admin)->getJson(route('admin.onboarding.lookup-customer', ['mobile' => '09350000000']))->json();
        $this->assertTrue($new['valid']);
        $this->assertNull($new['customer']);

        $bad = $this->actingAs($admin)->getJson(route('admin.onboarding.lookup-customer', ['mobile' => '021123']))->json();
        $this->assertFalse($bad['valid']);
    }

    public function test_domain_check_accepts_a_free_platform_subdomain(): void
    {
        $json = $this->actingAs($this->admin())
            ->getJson(route('admin.onboarding.check-domain', ['mode' => 'subdomain', 'domain' => 'Solar-Shop']))
            ->assertOk()->json();

        $this->assertTrue($json['ok']);
        $this->assertSame('solar-shop.' . self::ROOT, $json['domain']);
        $this->assertTrue($json['platform_subdomain']);
    }

    public function test_domain_check_rejects_reserved_malformed_taken_and_in_flight_domains(): void
    {
        $admin = $this->admin();
        $this->site($this->customer(), 'taken.' . self::ROOT);
        CustomerInstallation::create([
            'remote_server_id' => $this->server()->id, 'installer_user_id' => $admin->id, 'install_mode' => 'MODE_FRESH',
            'status' => 'running', 'customer_company' => 'X', 'customer_email' => '', 'customer_domain' => 'busy.' . self::ROOT,
            'request_payload' => [],
        ]);

        foreach (['admin' => 'رزرو', '-bad-' => 'زیردامنه باید', 'ab' => 'زیردامنه باید', 'taken' => 'قبلاً', 'busy' => 'در حال اجرا'] as $label => $needle) {
            $json = $this->actingAs($admin)->getJson(route('admin.onboarding.check-domain', ['mode' => 'subdomain', 'domain' => $label]))->json();
            $this->assertFalse($json['ok'], $label);
            $this->assertStringContainsString($needle, $json['error'], $label);
        }

        $custom = $this->actingAs($admin)->getJson(route('admin.onboarding.check-domain', ['mode' => 'custom', 'domain' => 'not a domain']))->json();
        $this->assertFalse($custom['ok']);
    }

    // ── submission ─────────────────────────────────────────────────────

    public function test_a_new_mobile_creates_the_customer_and_starts_the_pipeline(): void
    {
        $admin = $this->admin();
        $server = $this->server();

        $response = $this->actingAs($admin)->post(route('admin.onboarding.store'), $this->submission($server));

        $installation = CustomerInstallation::query()->sole();
        $response->assertRedirect(route('admin.infrastructure.customer-deployment.installations.show', $installation));

        $customer = Customer::query()->where('mobile', '09351234567')->sole();
        $this->assertSame('سارا محمدی', $customer->name);

        $payload = $installation->request_payload;
        $this->assertSame('pending', $installation->status);
        $this->assertSame('khorshid.' . self::ROOT, $installation->customer_domain);
        $this->assertSame($admin->id, $installation->installer_user_id);
        $this->assertSame($customer->id, $payload['customer_id']);
        $this->assertSame('09351234567', $payload['phone']);
        $this->assertSame('خورشید انرژی پارس', $payload['company']);
        $this->assertSame('professional', $payload['license_type']);
        $this->assertSame(['forms'], $payload['modules']);
        $this->assertSame('MODE_FRESH', $payload['install_mode']);
        $this->assertTrue($payload['ssl_enabled']);

        Queue::assertPushed(CustomerInstallationPipelineJob::class, fn ($job) => $job->installationId === $installation->id);
    }

    public function test_an_existing_mobile_adds_another_site_to_the_same_customer(): void
    {
        $customer = $this->customer('09351234567', 'سارا محمدی');
        $this->site($customer, 'first.' . self::ROOT);
        $server = $this->server();

        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($server, [
            'customer_name' => '', 'domain' => 'second',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Customer::query()->where('mobile', '09351234567')->count());
        $this->assertSame($customer->id, CustomerInstallation::query()->sole()->request_payload['customer_id']);
    }

    public function test_a_new_customer_needs_a_name(): void
    {
        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server(), ['customer_name' => '']))
            ->assertSessionHasErrors('customer_name');
        $this->assertSame(0, CustomerInstallation::count());
    }

    public function test_a_soft_deleted_customers_mobile_is_refused_with_guidance(): void
    {
        $this->customer('09351234567')->delete();

        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server()))
            ->assertSessionHasErrors('mobile');
        $this->assertSame(0, CustomerInstallation::count());
    }

    public function test_a_domain_already_owned_by_a_site_is_refused_on_submit(): void
    {
        $this->site($this->customer(), 'khorshid.' . self::ROOT);

        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server()))
            ->assertSessionHasErrors('domain');
        Queue::assertNothingPushed();
    }

    public function test_an_unimplemented_driver_is_refused_before_dispatch(): void
    {
        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server('docker')))
            ->assertSessionHasErrors('remote_server_id');
        Queue::assertNothingPushed();
    }

    public function test_a_theme_that_is_not_commercially_eligible_is_refused(): void
    {
        $draft = $this->draftTheme();

        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server(), ['theme_id' => $draft->id]))
            ->assertSessionHasErrors('theme_id');
        $this->assertSame(0, CustomerInstallation::count());
    }

    public function test_a_vertical_kit_needs_its_own_vertical(): void
    {
        $kit = $this->solarKit();

        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server(), [
            'business_vertical' => 'retail', 'kit_module_ids' => [$kit->id],
        ]))->assertSessionHasErrors('kit_module_ids');
        $this->assertSame(0, CustomerInstallation::count());
    }

    public function test_a_module_that_is_not_a_license_only_kit_is_refused(): void
    {
        $free = Module::query()->create(['slug' => 'free-thing', 'title' => 'Free', 'type' => Module::TYPE_CORE, 'is_active' => true]);

        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server(), [
            'kit_module_ids' => [$free->id],
        ]))->assertSessionHasErrors('kit_module_ids');
    }

    public function test_quote_characters_that_would_break_the_env_file_are_refused(): void
    {
        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server(), [
            'site_title' => 'Bad "name" $HOME',
        ]))->assertSessionHasErrors('site_title');
    }

    public function test_the_solar_profile_carries_vertical_kit_and_industry_theme_into_the_pipeline(): void
    {
        $theme = $this->eligibleTheme('solar-industry');
        $kit = $this->solarKit();

        $options = app(\App\Services\Onboarding\SiteOnboardingService::class)->wizardOptions();
        $this->assertSame($theme->id, $options['profiles']['solar_energy']['theme_id']);
        $this->assertContains($kit->id, $options['profiles']['solar_energy']['kit_ids']);

        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server(), [
            'business_vertical' => 'solar_energy', 'kit_module_ids' => [$kit->id], 'theme_id' => $theme->id,
        ]))->assertSessionHasNoErrors();

        $payload = CustomerInstallation::query()->sole()->request_payload;
        $this->assertSame('solar_energy', $payload['business_vertical']);
        $this->assertSame([$kit->id], $payload['kit_module_ids']);
        $this->assertSame($theme->id, $payload['theme_id']);
        $this->assertSame('solar-industry', $payload['theme_slug']);
    }

    public function test_fleet_updates_tab_renders_component_versioning_and_boundary_rules(): void
    {
        $this->eligibleTheme();
        $this->server();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.onboarding.create', ['tab' => 'fleet-updates']));

        $response->assertOk();
        $response->assertSee('مدیریت، نسخه‎بندی و به‌روزرسانی‌های ناوگان');
        $response->assertSee('قوانین قطعی استقرار مخازن');
        $response->assertSee('پروژه saas-platform:');
        $response->assertSee('نسخه‌بندی جامع اجزا');
        $response->assertSee('موتور و بلوک‌های کانواس');
        $response->assertSee('کتابخانه ویجت‌ها');
        $response->assertSee('هسته و داشبورد مشتری');
        $response->assertSee('موشن‌گرافیک‌ها');
    }

    public function test_apply_package_fleet_update_dispatches_package_upgrade(): void
    {
        $cust = $this->customer('09121112244');
        $site = $this->site($cust, 'pkg-target.example.com');
        $pkg = \App\Models\CustomerInstallationPackage::create([
            'version'       => '1.3.0',
            'arvan_key'     => 'pkg/1.3.0',
            'sha256'        => str_repeat('c', 64),
            'size_bytes'    => 1024,
            'is_active'     => true,
            'release_notes' => 'نسخه آزمایشی',
            'released_at'   => now(),
        ]);

        $mockPipeline = $this->createMock(\App\Services\CustomerInstallation\CustomerPackageUpgradePipeline::class);
        $mockPipeline->expects($this->once())
            ->method('upgradeSite')
            ->willReturn(\App\Models\ThemeFleetUpdate::create([
                'site_id'            => $site->id,
                'update_type'        => \App\Models\ThemeFleetUpdate::TYPE_PACKAGE,
                'status'             => \App\Models\ThemeFleetUpdate::STATUS_COMPLETED,
                'component_versions' => ['customer_core' => ['from' => '1.1.0', 'to' => '1.3.0']],
                'started_at'         => now(),
                'completed_at'       => now(),
            ]));
        $this->app->instance(\App\Services\CustomerInstallation\CustomerPackageUpgradePipeline::class, $mockPipeline);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.onboarding.fleet-updates.apply-package'), [
                'site_ids' => [$site->id],
            ]);

        $response->assertRedirect(route('admin.onboarding.create', ['tab' => 'fleet-updates']));
        $response->assertSessionHas('success');

        $this->assertTrue(\App\Models\ThemeFleetUpdate::where('site_id', $site->id)
            ->where('update_type', \App\Models\ThemeFleetUpdate::TYPE_PACKAGE)
            ->where('status', \App\Models\ThemeFleetUpdate::STATUS_COMPLETED)
            ->exists());
    }

    public function test_apply_package_without_an_active_package_never_fabricates_one(): void
    {
        $cust = $this->customer('09121112245');
        $site = $this->site($cust, 'pkg-none.example.com');
        \App\Models\CustomerInstallationPackage::query()->update(['is_active' => false]);
        $packagesBefore = \App\Models\CustomerInstallationPackage::count();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.onboarding.fleet-updates.apply-package'), ['site_ids' => [$site->id]]);

        $response->assertSessionHas('error');
        $this->assertSame($packagesBefore, \App\Models\CustomerInstallationPackage::count());
        $this->assertNull(\App\Models\CustomerInstallationPackage::active(), 'An inactive package must not be used or re-activated.');
        $this->assertSame(0, \App\Models\ThemeFleetUpdate::count());
    }

    public function test_fleet_tab_shows_package_upgrade_with_apply_button(): void
    {
        $cust = $this->customer('09121112246');
        $site = $this->site($cust, 'pkg-table.example.com');
        \App\Models\CustomerInstallation::create([
            'site_id'           => $site->id,
            'remote_server_id'  => 1,
            'installer_user_id' => 1,
            'customer_company'  => 'Test Co',
            'customer_email'    => 'test@example.com',
            'customer_domain'   => 'pkg-table.example.com',
            'status'            => 'complete',
            'package_version'   => '1.1.0',
        ]);

        \App\Models\CustomerInstallationPackage::create([
            'version'       => '1.2.0',
            'arvan_key'     => 'pkg/1.2.0',
            'sha256'        => str_repeat('d', 64),
            'size_bytes'    => 1024,
            'is_active'     => true,
            'release_notes' => 'نسخه آزمایشی',
            'released_at'   => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.onboarding.create', ['tab' => 'fleet-updates']))
            ->assertOk()
            ->assertSee(route('admin.onboarding.fleet-updates.apply-package'), false);
    }

    public function test_rollback_fleet_update_dispatches_one_click_rollback(): void
    {
        $theme = $this->eligibleTheme('solar-industry');
        $cust = $this->customer('09121112255');
        $site = $this->site($cust, 'rollback-site.example.com');
        $site->update(['theme_id' => $theme->id]);

        $rel1 = $theme->currentRelease;

        $rel2 = \App\Models\ThemeRelease::create([
            'theme_id'  => $theme->id,
            'version'   => '1.1.0',
            'changelog' => 'r2',
            'status'    => \App\Models\ThemeRelease::STATUS_PUBLISHED,
            'snapshot'  => ['pages' => []],
        ]);

        $update = \App\Models\ThemeFleetUpdate::create([
            'site_id'         => $site->id,
            'update_type'     => \App\Models\ThemeFleetUpdate::TYPE_THEME,
            'theme_id'        => $theme->id,
            'from_release_id' => $rel1->id,
            'to_release_id'   => $rel2->id,
            'revision_number' => 12,
            'status'          => \App\Models\ThemeFleetUpdate::STATUS_COMPLETED,
        ]);

        $mockGateway = $this->createMock(\App\Services\ThemeBuilder\ThemeInstallGateway::class);
        $mockGateway->expects($this->once())
            ->method('rollback')
            ->willReturn(\App\Services\ThemeBuilder\ThemeInstallResult::ok([
                'restored_revision_number' => 13,
                'restored_version'         => '1.0.0',
            ]));

        $this->app->instance(\App\Services\ThemeBuilder\ThemeInstallGateway::class, $mockGateway);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.onboarding.fleet-updates.rollback'), [
                'update_id' => $update->id,
            ]);

        $response->assertRedirect(route('admin.onboarding.create', ['tab' => 'fleet-updates']));
        $response->assertSessionHas('success');

        $this->assertTrue(\App\Models\ThemeFleetUpdate::where('site_id', $site->id)
            ->where('is_rollback', true)
            ->where('rollback_from_update_id', $update->id)
            ->exists());
    }
}
