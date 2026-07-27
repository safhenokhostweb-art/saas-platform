<?php

namespace Tests\Unit\ModuleEcosystem;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Product;
use App\Services\ModuleEcosystem\ModuleCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;
use Tests\TestCase;

/**
 * Phase 17-I — Part 8: ModuleCatalogService scenarios — per-module
 * commercial data assembly (Part 1/5), proving each field is read from
 * the table that already owns it rather than duplicated anywhere new.
 */
class ModuleCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ModuleCatalogService
    {
        return new ModuleCatalogService(app(ModuleVersionRegistry::class));
    }

    private function makeModule(array $overrides = []): Module
    {
        return Module::create(array_merge([
            'slug' => 'cat-' . uniqid('', true),
            'title' => 'ماژول تست', 'is_active' => true, 'version' => '1.0.0', 'type' => Module::TYPE_PAID,
            'price_irr' => 50000,
        ], $overrides));
    }

    public function test_describe_includes_latest_published_release(): void
    {
        $module = $this->makeModule();
        $release = ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.2.0', 'release_type' => 'minor',
            'is_active' => true, 'status' => ModuleRelease::STATUS_PUBLISHED, 'package_sha256' => str_repeat('a', 64),
        ]);

        $result = $this->service()->describe($module);

        $this->assertSame($release->id, $result['latest_release']->id);
    }

    public function test_describe_omits_draft_release(): void
    {
        $module = $this->makeModule();
        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'status' => ModuleRelease::STATUS_DRAFT,
        ]);

        $result = $this->service()->describe($module);

        $this->assertNull($result['latest_release']);
    }

    public function test_describe_includes_active_product_and_prefers_its_price(): void
    {
        $module = $this->makeModule(['price_irr' => 50000]);
        $product = Product::create([
            'sku' => 'sku-' . uniqid('', true), 'name_fa' => 'محصول', 'type' => Product::TYPE_MODULE_ADDON,
            'price_irr' => 45000, 'module_id' => $module->id, 'is_active' => true,
        ]);

        $result = $this->service()->describe($module);

        $this->assertSame($product->id, $result['product']->id);
        $this->assertSame(45000, $result['pricing']['price_irr']);
    }

    public function test_describe_falls_back_to_module_price_when_no_product(): void
    {
        $module = $this->makeModule(['price_irr' => 50000]);

        $result = $this->service()->describe($module);

        $this->assertNull($result['product']);
        $this->assertSame(50000, $result['pricing']['price_irr']);
    }

    public function test_describe_ignores_inactive_product(): void
    {
        $module = $this->makeModule();
        Product::create([
            'sku' => 'sku-' . uniqid('', true), 'name_fa' => 'محصول', 'type' => Product::TYPE_MODULE_ADDON,
            'price_irr' => 45000, 'module_id' => $module->id, 'is_active' => false,
        ]);

        $result = $this->service()->describe($module);

        $this->assertNull($result['product']);
    }

    public function test_describe_reads_compatibility_from_real_module_json(): void
    {
        $module = Module::updateOrCreate(
            ['slug' => 'forms'],
            ['title' => 'ماژول فرم', 'is_active' => true, 'version' => '1.0.0', 'type' => Module::TYPE_CORE]
        );

        $result = $this->service()->describe($module);

        $this->assertArrayHasKey('php', $result['compatibility']);
    }
}
