<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\Modules\SeoAdvanced\Contracts\SchemaBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;

/**
 * Phase 18 — Part 4: minimum viable Schema.org coverage — Organization,
 * LocalBusiness, Website. Falls back to Website (the most universally
 * applicable type) when no settings row exists yet, so a page never ends
 * up with zero structured data just because SEO Advanced was only just
 * installed.
 */
class SchemaBuilder implements SchemaBuilderInterface
{
    public function build(int $siteId): array
    {
        $settings = SeoAdvancedSetting::where('site_id', $siteId)->first();
        $type = $settings?->schema_type ?? SeoAdvancedSetting::SCHEMA_WEBSITE;

        return match ($type) {
            SeoAdvancedSetting::SCHEMA_ORGANIZATION => $this->organization($settings),
            SeoAdvancedSetting::SCHEMA_LOCAL_BUSINESS => $this->localBusiness($settings),
            default => $this->website($settings),
        };
    }

    public function renderHtml(array $schema): string
    {
        if (empty($schema)) {
            return '';
        }

        return '<script type="application/ld+json">'
            . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . '</script>';
    }

    private function organization(?SeoAdvancedSetting $settings): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $settings?->organization_name,
            'logo' => $settings?->organization_logo_url,
            'telephone' => $settings?->business_phone,
        ]);
    }

    private function localBusiness(?SeoAdvancedSetting $settings): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $settings?->organization_name,
            'image' => $settings?->organization_logo_url,
            'address' => $settings?->business_address,
            'telephone' => $settings?->business_phone,
        ]);
    }

    private function website(?SeoAdvancedSetting $settings): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $settings?->meta_title ?? $settings?->organization_name,
        ]);
    }
}
