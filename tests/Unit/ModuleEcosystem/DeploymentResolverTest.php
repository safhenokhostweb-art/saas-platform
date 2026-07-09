<?php

namespace Tests\Unit\ModuleEcosystem;

use App\Models\Customer;
use App\Models\Module;
use App\Models\Site;
use App\Services\ModuleEcosystem\DeploymentResolver;
use App\Services\ModuleEcosystem\Dto\DeploymentState;
use App\Services\ModuleUpdateEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3-A / Commit 2 — DeploymentResolver scenarios.
 *
 * "latest version" is controlled via modules.version (no module_releases
 * rows are created) — ModuleRelease::getLatestVersionForModule() falls
 * back to modules.version when no active release exists, per its own
 * documented behavior.
 */
class DeploymentResolverTest extends TestCase
{
    use RefreshDatabase;

    private DeploymentResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DeploymentResolver();
    }

    private function makeSite(): Site
    {
        $customer = Customer::create([
            'name' => 'تست مشتری ' . uniqid('', true),
            'mobile' => '0912' . random_int(1000000, 9999999),
            'email' => 'test_' . uniqid('', true) . '@example.com',
        ]);

        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com',
            'is_active' => true,
        ]);
    }

    private function makeModule(string $latestVersion): Module
    {
        return Module::create([
            'slug' => 'test-module-' . uniqid('', true),
            'title' => 'ماژول تست',
            'description' => null,
            'is_active' => true,
            'version' => $latestVersion,
            'type' => Module::TYPE_CORE,
        ]);
    }

    public function test_module_installed_and_up_to_date(): void
    {
        $site = $this->makeSite();
        $module = $this->makeModule('1.0.0');
        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);

        $state = $this->resolver->resolve($site, $module);

        $this->assertSame(DeploymentState::STATUS_INSTALLED, $state->status);
        $this->assertSame('1.0.0', $state->installedVersion);
        $this->assertSame('1.0.0', $state->latestVersion);
    }

    public function test_module_not_installed(): void
    {
        $site = $this->makeSite();
        $module = $this->makeModule('1.0.0');
        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => null]);

        $state = $this->resolver->resolve($site, $module);

        $this->assertSame(DeploymentState::STATUS_NOT_INSTALLED, $state->status);
        $this->assertNull($state->installedVersion);
        $this->assertSame('1.0.0', $state->latestVersion);
    }

    public function test_module_update_required_minor(): void
    {
        $site = $this->makeSite();
        $module = $this->makeModule('1.1.0');
        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);

        $state = $this->resolver->resolve($site, $module);

        $this->assertSame(DeploymentState::STATUS_UPDATE_REQUIRED, $state->status);
        $this->assertSame('1.0.0', $state->installedVersion);
        $this->assertSame('1.1.0', $state->latestVersion);
    }

    public function test_module_update_required_major(): void
    {
        $site = $this->makeSite();
        $module = $this->makeModule('2.0.0');
        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);

        $state = $this->resolver->resolve($site, $module);

        $this->assertSame(DeploymentState::STATUS_UPDATE_REQUIRED, $state->status);
        $this->assertSame('1.0.0', $state->installedVersion);
        $this->assertSame('2.0.0', $state->latestVersion);

        // DeploymentState carries only Installed/NotInstalled/UpdateRequired
        // (its Commit-1 contract has no "type" field). Update-type
        // classification already exists in ModuleUpdateEligibilityService —
        // this asserts the resolver's inputs are consistent with that
        // existing classification rather than duplicating it.
        $updateType = app(ModuleUpdateEligibilityService::class)
            ->getUpdateType($state->installedVersion, $state->latestVersion);
        $this->assertSame(ModuleUpdateEligibilityService::UPDATE_TYPE_MAJOR, $updateType);
    }
}
