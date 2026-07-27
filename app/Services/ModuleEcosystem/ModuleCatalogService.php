<?php

namespace App\Services\ModuleEcosystem;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Product;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;

/**
 * Phase 17-I — Part 1/5: assembles one module's full commercial picture —
 * catalog metadata (Module), the release actually deployable right now
 * (ModuleRelease), the product it's sold under (Product, if any), and its
 * build-time compatibility (module.json) — without ever writing anything
 * or duplicating a value across tables. Each field below is read from
 * whichever table already owns it (see module-release-lifecycle.md بخش ۴):
 *
 *   module      → title/description/screenshots/pricing display fields
 *   release     → version/changelog (ModuleRelease.changelog, Phase 6.98)
 *   product     → sku/sale price (Product, may be null for free modules)
 *   compatibility → module.json (ModuleVersionRegistry), build-time only
 *
 * Read-only. Never used to decide whether a module IS assignable — that
 * is ModuleAvailabilityRule's job; this only describes one, once you
 * already have it.
 */
class ModuleCatalogService
{
    public function __construct(
        private readonly ModuleVersionRegistry $registry,
    ) {}

    /**
     * @return array{module: Module, latest_release: ?ModuleRelease, product: ?Product, pricing: array{price_irr: ?int, price_promo_irr: ?int, display_price: ?int, currency: ?string}, compatibility: array<string, string>}
     */
    public function describe(Module $module): array
    {
        $release = ModuleRelease::latestPublishedForModule($module->id);
        $product = $this->activeProductFor($module);

        return [
            'module' => $module,
            'latest_release' => $release,
            'product' => $product,
            'pricing' => [
                'price_irr' => $product->price_irr ?? $module->price_irr,
                'price_promo_irr' => $module->price_promo_irr,
                'display_price' => $module->display_price,
                'currency' => $module->currency,
            ],
            'compatibility' => $this->registry->compatibility($module->slug),
        ];
    }

    private function activeProductFor(Module $module): ?Product
    {
        return Product::where('module_id', $module->id)
            ->where('type', Product::TYPE_MODULE_ADDON)
            ->where('is_active', true)
            ->first();
    }
}
