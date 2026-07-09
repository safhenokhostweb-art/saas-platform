<?php

namespace Tests\Feature\ModuleEcosystem;

use App\Models\Customer;
use App\Models\Module;
use App\Models\ModuleEntitlement;
use App\Models\ModuleLicense;
use App\Models\Site;
use App\Services\ModuleEcosystem\DeploymentResolver;
use App\Services\ModuleEcosystem\Dto\DeploymentState;
use App\Services\ModuleEcosystem\Dto\VisibilityState;
use App\Services\ModuleEcosystem\EntitlementResolver;
use App\Services\ModuleEcosystem\VisibilityResolver;
use App\Services\ModuleLicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3-A / Commit 5 — combined Resolver behavior.
 *
 * Exercises DeploymentResolver + EntitlementResolver + VisibilityResolver
 * together, directly (no Controller, no API, no UI), to validate that the
 * three compose into the correct customer-facing visibility outcome
 * before any Commit 6 API/UI wiring happens.
 */
class ModuleVisibilityFlowTest extends TestCase
{
    use RefreshDatabase;

    private DeploymentResolver $deploymentResolver;
    private EntitlementResolver $entitlementResolver;
    private VisibilityResolver $visibilityResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deploymentResolver = new DeploymentResolver();
        $this->entitlementResolver = new EntitlementResolver(app(ModuleLicenseService::class));
        $this->visibilityResolver = new VisibilityResolver($this->deploymentResolver, $this->entitlementResolver);
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

    public function test_installed_with_valid_license_is_active(): void
    {
        $site = $this->makeSite($this->makeCustomer());
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

        $deployment = $this->deploymentResolver->resolve($site, $module);
        $entitlement = $this->entitlementResolver->resolve($site, $module);
        $visibility = $this->visibilityResolver->resolve($site, $module);

        $this->assertSame(DeploymentState::STATUS_INSTALLED, $deployment->status);
        $this->assertTrue($entitlement->allowed);
        $this->assertSame(VisibilityState::STATE_ACTIVE, $visibility->state);
    }

    public function test_installed_with_no_entitlement_is_locked(): void
    {
        $site = $this->makeSite($this->makeCustomer());
        $module = $this->makeModule(['license_required' => true]);

        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);
        // No ModuleLicense, no ModuleEntitlement: nothing was ever purchased/granted.

        $deployment = $this->deploymentResolver->resolve($site, $module);
        $entitlement = $this->entitlementResolver->resolve($site, $module);
        $visibility = $this->visibilityResolver->resolve($site, $module);

        $this->assertSame(DeploymentState::STATUS_INSTALLED, $deployment->status);
        $this->assertFalse($entitlement->allowed);
        $this->assertSame('no_purchase', $entitlement->reason);
        $this->assertSame(VisibilityState::STATE_LOCKED, $visibility->state);
    }

    public function test_not_installed_paid_marketplace_module_is_available(): void
    {
        $site = $this->makeSite($this->makeCustomer());
        $module = $this->makeModule([
            'license_required' => false,
            'marketplace_visible' => true,
            'price_irr' => 100000,
        ]);

        // No site_modules row at all: never installed/assigned.

        $deployment = $this->deploymentResolver->resolve($site, $module);
        $entitlement = $this->entitlementResolver->resolve($site, $module);
        $visibility = $this->visibilityResolver->resolve($site, $module);

        $this->assertSame(DeploymentState::STATUS_NOT_INSTALLED, $deployment->status);
        $this->assertFalse($entitlement->allowed);
        $this->assertSame(VisibilityState::STATE_AVAILABLE, $visibility->state);
    }

    public function test_platform_internal_module_is_hidden(): void
    {
        $site = $this->makeSite($this->makeCustomer());
        $module = $this->makeModule(['type' => Module::TYPE_INTERNAL]);

        $visibility = $this->visibilityResolver->resolve($site, $module);

        $this->assertSame(VisibilityState::STATE_HIDDEN, $visibility->state);
    }

    public function test_expired_license_is_locked(): void
    {
        $site = $this->makeSite($this->makeCustomer());
        $module = $this->makeModule(['license_required' => true]);

        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);
        ModuleLicense::create([
            'site_id' => $site->id,
            'module_id' => $module->id,
            'license_type' => ModuleLicense::TYPE_ONE_TIME,
            'purchased_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
            'status' => ModuleLicense::STATUS_ACTIVE,
        ]);

        $deployment = $this->deploymentResolver->resolve($site, $module);
        $entitlement = $this->entitlementResolver->resolve($site, $module);
        $visibility = $this->visibilityResolver->resolve($site, $module);

        $this->assertSame(DeploymentState::STATUS_INSTALLED, $deployment->status);
        $this->assertFalse($entitlement->allowed);
        $this->assertSame('license_expired', $entitlement->reason);
        $this->assertSame(VisibilityState::STATE_LOCKED, $visibility->state);
    }

    public function test_revoked_grant_is_locked(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule(['license_required' => true]);

        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);
        ModuleEntitlement::create([
            'site_id' => $site->id,
            'customer_id' => $customer->id,
            'module_id' => $module->id,
            'module_slug' => $module->slug,
            'source' => ModuleEntitlement::SOURCE_ADMIN_GRANT,
            'status' => ModuleEntitlement::STATUS_REVOKED,
            'granted_at' => now()->subMonth(),
            'revoked_at' => now(),
        ]);

        $deployment = $this->deploymentResolver->resolve($site, $module);
        $entitlement = $this->entitlementResolver->resolve($site, $module);
        $visibility = $this->visibilityResolver->resolve($site, $module);

        $this->assertSame(DeploymentState::STATUS_INSTALLED, $deployment->status);
        $this->assertFalse($entitlement->allowed);
        $this->assertSame('revoked', $entitlement->reason);
        $this->assertSame(VisibilityState::STATE_LOCKED, $visibility->state);
    }
}
