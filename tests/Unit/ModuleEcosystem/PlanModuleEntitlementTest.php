<?php

namespace Tests\Unit\ModuleEcosystem;

use App\Models\Customer;
use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\Site;
use App\Services\ModuleAssignmentService;
use App\Services\ModuleEcosystem\EntitlementResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 17-I — Part 8/4: "Plan & Entitlement Audit". There is no Plan
 * model in this codebase (only SupportPlan, an unrelated SLA/support
 * concept — see the Phase 17-I audit report) — entitlement is granted
 * per-module (ModuleLicense / site_modules), not via a module-bundling
 * Plan. These scenarios document and prove the *actual* entitlement
 * primitive instead: that granting entitlement (a license) is a distinct
 * act from deployment, and that an assignment attempt without a valid
 * entitlement is rejected — answering Part 4's exact questions
 * ("آیا Plan فقط Entitlement است؟ آیا Install واقعی جدا است؟") against
 * the real mechanism that exists today.
 */
class PlanModuleEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private function makeSite(): Site
    {
        $customer = Customer::create(['name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999)]);

        return Site::create([
            'customer_id' => $customer->id, 'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com', 'is_active' => true,
        ]);
    }

    private function makeLicenseOnlyModule(): Module
    {
        return Module::create([
            'slug' => 'ent-' . uniqid('', true), 'title' => 'ماژول تست', 'is_active' => true,
            'version' => '1.0.0', 'type' => Module::TYPE_PAID, 'license_required' => true, 'free_assignable' => false,
        ]);
    }

    // ─── Scenario 5 — "Plan" (module) entitlement works correctly ─────────

    public function test_granting_a_license_is_entitlement_only_and_does_not_by_itself_install_anything(): void
    {
        $site = $this->makeSite();
        $module = $this->makeLicenseOnlyModule();

        ModuleLicense::create([
            'site_id' => $site->id, 'module_id' => $module->id, 'license_type' => ModuleLicense::TYPE_ONE_TIME,
            'purchased_at' => now(), 'expires_at' => now()->addYear(), 'status' => ModuleLicense::STATUS_ACTIVE,
        ]);

        // Entitled...
        $entitlement = app(EntitlementResolver::class)->resolve($site, $module);
        $this->assertTrue($entitlement->allowed);

        // ...but never installed — granting a license writes module_licenses
        // only; site_modules (deployment/entitlement pivot) is untouched.
        $row = \Illuminate\Support\Facades\DB::table('site_modules')
            ->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertNull($row);
    }

    public function test_valid_license_makes_the_module_enableable(): void
    {
        $site = $this->makeSite();
        $module = $this->makeLicenseOnlyModule();

        ModuleLicense::create([
            'site_id' => $site->id, 'module_id' => $module->id, 'license_type' => ModuleLicense::TYPE_ONE_TIME,
            'purchased_at' => now(), 'expires_at' => now()->addYear(), 'status' => ModuleLicense::STATUS_ACTIVE,
        ]);

        $this->assertTrue(app(ModuleAssignmentService::class)->canEnableForSite($site, $module->id));
    }

    // ─── Scenario 6 — unauthorized assignment rejected ─────────────────────

    public function test_enable_is_rejected_without_a_valid_license(): void
    {
        $site = $this->makeSite();
        $module = $this->makeLicenseOnlyModule();
        // No ModuleLicense at all.

        $this->assertFalse(app(ModuleAssignmentService::class)->canEnableForSite($site, $module->id));

        $result = app(ModuleAssignmentService::class)->enableForSite($site, $module->id);
        $this->assertFalse($result['success']);
    }

    public function test_enable_is_rejected_for_paid_marketplace_modules_regardless_of_anything_else(): void
    {
        $site = $this->makeSite();
        $module = Module::create([
            'slug' => 'ent-paid-' . uniqid('', true), 'title' => 'ماژول تست', 'is_active' => true,
            'version' => '1.0.0', 'type' => Module::TYPE_PAID, 'license_required' => false,
            'marketplace_visible' => true, 'price_irr' => 100000,
        ]);

        // Paid-marketplace modules may only become entitled via
        // activatePaidPurchase() (post-payment) — enableForSite() (the
        // manual admin checkbox path) must never grant them, even with
        // no other obstacle present.
        $this->assertFalse(app(ModuleAssignmentService::class)->canEnableForSite($site, $module->id));

        $result = app(ModuleAssignmentService::class)->enableForSite($site, $module->id);
        $this->assertFalse($result['success']);
    }

    public function test_expired_license_no_longer_permits_enabling(): void
    {
        $site = $this->makeSite();
        $module = $this->makeLicenseOnlyModule();

        ModuleLicense::create([
            'site_id' => $site->id, 'module_id' => $module->id, 'license_type' => ModuleLicense::TYPE_ONE_TIME,
            'purchased_at' => now()->subYear(), 'expires_at' => now()->subDay(), 'status' => ModuleLicense::STATUS_ACTIVE,
        ]);

        $this->assertFalse(app(ModuleAssignmentService::class)->canEnableForSite($site, $module->id));
    }
}
