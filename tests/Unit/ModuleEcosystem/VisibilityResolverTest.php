<?php

namespace Tests\Unit\ModuleEcosystem;

use App\Models\Customer;
use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\Site;
use App\Services\ModuleEcosystem\DeploymentResolver;
use App\Services\ModuleEcosystem\Dto\VisibilityState;
use App\Services\ModuleEcosystem\EntitlementResolver;
use App\Services\ModuleEcosystem\VisibilityResolver;
use App\Services\ModuleLicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3-A / Commit 4 — VisibilityResolver scenarios.
 */
class VisibilityResolverTest extends TestCase
{
    use RefreshDatabase;

    private VisibilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new VisibilityResolver(
            new DeploymentResolver(),
            new EntitlementResolver(app(ModuleLicenseService::class)),
        );
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'تست مشتری ' . uniqid('', true),
            'mobile' => '0912' . random_int(1000000, 9999999),
            'email' => 'test_' . uniqid('', true) . '@example.com',
        ]);
    }

    private function makeSite(Customer $customer): Site
    {
        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com',
            'is_active' => true,
        ]);
    }

    private function makeModule(array $overrides = []): Module
    {
        return Module::create(array_merge([
            'slug' => 'test-module-' . uniqid('', true),
            'title' => 'ماژول تست',
            'description' => 'توضیح ماژول تست',
            'is_active' => true,
            'type' => Module::TYPE_PAID,
            'version' => '1.0.0',
        ], $overrides));
    }

    public function test_installed_and_enabled_is_active(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule(['license_required' => true]);

        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);
        ModuleLicense::create([
            'site_id' => $site->id,
            'module_id' => $module->id,
            'license_type' => ModuleLicense::TYPE_ONE_TIME,
            'purchased_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => ModuleLicense::STATUS_ACTIVE,
        ]);

        $state = $this->resolver->resolve($site, $module);

        $this->assertSame(VisibilityState::STATE_ACTIVE, $state->state);
    }

    public function test_installed_but_not_entitled_is_locked(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule(['license_required' => true]);

        // Deployed on the site, but no ModuleLicense / entitlement grant exists.
        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);

        $state = $this->resolver->resolve($site, $module);

        $this->assertSame(VisibilityState::STATE_LOCKED, $state->state);
        $this->assertSame($module->title, $state->metadata['title']);
        $this->assertSame($module->description, $state->metadata['description']);
        $this->assertArrayHasKey('features', $state->metadata);
    }

    public function test_not_installed_but_purchasable_is_available(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule([
            'license_required' => false,
            'marketplace_visible' => true,
            'price_irr' => 100000,
        ]);

        // No site_modules row at all: never installed/assigned.
        $state = $this->resolver->resolve($site, $module);

        $this->assertSame(VisibilityState::STATE_AVAILABLE, $state->state);
    }

    public function test_platform_only_module_is_hidden(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule(['type' => Module::TYPE_INTERNAL]);

        $state = $this->resolver->resolve($site, $module);

        $this->assertSame(VisibilityState::STATE_HIDDEN, $state->state);
        $this->assertSame('platform_only', $state->metadata['reason']);
    }

    public function test_roadmap_module_is_hidden(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        // Not yet launched platform-wide: modules.is_active = false ->
        // getDistributionMode() = MODE_DISABLED (no separate "roadmap"
        // field exists on the Module model).
        $module = $this->makeModule(['is_active' => false]);

        $state = $this->resolver->resolve($site, $module);

        $this->assertSame(VisibilityState::STATE_HIDDEN, $state->state);
        $this->assertSame('not_ready', $state->metadata['reason']);
    }
}
