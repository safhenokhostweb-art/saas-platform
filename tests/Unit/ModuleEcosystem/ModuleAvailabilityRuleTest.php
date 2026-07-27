<?php

namespace Tests\Unit\ModuleEcosystem;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Product;
use App\Services\ModuleEcosystem\ModuleAvailabilityRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 17-I — Part 8: ModuleAvailabilityRule scenarios — the formal
 * "Assignable" formula (Part 3).
 */
class ModuleAvailabilityRuleTest extends TestCase
{
    use RefreshDatabase;

    private function rule(): ModuleAvailabilityRule
    {
        return new ModuleAvailabilityRule();
    }

    private function makeModule(array $overrides = []): Module
    {
        return Module::create(array_merge([
            'slug' => 'avail-' . uniqid('', true),
            'title' => 'ماژول تست', 'is_active' => true, 'version' => '1.0.0', 'type' => Module::TYPE_CORE,
        ], $overrides));
    }

    private function makeRelease(Module $module, string $status, string $version = '1.0.0'): ModuleRelease
    {
        return ModuleRelease::create([
            'module_id' => $module->id, 'version' => $version, 'release_type' => 'minor',
            'is_active' => $status !== ModuleRelease::STATUS_DEPRECATED, 'status' => $status,
            'package_sha256' => str_repeat('a', 64),
        ]);
    }

    // ─── Free/core module ───────────────────────────────────────────────────

    public function test_free_module_with_published_release_is_assignable(): void
    {
        $module = $this->makeModule(['free_assignable' => true]);
        $this->makeRelease($module, ModuleRelease::STATUS_PUBLISHED);

        $this->assertTrue($this->rule()->isAssignable($module));
    }

    public function test_free_module_with_only_draft_release_is_not_assignable(): void
    {
        $module = $this->makeModule(['free_assignable' => true]);
        $this->makeRelease($module, ModuleRelease::STATUS_DRAFT);

        $result = $this->rule()->evaluate($module);
        $this->assertFalse($result['assignable']);
        $this->assertContains('no_published_release', $result['reasons']);
    }

    public function test_module_with_only_deprecated_release_is_not_assignable(): void
    {
        $module = $this->makeModule(['free_assignable' => true]);
        $this->makeRelease($module, ModuleRelease::STATUS_DEPRECATED);

        $this->assertFalse($this->rule()->isAssignable($module));
    }

    public function test_module_with_no_release_at_all_is_not_assignable(): void
    {
        $module = $this->makeModule(['free_assignable' => true]);

        $this->assertFalse($this->rule()->isAssignable($module));
    }

    // ─── Paid marketplace module — needs an active Product too ────────────

    public function test_paid_marketplace_module_with_release_and_active_product_is_assignable(): void
    {
        $module = $this->makeModule([
            'type' => Module::TYPE_PAID, 'license_required' => false,
            'marketplace_visible' => true, 'price_irr' => 100000,
        ]);
        $this->makeRelease($module, ModuleRelease::STATUS_PUBLISHED);
        Product::create([
            'sku' => 'sku-' . uniqid('', true), 'name_fa' => 'محصول', 'type' => Product::TYPE_MODULE_ADDON,
            'price_irr' => 100000, 'module_id' => $module->id, 'is_active' => true,
        ]);

        $this->assertTrue($this->rule()->isAssignable($module));
    }

    public function test_paid_marketplace_module_without_any_product_is_not_assignable(): void
    {
        $module = $this->makeModule([
            'type' => Module::TYPE_PAID, 'license_required' => false,
            'marketplace_visible' => true, 'price_irr' => 100000,
        ]);
        $this->makeRelease($module, ModuleRelease::STATUS_PUBLISHED);
        // No Product row created at all.

        $result = $this->rule()->evaluate($module);
        $this->assertFalse($result['assignable']);
        $this->assertContains('no_active_product', $result['reasons']);
    }

    public function test_paid_marketplace_module_with_only_inactive_product_is_not_assignable(): void
    {
        $module = $this->makeModule([
            'type' => Module::TYPE_PAID, 'license_required' => false,
            'marketplace_visible' => true, 'price_irr' => 100000,
        ]);
        $this->makeRelease($module, ModuleRelease::STATUS_PUBLISHED);
        Product::create([
            'sku' => 'sku-' . uniqid('', true), 'name_fa' => 'محصول', 'type' => Product::TYPE_MODULE_ADDON,
            'price_irr' => 100000, 'module_id' => $module->id, 'is_active' => false,
        ]);

        $this->assertFalse($this->rule()->isAssignable($module));
    }

    // ─── Structural exclusions ──────────────────────────────────────────────

    public function test_internal_module_is_never_assignable_even_with_a_published_release(): void
    {
        $module = $this->makeModule(['type' => Module::TYPE_INTERNAL]);
        // scopeAssignableToSite excludes internal at the query level, but
        // the rule itself must also refuse it directly if ever called
        // without that scope pre-applied.
        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'status' => ModuleRelease::STATUS_PUBLISHED, 'package_sha256' => str_repeat('a', 64),
        ]);

        $result = $this->rule()->evaluate($module);
        $this->assertFalse($result['assignable']);
        $this->assertContains('internal_module', $result['reasons']);
    }

    public function test_retired_module_is_not_assignable_even_with_a_published_release(): void
    {
        $module = $this->makeModule(['free_assignable' => true, 'retired_at' => now()]);
        $this->makeRelease($module, ModuleRelease::STATUS_PUBLISHED);

        $result = $this->rule()->evaluate($module);
        $this->assertFalse($result['assignable']);
        $this->assertContains('retired', $result['reasons']);
    }
}
