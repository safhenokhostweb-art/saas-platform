<?php

namespace Tests\Unit\Services\Modules;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Services\ModuleUpdateEligibilityService;
use App\Services\Modules\ModuleDeploymentRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 17-H — Part 8: proves ModuleDeploymentRequestService (install/
 * upgrade) and ModuleUpdateEligibilityService (upgrade listing) both
 * select only published releases — the actual Part 7 gap-fix, exercised
 * end-to-end rather than just at the model-method level
 * (ModuleReleaseStatusTest covers that).
 */
class ModuleDeploymentReleaseSelectionTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'forms';

    private function makeSite(): Site
    {
        $customer = Customer::create(['name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999)]);

        $site = Site::create([
            'customer_id' => $customer->id, 'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com', 'is_active' => true,
            'support_expires_at' => now()->addYear(),
        ]);

        $server = CustomerRemoteServer::create([
            'label' => 'Test Server', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH',
            'status' => 'complete', 'site_id' => $site->id, 'customer_company' => $customer->name,
            'customer_email' => 'noreply@example.com', 'customer_domain' => $site->domain,
        ]);

        return $site;
    }

    private function makeModule(): Module
    {
        return Module::updateOrCreate(
            ['slug' => self::SLUG],
            ['title' => 'ماژول فرم', 'is_active' => true, 'version' => '1.0.0', 'type' => Module::TYPE_CORE, 'free_assignable' => true]
        );
    }

    private function makeRelease(Module $module, string $version, string $status, bool $withPackage = true): ModuleRelease
    {
        return ModuleRelease::create([
            'module_id' => $module->id, 'version' => $version, 'release_type' => 'minor',
            'is_active' => $status !== ModuleRelease::STATUS_DEPRECATED,
            'status' => $status,
            'package_sha256' => $withPackage ? str_repeat('a', 64) : null,
            'package_path' => $withPackage ? '/tmp/forms-package.zip' : null,
            'package_size_bytes' => $withPackage ? 1024 : null,
        ]);
    }

    // ─── Scenario 4 — a draft-only module cannot be deployed ──────────────

    public function test_install_rejected_when_only_release_is_draft(): void
    {
        Bus::fake();
        $site = $this->makeSite();
        $module = $this->makeModule();
        $this->makeRelease($module, '1.0.0', ModuleRelease::STATUS_DRAFT);

        $result = app(ModuleDeploymentRequestService::class)->deployModule($site, $module);

        $this->assertFalse($result['success']);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    // ─── Scenario 6 — install selects the latest published release ────────

    public function test_install_selects_latest_published_release_ignoring_a_newer_draft(): void
    {
        Bus::fake();
        $site = $this->makeSite();
        $module = $this->makeModule();
        $this->makeRelease($module, '1.0.0', ModuleRelease::STATUS_PUBLISHED);
        $this->makeRelease($module, '2.0.0', ModuleRelease::STATUS_DRAFT);

        $result = app(ModuleDeploymentRequestService::class)->deployModule($site, $module);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame('1.0.0', $result['installation']->metadata['target_version']);
    }

    // ─── Scenario 7 — upgrade selects the correct (published) version ─────

    public function test_upgrade_selects_latest_published_release_ignoring_a_newer_draft(): void
    {
        Bus::fake();
        $site = $this->makeSite();
        $module = $this->makeModule();
        $this->makeRelease($module, '1.1.0', ModuleRelease::STATUS_PUBLISHED);
        $this->makeRelease($module, '2.0.0', ModuleRelease::STATUS_DRAFT);

        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id, 'is_active' => true,
            'deployment_status' => 'installed', 'installed_version' => '1.0.0',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(ModuleDeploymentRequestService::class)->upgradeModule($site, $module);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame('1.1.0', $result['installation']->metadata['target_version']);
    }

    public function test_upgrade_eligibility_service_never_lists_a_deprecated_or_draft_release(): void
    {
        $site = $this->makeSite();
        $module = $this->makeModule();
        $this->makeRelease($module, '2.0.0', ModuleRelease::STATUS_DEPRECATED);
        $this->makeRelease($module, '3.0.0', ModuleRelease::STATUS_DRAFT);

        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id, 'is_active' => true,
            'installed_version' => '1.0.0', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $site->refresh();

        $updates = app(ModuleUpdateEligibilityService::class)->getAvailableModuleUpdates($site);

        $this->assertEmpty($updates, 'Neither a deprecated nor a draft release should ever be offered as an upgrade.');
    }

    // ─── Scenario 5 — deprecated-only module cannot be upgraded to ────────

    public function test_upgrade_rejected_when_the_only_release_for_the_module_is_deprecated(): void
    {
        Bus::fake();
        $site = $this->makeSite();
        $module = $this->makeModule();
        // No published release at all — the module's only release was
        // pulled from the catalog after a defect was found.
        $this->makeRelease($module, '2.0.0', ModuleRelease::STATUS_DEPRECATED);

        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id, 'is_active' => true,
            'deployment_status' => 'installed', 'installed_version' => '1.0.0',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(ModuleDeploymentRequestService::class)->upgradeModule($site, $module);

        $this->assertFalse($result['success']);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    public function test_deprecated_release_is_never_selected_when_a_published_one_also_exists(): void
    {
        Bus::fake();
        $site = $this->makeSite();
        $module = $this->makeModule();
        $this->makeRelease($module, '1.0.0', ModuleRelease::STATUS_PUBLISHED);
        $this->makeRelease($module, '2.0.0', ModuleRelease::STATUS_DEPRECATED);

        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id, 'is_active' => true,
            'deployment_status' => 'installed', 'installed_version' => '1.0.0',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(ModuleDeploymentRequestService::class)->upgradeModule($site, $module);

        // 2.0.0 (deprecated) is never reachable, no matter that it's the
        // higher version number — latestPublishedForModule() only ever
        // sees 1.0.0.
        $this->assertTrue($result['success']);
        $this->assertSame('1.0.0', $result['installation']->metadata['target_version']);
    }
}
