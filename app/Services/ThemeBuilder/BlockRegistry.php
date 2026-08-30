<?php
namespace App\Services\ThemeBuilder;

/**
 * Phase 2h (ADR-029) — the canonical block type registry now lives in the
 * shared saas-platform/modules-theme-runtime package
 * (\SaaSPlatform\ThemeRuntime\BlockRegistry), the one authority both
 * saas-platform and customer-site read block schemas/empty-output policy
 * from. This class exists only so existing
 * `App\Services\ThemeBuilder\BlockRegistry` references throughout
 * saas-platform keep working — it adds no data of its own. A genuinely new
 * canonical type belongs in the shared package; a saas-platform-only type
 * belongs behind BlockRegistry::registerHostType() instead of being added
 * here.
 */
class BlockRegistry extends \SaaSPlatform\ThemeRuntime\BlockRegistry
{
}
