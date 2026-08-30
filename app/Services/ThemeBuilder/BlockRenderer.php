<?php
namespace App\Services\ThemeBuilder;

/**
 * Phase 2h (ADR-029) — the canonical structural block-rendering methods
 * this class used to define directly now live in the shared
 * saas-platform/modules-theme-runtime package (\SaaSPlatform\ThemeRuntime\BlockRenderer),
 * consumed identically by customer-site's tenant runtime. This class exists
 * only so existing `App\Services\ThemeBuilder\BlockRenderer` references
 * throughout saas-platform (type-hints, container resolution, test doubles)
 * keep working — it adds no behavior of its own. Do not add a render*()
 * method here that duplicates a canonical type already defined in the
 * shared base class; a genuinely new canonical type belongs in the shared
 * package, a genuinely saas-platform-only type belongs here.
 */
class BlockRenderer extends \SaaSPlatform\ThemeRuntime\BlockRenderer
{
}
