<?php

namespace Tests\Feature\Onboarding;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\CustomerInstallation;
use App\Models\Module;
use App\Models\Site;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Stages\ApplySiteEntitlementsStage;
use App\Services\CustomerInstallation\Stages\ProvisionLicenseStage;
use App\Services\Onboarding\SiteOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P1 (2026-11-19): general modules picked in the wizard are activated only
 * through ModuleAssignmentService::enableForSite(). The stage used to upsert
 * site_modules directly (paid modules free, license-only modules without a
 * license, no entitlement sync) and "select all" also ticked kits, which
 * the stage then silently skipped.
 */
class OnboardingActiveModulesTest extends TestCase
{
    use OnboardingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\CustomerInstallationPackage::create([
            'version' => '1.1.0', 'arvan_key' => 'packages/customer-site-v1.1.0.zip',
            'sha256' => str_repeat('a', 64), 'size_bytes' => 1024, 'is_active' => true, 'released_at' => now(),
        ]);
        Queue::fake();
        $this->platformRootDomain();
        config(['services.customer_provision_secret' => 'provision-secret-for-tests']);
    }

    private function freeModule(string $slug = 'blog'): Module
    {
        return Module::updateOrCreate(['slug' => $slug], [
            'title' => 'وبلاگ', 'type' => Module::TYPE_CORE, 'is_active' => true,
            'license_required' => false, 'free_assignable' => true, 'price_irr' => 0,
        ]);
    }

    private function paidMarketplaceModule(string $slug = 'shop'): Module
    {
        return Module::updateOrCreate(['slug' => $slug], [
            'title' => 'فروشگاه', 'type' => Module::TYPE_PAID, 'is_active' => true,
            'license_required' => false, 'free_assignable' => false, 'price_irr' => 5_000_000,
        ]);
    }

    private function licenseOnlyCore(string $slug = 'marketing_automation'): Module
    {
        return Module::updateOrCreate(['slug' => $slug], [
            'title' => 'اتوماسیون بازاریابی', 'type' => Module::TYPE_CORE, 'is_active' => true,
            'license_required' => true, 'free_assignable' => true,
        ]);
    }

    private function runStage(string $class, CustomerInstallation $installation, FakeRemoteDriver $driver): StageResult
    {
        $installation->refresh();
        $ctx = new InstallationContext($installation, CustomerInstallRequest::fromArray($installation->request_payload), $driver);

        return app($class)->execute($ctx);
    }

    private function installation(array $payload): CustomerInstallation
    {
        $server = $this->server();
        $payload = array_merge([
            'company' => 'ماژول', 'owner_name' => 'مالک', 'email' => '', 'phone' => '09927530318',
            'domain' => 'modules.' . self::ROOT, 'license_type' => 'professional', 'theme_slug' => '', 'modules' => [],
            'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => true, 'remote_server_id' => $server->id,
            'install_mode' => 'MODE_FRESH',
        ], $payload);

        return CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => $this->admin()->id, 'install_mode' => 'MODE_FRESH',
            'status' => 'running', 'customer_company' => $payload['company'], 'customer_email' => '',
            'customer_domain' => $payload['domain'], 'request_payload' => $payload,
        ]);
    }

    public function test_wizard_marks_kits_paid_and_license_only_modules_as_not_selectable(): void
    {
        $free = $this->freeModule();
        $paid = $this->paidMarketplaceModule();
        $licensed = $this->licenseOnlyCore();
        $kit = $this->solarKit();

        $modules = collect(app(SiteOnboardingService::class)->wizardOptions()['modules'])->keyBy('id');

        $this->assertTrue($modules[$free->id]['selectable']);
        $this->assertFalse($modules[$paid->id]['selectable']);
        $this->assertFalse($modules[$licensed->id]['selectable']);
        $this->assertFalse($modules[$kit->id]['selectable']);
        $this->assertNotEmpty($modules[$kit->id]['unselectable_reason']);
    }

    public function test_vertical_defaults_only_contain_selectable_modules(): void
    {
        $blog = $this->freeModule('blog');
        $this->paidMarketplaceModule('shop');
        $kit = $this->solarKit();

        $options = app(SiteOnboardingService::class)->wizardOptions();

        $this->assertContains($blog->id, $options['vertical_modules']['solar_energy']);
        $this->assertNotContains($kit->id, $options['vertical_modules']['solar_energy'], 'kits come from the kits section');
        $this->assertSame([], array_values(array_intersect(
            $options['vertical_modules']['retail'],
            collect($options['modules'])->where('selectable', false)->pluck('id')->all(),
        )));
    }

    public function test_submitting_a_paid_or_kit_module_as_active_is_rejected_before_anything_is_created(): void
    {
        foreach ([$this->paidMarketplaceModule(), $this->licenseOnlyCore(), $this->solarKit()] as $module) {
            $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server(), [
                'active_module_ids' => [$module->id],
            ]))->assertSessionHasErrors('active_module_ids');
        }

        $this->assertSame(0, CustomerInstallation::query()->count());
        Queue::assertNotPushed(CustomerInstallationPipelineJob::class);
    }

    public function test_a_free_module_is_activated_through_enable_for_site(): void
    {
        $free = $this->freeModule();
        $installation = $this->installation(['active_module_ids' => [$free->id]]);
        $driver = new FakeRemoteDriver();
        $this->runStage(ProvisionLicenseStage::class, $installation, $driver);

        $result = $this->runStage(ApplySiteEntitlementsStage::class, $installation, $driver);

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame(['blog' => 'active'], $result->output['modules']);
        $site = Site::query()->findOrFail($installation->fresh()->site_id);
        $this->assertTrue((bool) DB::table('site_modules')->where(['site_id' => $site->id, 'module_id' => $free->id])->value('is_active'));
    }

    public function test_a_stored_payload_with_a_paid_module_fails_closed_and_activates_nothing(): void
    {
        $paid = $this->paidMarketplaceModule();
        $installation = $this->installation(['active_module_ids' => [$paid->id]]);
        $driver = new FakeRemoteDriver();
        $this->runStage(ProvisionLicenseStage::class, $installation, $driver);

        $result = $this->runStage(ApplySiteEntitlementsStage::class, $installation, $driver);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('shop', (string) $result->error);
        $this->assertFalse(DB::table('site_modules')->where('module_id', $paid->id)->where('is_active', true)->exists(),
            'A paid module must never be activated for free.');
    }

    public function test_a_kit_sent_only_as_an_active_module_fails_instead_of_being_skipped(): void
    {
        $kit = $this->solarKit();
        $installation = $this->installation(['business_vertical' => 'solar_energy', 'active_module_ids' => [$kit->id]]);
        $driver = new FakeRemoteDriver();
        $this->runStage(ProvisionLicenseStage::class, $installation, $driver);

        $result = $this->runStage(ApplySiteEntitlementsStage::class, $installation, $driver);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('solar_industry_kit', (string) $result->error);
    }
}
