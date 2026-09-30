<?php

namespace App\Services\ThemeBuilder\FoodMart;

use App\Services\ThemeBuilder\BlockRegistry;
use SaaSPlatform\ThemeRuntime\FoodMartBlockTypes;

/**
 * FoodMart Phase 2A host-side registration facade; Phase 3: DELEGATES to the
 * canonical SaaSPlatform\ThemeRuntime\FoodMartBlockTypes definitions (the
 * 19 foodmart_* type names/categories were copied there verbatim). This keeps
 * one canonical definition of the foodmart_* block types in the shared
 * package, consumed by BOTH hosts through the same generic
 * BlockRegistry::registerHostType() seam - never FoodMart detection in
 * generic code. Types below still carry EMPTY schema/defaultProps
 * (Phase 2A Step 8: rendering fidelity only, zero authorable properties).
 *
 * registerHostType() is process-global static state (by the shared
 * package's own design) - call reset() in test teardown, matching the
 * ThemeRuntimeSharedAuthorityTest convention.
 */
final class FoodMartBlocks
{
    /** Canonical foodmart_* definitions live in the shared package. */
    public const TYPES = FoodMartBlockTypes::TYPES;

    public static function register(): void
    {
        FoodMartBlockTypes::register();
    }

    public static function reset(): void
    {
        BlockRegistry::resetHostTypes();
    }
}
