<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Services\ModuleUpdateEligibilityService;
use App\Services\SupportLicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\CoreUtils\Services\PlatformCompatibilityService;
use Tests\TestCase;

/**
 * Commit 12-K — compatibility gate in ModuleUpdateEligibilityService.
 *
 * PlatformCompatibilityService is mocked rather than exercised against real
 * package module.json files: it is already the canonical, independently
 * responsible compatibility engine (PHASE 6.98 / TASK 8) — this test only
 * verifies that ModuleUpdateEligibilityService correctly consults it and
 * gates on `passed`, not that the constraint-evaluation logic itself is
 * correct (that's PlatformCompatibilityService's own concern).
 */
class ModuleUpdateEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeEligibleSite(): Site
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
            'support_expires_at' => now()->addYear(),
        ]);
    }

    private function makeModuleWithRelease(string $installed, string $latest): array
    {
        $module = Module::create([
            'slug' => 'test-module-' . uniqid('', true),
            'title' => 'ماژول تست',
            'is_active' => true,
            'version' => $installed,
            'type' => Module::TYPE_CORE,
        ]);

        ModuleRelease::create([
            'module_id' => $module->id,
            'version' => $latest,
            'release_type' => 'minor',
            'is_active' => true,
        ]);

        return [$module, $latest];
    }

    public function test_compatible_update_is_offered(): void
    {
        $site = $this->makeEligibleSite();
        [$module] = $this->makeModuleWithRelease('1.0.0', '1.1.0');
        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);

        $compatibility = $this->mock(PlatformCompatibilityService::class);
        $compatibility->shouldReceive('check')
            ->once()
            ->with($module->slug, '1.1.0')
            ->andReturn(['passed' => true, 'errors' => [], 'warnings' => []]);

        $service = new ModuleUpdateEligibilityService(app(SupportLicenseService::class), $compatibility);
        $updates = $service->getAvailableModuleUpdates($site);

        $this->assertCount(1, $updates);
        $this->assertSame($module->slug, $updates[0]['slug']);
        $this->assertSame('1.1.0', $updates[0]['available_version']);
    }

    public function test_incompatible_update_is_suppressed(): void
    {
        $site = $this->makeEligibleSite();
        [$module] = $this->makeModuleWithRelease('1.0.0', '2.0.0');
        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);

        $compatibility = $this->mock(PlatformCompatibilityService::class);
        $compatibility->shouldReceive('check')
            ->once()
            ->with($module->slug, '2.0.0')
            ->andReturn(['passed' => false, 'errors' => ['PHP 8.3 is required'], 'warnings' => []]);

        $service = new ModuleUpdateEligibilityService(app(SupportLicenseService::class), $compatibility);
        $updates = $service->getAvailableModuleUpdates($site);

        $this->assertCount(0, $updates);
    }

    public function test_no_update_available_never_reaches_compatibility_check(): void
    {
        $site = $this->makeEligibleSite();
        [$module] = $this->makeModuleWithRelease('1.0.0', '1.0.0');
        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);

        $compatibility = $this->mock(PlatformCompatibilityService::class);
        $compatibility->shouldNotReceive('check');

        $service = new ModuleUpdateEligibilityService(app(SupportLicenseService::class), $compatibility);
        $updates = $service->getAvailableModuleUpdates($site);

        $this->assertCount(0, $updates);
    }
}
