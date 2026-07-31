<?php

namespace Tests\Feature\Modules\MotionGraphics\Phase6;

use App\Models\Module;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 (docs/motion-integration-phase6-audit.md §4/§6): proves the
 * motion-graphics `modules` row is genuinely invisible to every live
 * customer-facing marketplace surface, and that the seeder is idempotent
 * — the two properties the audit's design leans on instead of a second,
 * bespoke visibility check.
 */
class ModuleRegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_registers_motion_graphics_exactly_once(): void
    {
        (new ModuleSeeder())->run();
        (new ModuleSeeder())->run();

        $this->assertSame(1, Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->count());
    }

    public function test_the_seeded_row_matches_the_audited_shape(): void
    {
        (new ModuleSeeder())->run();

        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();

        $this->assertSame(Module::TYPE_CORE, $module->type);
        $this->assertTrue($module->free_assignable);
        $this->assertFalse($module->license_required);
        $this->assertFalse($module->marketplace_visible);
        $this->assertTrue($module->is_active);
    }

    public function test_motion_graphics_never_appears_in_the_live_customer_marketplace_query(): void
    {
        (new ModuleSeeder())->run();

        // Module::paidMarketplace() is the exact base query
        // App\Http\Controllers\Api\TenantMarketplaceController::modules()
        // uses for the only wired, live customer-facing marketplace
        // endpoint on the platform. type=core is structurally excluded
        // (the scope requires type=paid), independent of any other flag.
        $slugs = Module::paidMarketplace()->pluck('slug');

        $this->assertFalse($slugs->contains(RealMotionLicenseGateway::MODULE_SLUG));
    }

    public function test_motion_graphics_is_not_assignable_via_the_release_gated_marketplace_catalog(): void
    {
        (new ModuleSeeder())->run();

        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();

        // App\Services\ModuleEcosystem\ModuleAvailabilityRule requires a
        // published ModuleRelease before any module is "assignable" —
        // motion-graphics deliberately has none.
        $rule = $this->app->make(\App\Services\ModuleEcosystem\ModuleAvailabilityRule::class);
        $result = $rule->evaluate($module);

        $this->assertFalse($result['assignable']);
        $this->assertContains('no_published_release', $result['reasons']);
    }
}
