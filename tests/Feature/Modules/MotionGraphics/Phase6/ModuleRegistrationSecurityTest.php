<?php

namespace Tests\Feature\Modules\MotionGraphics\Phase6;

use App\Models\Module;
use App\Models\Product;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 registered motion-graphics as type=core (deliberately invisible
 * to every customer-facing surface, for safely proving the technical
 * integration with zero commercial exposure). Phase 9
 * (docs/motion-commercialization/AUDIT_REPORT.md §5) intentionally
 * reclassifies it to type=paid, now a real, purchasable commercial
 * module — this file was updated in Phase 9 to assert the new,
 * intentional shape rather than the old one.
 */
class ModuleRegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_registers_motion_graphics_exactly_once(): void
    {
        (new ModuleSeeder())->run();
        (new ModuleSeeder())->run();

        $this->assertSame(1, Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->count());
        $this->assertSame(1, Product::where('sku', 'motion-graphics-addon')->count());
    }

    public function test_the_seeded_row_matches_the_phase9_commercial_shape(): void
    {
        (new ModuleSeeder())->run();

        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();

        $this->assertSame(Module::TYPE_PAID, $module->type);
        $this->assertFalse($module->free_assignable);
        $this->assertFalse($module->license_required);
        $this->assertTrue($module->marketplace_visible);
        $this->assertTrue($module->is_active);
        $this->assertGreaterThan(0, $module->price_irr);
    }

    public function test_a_real_product_row_sells_it(): void
    {
        (new ModuleSeeder())->run();
        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();

        $product = Product::where('sku', 'motion-graphics-addon')->firstOrFail();

        $this->assertSame(Product::TYPE_MODULE_ADDON, $product->type);
        $this->assertSame($module->id, $product->module_id);
        $this->assertTrue($product->is_active);
        $this->assertSame($module->price_irr, $product->price_irr);
    }

    public function test_motion_graphics_now_appears_in_the_live_customer_marketplace_query(): void
    {
        (new ModuleSeeder())->run();

        // Module::paidMarketplace() is the exact base query
        // App\Http\Controllers\Api\TenantMarketplaceController::modules()
        // uses for the only wired, live customer-facing marketplace
        // endpoint on the platform. Phase 9 deliberately makes
        // motion-graphics discoverable here — this is the intended
        // commercialization surface, not a regression of Phase 6's
        // invisibility guarantee (which was intentional for a different
        // reason, at a different phase).
        $slugs = Module::paidMarketplace()->pluck('slug');

        $this->assertTrue($slugs->contains(RealMotionLicenseGateway::MODULE_SLUG));
    }

    public function test_motion_graphics_is_still_not_assignable_via_the_release_gated_marketplace_catalog(): void
    {
        (new ModuleSeeder())->run();

        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();

        // App\Services\ModuleEcosystem\ModuleAvailabilityRule requires a
        // published ModuleRelease before any module is "assignable" —
        // motion-graphics deliberately has none (it ships no deployable
        // code package; see AUDIT_REPORT.md). Unaffected by the Phase 9
        // reclassification — this exclusion was never about type=core.
        $rule = $this->app->make(\App\Services\ModuleEcosystem\ModuleAvailabilityRule::class);
        $result = $rule->evaluate($module);

        $this->assertFalse($result['assignable']);
        $this->assertContains('no_published_release', $result['reasons']);
    }
}
