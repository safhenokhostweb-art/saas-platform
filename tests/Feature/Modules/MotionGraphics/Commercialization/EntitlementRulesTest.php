<?php

namespace Tests\Feature\Modules\MotionGraphics\Commercialization;

use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\Site;
use App\Models\User;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

/**
 * Phase 9 (docs/motion-commercialization/ENTITLEMENT_RULES.md): proves
 * the REAL commercial entitlement gate for the now type=paid
 * motion-graphics module — through the exact same
 * RealMotionLicenseGateway/ModuleLicenseAdapter/ModuleLicenseService
 * chain every other paid module already uses, unmodified. No fakes for
 * the license layer anywhere in this file.
 */
class EntitlementRulesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;

    protected function setUp(): void
    {
        parent::setUp();
        (new ModuleSeeder())->run();
    }

    private function module(): Module
    {
        return Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();
    }

    /**
     * grantByAdmin() writes granted_by as a real foreign key to `users`
     * (module_licenses.granted_by), unlike most Motion Graphics adapter
     * tests (which only ever compare an unsaved User's ->id as an opaque
     * actor identifier) — this must be a genuinely persisted row.
     */
    private function admin(): User
    {
        return User::create([
            'mobile' => '0912'.random_int(1000000, 9999999),
            'password' => bcrypt('secret'),
            'is_admin' => true,
        ]);
    }

    private function gateway(): RealMotionLicenseGateway
    {
        return $this->app->make(RealMotionLicenseGateway::class);
    }

    public function test_a_site_with_no_license_is_denied(): void
    {
        $site = $this->createSite();

        $decision = $this->gateway()->checkAvailability('motion-1', (string) $site->id);

        $this->assertFalse($decision->allowed);
    }

    public function test_a_site_with_a_valid_admin_granted_license_is_allowed(): void
    {
        $site = $this->createSite();
        $this->app->make(\App\Services\ModuleLicenseService::class)
            ->grantByAdmin($site, $this->module()->id, $this->admin());

        $decision = $this->gateway()->checkAvailability('motion-1', (string) $site->id);

        $this->assertTrue($decision->allowed);
    }

    public function test_an_expired_license_is_denied(): void
    {
        $site = $this->createSite();
        $this->app->make(\App\Services\ModuleLicenseService::class)
            ->grantByAdmin($site, $this->module()->id, $this->admin(), now()->subDay());

        $decision = $this->gateway()->checkAvailability('motion-1', (string) $site->id);

        $this->assertFalse($decision->allowed);
    }

    public function test_a_revoked_license_is_denied(): void
    {
        $site = $this->createSite();
        $license = $this->app->make(\App\Services\ModuleLicenseService::class)
            ->grantByAdmin($site, $this->module()->id, $this->admin());
        $this->app->make(\App\Services\ModuleLicenseService::class)->revoke($license);

        $decision = $this->gateway()->checkAvailability('motion-1', (string) $site->id);

        $this->assertFalse($decision->allowed);
    }

    public function test_a_license_never_grants_entitlement_to_a_different_site(): void
    {
        $licensedSite = $this->createSite('Licensed Site');
        $otherSite = $this->createSite('Other Site');
        $this->app->make(\App\Services\ModuleLicenseService::class)
            ->grantByAdmin($licensedSite, $this->module()->id, $this->admin());

        $decisionForLicensed = $this->gateway()->checkAvailability('motion-1', (string) $licensedSite->id);
        $decisionForOther = $this->gateway()->checkAvailability('motion-1', (string) $otherSite->id);

        $this->assertTrue($decisionForLicensed->allowed);
        $this->assertFalse($decisionForOther->allowed);
    }

    public function test_upgrade_extending_expiry_restores_entitlement(): void
    {
        $site = $this->createSite();
        $license = $this->app->make(\App\Services\ModuleLicenseService::class)
            ->grantByAdmin($site, $this->module()->id, $this->admin(), now()->addDay());
        $this->assertTrue($this->gateway()->checkAvailability('motion-1', (string) $site->id)->allowed);

        // "Downgrade": let it lapse.
        $license->update(['expires_at' => now()->subMinute()]);
        $this->assertFalse($this->gateway()->checkAvailability('motion-1', (string) $site->id)->allowed);

        // "Upgrade": extend it again — the same license row, no new grant.
        $license->update(['expires_at' => now()->addYear()]);
        $this->assertTrue($this->gateway()->checkAvailability('motion-1', (string) $site->id)->allowed);
    }

    public function test_a_never_expiring_license_remains_valid(): void
    {
        $site = $this->createSite();
        $this->app->make(\App\Services\ModuleLicenseService::class)
            ->grantByAdmin($site, $this->module()->id, $this->admin(), null);

        $this->assertTrue($this->gateway()->checkAvailability('motion-1', (string) $site->id)->allowed);
    }
}
