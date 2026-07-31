<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Adapters;

use SaaSPlatform\CoreUtils\Contracts\Services\SeoOverrideProviderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\MetaBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SchemaBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;

/**
 * Phase 18-B — Part 4: connects this module's existing settings/schema
 * services to the host runtime's generic SeoOverrideProviderInterface.
 * Only bound when this module is actually installed (see
 * SeoAdvancedServiceProvider::boot()) — the host's default Null Object
 * binding is what every other site gets.
 *
 * Deliberately uses a fixed site_id of 1 — a customer-site deployment is
 * always exactly one site (see App\Services\NullModuleLicenseAdapter's
 * own docblock: "Customer-site operates as a single licensed tenant").
 * This module's own tables were designed with a site_id column so the
 * SAME code can also be exercised, per-site, inside the multi-tenant
 * platform-admin app during development/testing (see the Phase 18 test
 * suite) — the constant here is the single point that would need to
 * change if customer-site ever became multi-site itself.
 */
class SeoOverrideProviderAdapter implements SeoOverrideProviderInterface
{
    private const SINGLE_TENANT_SITE_ID = 1;

    public function __construct(
        private readonly SchemaBuilderInterface $schemaBuilder,
        private readonly MetaBuilderInterface $metaBuilder,
    ) {}

    /**
     * Phase 18-D — Part 2: delegates to MetaBuilder rather than reading
     * canonical_url directly, so the Canonical Policy (auto/force_https/
     * custom) and Twitter Card default apply identically whether a page
     * is rendered via this module's own <x-seo-advanced::head> component
     * or via Core's native SeoService (this adapter) — one source of
     * truth for "what does this site's canonical/OG/twitter output".
     */
    public function overrideFor(): ?array
    {
        $settings = SeoAdvancedSetting::where('site_id', self::SINGLE_TENANT_SITE_ID)->first();
        if (!$settings) {
            return null;
        }

        $meta = $this->metaBuilder->build(self::SINGLE_TENANT_SITE_ID);

        return [
            'title' => $meta['title'],
            'description' => $meta['description'],
            'robots' => $meta['robots'],
            'canonical' => $meta['canonical'],
            'ogImage' => $meta['og']['image'],
            'twitterCard' => $meta['twitter_card'],
        ];
    }

    /**
     * Only LocalBusiness is contributed here — Organization/WebSite are
     * already rendered natively by the host (App\Helpers\JsonLd), so
     * duplicating them would conflict rather than add value (see the
     * Phase 18-B audit report).
     */
    public function extraJsonLd(): array
    {
        $settings = SeoAdvancedSetting::where('site_id', self::SINGLE_TENANT_SITE_ID)->first();
        if (!$settings || $settings->schema_type !== SeoAdvancedSetting::SCHEMA_LOCAL_BUSINESS) {
            return [];
        }

        $schema = $this->schemaBuilder->build(self::SINGLE_TENANT_SITE_ID);

        return empty($schema) ? [] : [$schema];
    }
}
