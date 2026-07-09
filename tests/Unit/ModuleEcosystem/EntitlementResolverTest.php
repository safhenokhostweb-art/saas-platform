<?php

namespace Tests\Unit\ModuleEcosystem;

use App\Models\Customer;
use App\Models\Module;
use App\Models\ModuleEntitlement;
use App\Models\ModuleLicense;
use App\Models\Site;
use App\Services\ModuleEcosystem\Dto\EntitlementState;
use App\Services\ModuleEcosystem\EntitlementResolver;
use App\Services\ModuleLicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3-A / Commit 3 — EntitlementResolver scenarios.
 *
 * All scenarios use a license_only module (type=paid, license_required=true)
 * so that ModuleLicense is the applicable gate, per module_licenses'
 * own documented rule: "ماژول فقط در صورت داشتن لایسنس معتبر ... فعال
 * محسوب می‌شود."
 */
class EntitlementResolverTest extends TestCase
{
    use RefreshDatabase;

    private EntitlementResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new EntitlementResolver(app(ModuleLicenseService::class));
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

    private function makeLicenseOnlyModule(): Module
    {
        return Module::create([
            'slug' => 'test-module-' . uniqid('', true),
            'title' => 'ماژول تست',
            'description' => null,
            'is_active' => true,
            'type' => Module::TYPE_PAID,
            'license_required' => true,
        ]);
    }

    public function test_valid_license_is_allowed(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeLicenseOnlyModule();

        ModuleLicense::create([
            'site_id' => $site->id,
            'module_id' => $module->id,
            'license_type' => ModuleLicense::TYPE_ONE_TIME,
            'purchased_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => ModuleLicense::STATUS_ACTIVE,
        ]);

        $state = $this->resolver->resolve($site, $module);

        $this->assertTrue($state->allowed);
        $this->assertNull($state->reason);
    }

    public function test_expired_license_is_denied(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeLicenseOnlyModule();

        ModuleLicense::create([
            'site_id' => $site->id,
            'module_id' => $module->id,
            'license_type' => ModuleLicense::TYPE_ONE_TIME,
            'purchased_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
            'status' => ModuleLicense::STATUS_ACTIVE,
        ]);

        $state = $this->resolver->resolve($site, $module);

        $this->assertFalse($state->allowed);
        $this->assertSame('license_expired', $state->reason);
    }

    public function test_revoked_entitlement_is_denied(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeLicenseOnlyModule();

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

        $state = $this->resolver->resolve($site, $module);

        $this->assertFalse($state->allowed);
        $this->assertSame('revoked', $state->reason);
    }

    public function test_no_purchase_or_grant_is_denied(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeLicenseOnlyModule();

        $state = $this->resolver->resolve($site, $module);

        $this->assertFalse($state->allowed);
        $this->assertContains($state->reason, ['no_purchase', 'not_entitled']);
    }

    public function test_active_admin_grant_is_allowed(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeLicenseOnlyModule();

        ModuleEntitlement::create([
            'site_id' => $site->id,
            'customer_id' => $customer->id,
            'module_id' => $module->id,
            'module_slug' => $module->slug,
            'source' => ModuleEntitlement::SOURCE_ADMIN_GRANT,
            'status' => ModuleEntitlement::STATUS_ACTIVE,
            'granted_at' => now(),
        ]);

        $state = $this->resolver->resolve($site, $module);

        $this->assertTrue($state->allowed);
    }
}
