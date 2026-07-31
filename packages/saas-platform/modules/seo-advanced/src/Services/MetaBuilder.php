<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\Modules\SeoAdvanced\Contracts\MetaBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;

class MetaBuilder implements MetaBuilderInterface
{
    public function build(int $siteId, array $overrides = []): array
    {
        $settings = SeoAdvancedSetting::where('site_id', $siteId)->first();

        $title = $overrides['title'] ?? $settings?->meta_title;
        $description = $overrides['description'] ?? $settings?->meta_description;

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $overrides['keywords'] ?? $settings?->meta_keywords,
            'robots' => $overrides['robots'] ?? $settings?->robots_directive ?? 'index,follow',
            'canonical' => $this->resolveCanonical($overrides['canonical'] ?? null, $settings),
            'twitter_card' => $overrides['twitter_card'] ?? $settings?->twitter_card_type ?? 'summary',
            'og' => [
                'title' => $overrides['og']['title'] ?? $settings?->og_title ?? $title,
                'description' => $overrides['og']['description'] ?? $settings?->og_description ?? $description,
                'image' => $overrides['og']['image'] ?? $settings?->og_image_url,
            ],
        ];
    }

    /**
     * Phase 18-D — Part 2: Canonical Policy.
     * - auto: no site-wide override — the caller (Core's own SeoService,
     *   see Phase 18-B) falls back to the current page's own URL.
     * - force_https: whatever canonical would apply, upgraded to https.
     * - custom: the literal configured canonical_url, unchanged (the only
     *   behavior that existed before this phase — see the migration's
     *   backward-compatibility backfill).
     */
    private function resolveCanonical(?string $pageOverride, ?SeoAdvancedSetting $settings): ?string
    {
        if ($pageOverride !== null) {
            return $pageOverride;
        }

        $strategy = $settings?->canonical_strategy ?? SeoAdvancedSetting::CANONICAL_AUTO;

        if ($strategy === SeoAdvancedSetting::CANONICAL_CUSTOM) {
            return $settings?->canonical_url;
        }

        if ($strategy === SeoAdvancedSetting::CANONICAL_FORCE_HTTPS && !empty($settings?->canonical_url)) {
            return preg_replace('#^http://#i', 'https://', $settings->canonical_url);
        }

        return null;
    }

    public function renderHtml(array $meta): string
    {
        $tags = [];

        if (!empty($meta['title'])) {
            $tags[] = '<title>' . e($meta['title']) . '</title>';
        }
        if (!empty($meta['description'])) {
            $tags[] = '<meta name="description" content="' . e($meta['description']) . '">';
        }
        if (!empty($meta['keywords'])) {
            $tags[] = '<meta name="keywords" content="' . e($meta['keywords']) . '">';
        }
        if (!empty($meta['robots'])) {
            $tags[] = '<meta name="robots" content="' . e($meta['robots']) . '">';
        }
        if (!empty($meta['canonical'])) {
            $tags[] = '<link rel="canonical" href="' . e($meta['canonical']) . '">';
        }

        foreach (['title', 'description', 'image'] as $ogField) {
            if (!empty($meta['og'][$ogField])) {
                $tags[] = '<meta property="og:' . $ogField . '" content="' . e($meta['og'][$ogField]) . '">';
            }
        }

        if (!empty($meta['twitter_card'])) {
            $tags[] = '<meta name="twitter:card" content="' . e($meta['twitter_card']) . '">';
        }

        return implode("\n", $tags);
    }
}
