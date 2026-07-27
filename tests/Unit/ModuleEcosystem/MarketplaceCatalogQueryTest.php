<?php

namespace Tests\Unit\ModuleEcosystem;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Product;
use App\Services\ModuleEcosystem\MarketplaceModuleCatalog;
use App\Services\ModuleEcosystem\ModuleAvailabilityRule;
use App\Services\ModuleEcosystem\ModuleCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;
use Tests\TestCase;

/**
 * Phase 17-I — Part 8: MarketplaceModuleCatalog scenarios (Part 5) —
 * Scenarios 1-3 exactly as specified.
 */
class MarketplaceCatalogQueryTest extends TestCase
{
    use RefreshDatabase;

    private function catalog(): MarketplaceModuleCatalog
    {
        return new MarketplaceModuleCatalog(
            new ModuleAvailabilityRule(),
            new ModuleCatalogService(app(ModuleVersionRegistry::class))
        );
    }

    private function makeFreeModule(string $slug, string $releaseStatus = ModuleRelease::STATUS_PUBLISHED): Module
    {
        $module = Module::create([
            'slug' => $slug, 'title' => $slug, 'is_active' => true, 'version' => '1.0.0',
            'type' => Module::TYPE_CORE, 'free_assignable' => true,
        ]);
        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => $releaseStatus !== ModuleRelease::STATUS_DEPRECATED,
            'status' => $releaseStatus, 'package_sha256' => str_repeat('a', 64),
        ]);
        return $module;
    }

    // ─── Scenario 1 — published module appears ─────────────────────────────

    public function test_published_module_appears_in_catalog(): void
    {
        $module = $this->makeFreeModule('cat-pub-' . uniqid('', true));

        $slugs = array_map(fn ($row) => $row['module']->slug, $this->catalog()->assignableModules());

        $this->assertContains($module->slug, $slugs);
    }

    // ─── Scenario 2 — draft module does not appear ─────────────────────────

    public function test_draft_only_module_does_not_appear_in_catalog(): void
    {
        $module = $this->makeFreeModule('cat-draft-' . uniqid('', true), ModuleRelease::STATUS_DRAFT);

        $slugs = array_map(fn ($row) => $row['module']->slug, $this->catalog()->assignableModules());

        $this->assertNotContains($module->slug, $slugs);
    }

    // ─── Scenario 3 — deprecated module does not appear ────────────────────

    public function test_deprecated_only_module_does_not_appear_in_catalog(): void
    {
        $module = $this->makeFreeModule('cat-dep-' . uniqid('', true), ModuleRelease::STATUS_DEPRECATED);

        $slugs = array_map(fn ($row) => $row['module']->slug, $this->catalog()->assignableModules());

        $this->assertNotContains($module->slug, $slugs);
    }

    // ─── Paid module without a product does not appear ─────────────────────

    public function test_paid_module_without_product_does_not_appear_in_catalog(): void
    {
        $module = Module::create([
            'slug' => 'cat-nopay-' . uniqid('', true), 'title' => 'ماژول تست', 'is_active' => true,
            'version' => '1.0.0', 'type' => Module::TYPE_PAID, 'license_required' => false,
            'marketplace_visible' => true, 'price_irr' => 100000,
        ]);
        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'status' => ModuleRelease::STATUS_PUBLISHED, 'package_sha256' => str_repeat('a', 64),
        ]);
        // No Product row.

        $slugs = array_map(fn ($row) => $row['module']->slug, $this->catalog()->assignableModules());

        $this->assertNotContains($module->slug, $slugs);
    }

    public function test_paid_module_with_product_appears_with_full_data(): void
    {
        $module = Module::create([
            'slug' => 'cat-pay-' . uniqid('', true), 'title' => 'ماژول تست', 'is_active' => true,
            'version' => '1.0.0', 'type' => Module::TYPE_PAID, 'license_required' => false,
            'marketplace_visible' => true, 'price_irr' => 100000,
        ]);
        $release = ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'status' => ModuleRelease::STATUS_PUBLISHED, 'package_sha256' => str_repeat('a', 64),
        ]);
        $product = Product::create([
            'sku' => 'sku-' . uniqid('', true), 'name_fa' => 'محصول', 'type' => Product::TYPE_MODULE_ADDON,
            'price_irr' => 100000, 'module_id' => $module->id, 'is_active' => true,
        ]);

        $row = collect($this->catalog()->assignableModules())->firstWhere(fn ($r) => $r['module']->slug === $module->slug);

        $this->assertNotNull($row);
        $this->assertSame($release->id, $row['latest_release']->id);
        $this->assertSame($product->id, $row['product']->id);
        $this->assertArrayHasKey('price_irr', $row['pricing']);
    }

    // ─── Internal module never appears ─────────────────────────────────────

    public function test_internal_module_never_appears_in_catalog(): void
    {
        $module = Module::create([
            'slug' => 'cat-int-' . uniqid('', true), 'title' => 'ماژول داخلی', 'is_active' => true,
            'version' => '1.0.0', 'type' => Module::TYPE_INTERNAL,
        ]);

        $slugs = array_map(fn ($row) => $row['module']->slug, $this->catalog()->assignableModules());

        $this->assertNotContains($module->slug, $slugs);
    }
}
