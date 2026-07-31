<?php

namespace Tests\Feature\Modules\MotionGraphics\Phase6;

use App\Models\Module;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

/**
 * Phase 6 (docs/motion-integration-phase6-audit.md §4): unit-level proof
 * that RealMotionLicenseGateway is a faithful, real wrapper around
 * ModuleLicenseServiceInterface — no fake, no bespoke licensing logic.
 */
class RealMotionLicenseGatewayTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;

    private function gateway(): RealMotionLicenseGateway
    {
        return new RealMotionLicenseGateway($this->app->make(ModuleLicenseServiceInterface::class));
    }

    private function registerModule(array $overrides = []): Module
    {
        return Module::query()->create(array_merge([
            'slug' => RealMotionLicenseGateway::MODULE_SLUG,
            'title' => 'Motion Graphics',
            'is_active' => true,
            'type' => Module::TYPE_CORE,
            'free_assignable' => true,
            'license_required' => false,
        ], $overrides));
    }

    public function test_it_allows_an_active_site_once_the_module_is_registered(): void
    {
        $site = $this->createSite();
        $this->registerModule();

        $decision = $this->gateway()->checkAvailability('motion-1', (string) $site->id);

        $this->assertTrue($decision->allowed);
    }

    public function test_check_availability_and_validate_entitlement_agree(): void
    {
        $site = $this->createSite();
        $this->registerModule();

        $availability = $this->gateway()->checkAvailability('motion-1', (string) $site->id);
        $entitlement = $this->gateway()->validateEntitlement((string) $site->id, 'template-1');

        $this->assertTrue($availability->allowed);
        $this->assertTrue($entitlement->allowed);
    }

    public function test_it_denies_when_the_module_is_not_registered_at_all(): void
    {
        $site = $this->createSite();

        $decision = $this->gateway()->checkAvailability('motion-1', (string) $site->id);

        $this->assertFalse($decision->allowed);
        $this->assertSame('license_denied', $decision->code);
    }

    public function test_it_denies_when_the_module_row_is_retired(): void
    {
        $site = $this->createSite();
        $this->registerModule(['retired_at' => now()]);

        $decision = $this->gateway()->checkAvailability('motion-1', (string) $site->id);

        $this->assertFalse($decision->allowed);
    }

    public function test_it_denies_when_the_module_row_is_inactive(): void
    {
        $site = $this->createSite();
        $this->registerModule(['is_active' => false]);

        $decision = $this->gateway()->checkAvailability('motion-1', (string) $site->id);

        $this->assertFalse($decision->allowed);
    }

    public function test_it_denies_a_non_numeric_site_reference(): void
    {
        $this->registerModule();

        $decision = $this->gateway()->checkAvailability('motion-1', 'not-a-site-id');

        $this->assertFalse($decision->allowed);
        $this->assertSame('invalid_site_reference', $decision->code);
    }

    public function test_it_denies_a_site_reference_that_does_not_resolve_to_a_real_site(): void
    {
        $this->registerModule();

        $decision = $this->gateway()->checkAvailability('motion-1', '999999');

        $this->assertFalse($decision->allowed);
        $this->assertSame('site_not_found', $decision->code);
    }
}
