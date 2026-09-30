<?php

namespace App\Services\ThemeBuilder\FoodMart;

use App\Services\ThemeBuilder\BlockRenderer;

/**
 * FoodMart Phase 3 - thin compatibility subclass; contains NO markup.
 *
 * The canonical renderFoodmart*() implementations MOVED verbatim into the
 * shared theme-runtime package
 * (SaaSPlatform\ThemeRuntime\FoodMartBlockRendererTrait, consumed by
 * SaaSPlatform\ThemeRuntime\BlockRenderer), so the saas-platform Builder
 * preview and the real customer-site tenant runtime render foodmart_* blocks
 * from ONE implementation. The moved markup was verified byte-identical to
 * the Phase 2A extraction in this file before the body was deleted
 * (newline normalization only - see
 * storage/app/theme-baselines/foodmart/phase3/renderer-moved-evidence.json).
 *
 * This class remains only so Phase 2A/2B/2D test support and any
 * App\...\FoodMart\ type-hints keep resolving.
 *
 * Do NOT add render methods here: a foodmart markup change belongs in
 * SaaSPlatform\ThemeRuntime\FoodMartBlockRendererTrait only.
 */
class FoodMartBlockRenderer extends BlockRenderer
{
    // Intentionally empty - canonical rendering is inherited from the
    // shared package via App\Services\ThemeBuilder\BlockRenderer.
}
