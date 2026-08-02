<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\Modules\SeoAdvanced\Contracts\SchemaBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;

/**
 * Phase 18 — Part 4 / Phase 18-E — Part 5: Organization/LocalBusiness/
 * WebSite are the SITE-level identity types, driven by SeoAdvancedSetting
 * (build()) — a site only ever has one. Product/Article/FAQPage/HowTo/
 * Event/Review are PAGE/content-level types with no site-wide setting;
 * buildType() builds any of the 8 from raw data supplied by the caller
 * (this module's own admin UI, or — since this is Module-friendly by
 * design — a future shop/blog module that never needs to know this
 * module's internals, only the shared @type + field shape).
 */
class SchemaBuilder implements SchemaBuilderInterface
{
    public const TYPE_ORGANIZATION = 'Organization';
    public const TYPE_LOCAL_BUSINESS = 'LocalBusiness';
    public const TYPE_WEBSITE = 'WebSite';
    public const TYPE_PRODUCT = 'Product';
    public const TYPE_ARTICLE = 'Article';
    public const TYPE_FAQ_PAGE = 'FAQPage';
    public const TYPE_HOW_TO = 'HowTo';
    public const TYPE_EVENT = 'Event';
    public const TYPE_REVIEW = 'Review';

    public const SUPPORTED_TYPES = [
        self::TYPE_ORGANIZATION, self::TYPE_LOCAL_BUSINESS, self::TYPE_WEBSITE,
        self::TYPE_PRODUCT, self::TYPE_ARTICLE, self::TYPE_FAQ_PAGE,
        self::TYPE_HOW_TO, self::TYPE_EVENT, self::TYPE_REVIEW,
    ];

    public function build(int $siteId): array
    {
        $settings = SeoAdvancedSetting::where('site_id', $siteId)->first();
        $type = $settings?->schema_type ?? SeoAdvancedSetting::SCHEMA_WEBSITE;

        return match ($type) {
            SeoAdvancedSetting::SCHEMA_ORGANIZATION => $this->organization([
                'name' => $settings?->organization_name,
                'logo' => $settings?->organization_logo_url,
                'telephone' => $settings?->business_phone,
            ]),
            SeoAdvancedSetting::SCHEMA_LOCAL_BUSINESS => $this->localBusiness([
                'name' => $settings?->organization_name,
                'image' => $settings?->organization_logo_url,
                'address' => $settings?->business_address,
                'telephone' => $settings?->business_phone,
            ]),
            default => $this->website(['name' => $settings?->meta_title ?? $settings?->organization_name]),
        };
    }

    public function buildType(string $type, array $data): array
    {
        return match ($type) {
            self::TYPE_ORGANIZATION => $this->organization($data),
            self::TYPE_LOCAL_BUSINESS => $this->localBusiness($data),
            self::TYPE_WEBSITE => $this->website($data),
            self::TYPE_PRODUCT => $this->product($data),
            self::TYPE_ARTICLE => $this->article($data),
            self::TYPE_FAQ_PAGE => $this->faqPage($data),
            self::TYPE_HOW_TO => $this->howTo($data),
            self::TYPE_EVENT => $this->event($data),
            self::TYPE_REVIEW => $this->review($data),
            default => [],
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

    private function organization(array $d): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => self::TYPE_ORGANIZATION,
            'name' => $d['name'] ?? null,
            'logo' => $d['logo'] ?? null,
            'telephone' => $d['telephone'] ?? null,
            'url' => $d['url'] ?? null,
        ]);
    }

    private function localBusiness(array $d): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => self::TYPE_LOCAL_BUSINESS,
            'name' => $d['name'] ?? null,
            'image' => $d['image'] ?? null,
            'address' => $d['address'] ?? null,
            'telephone' => $d['telephone'] ?? null,
            'priceRange' => $d['price_range'] ?? null,
            'openingHours' => $d['opening_hours'] ?? null,
        ]);
    }

    private function website(array $d): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => self::TYPE_WEBSITE,
            'name' => $d['name'] ?? null,
            'url' => $d['url'] ?? null,
        ]);
    }

    /** Expects: name, description, image, sku, price, currency (default IRR), availability (default InStock), brand. */
    private function product(array $d): array
    {
        if (empty($d['name'])) {
            return [];
        }

        $offers = array_filter([
            '@type' => 'Offer',
            'price' => $d['price'] ?? null,
            'priceCurrency' => $d['currency'] ?? 'IRR',
            'availability' => 'https://schema.org/' . ($d['availability'] ?? 'InStock'),
        ]);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => self::TYPE_PRODUCT,
            'name' => $d['name'],
            'description' => $d['description'] ?? null,
            'image' => $d['image'] ?? null,
            'sku' => $d['sku'] ?? null,
            'brand' => !empty($d['brand']) ? ['@type' => 'Brand', 'name' => $d['brand']] : null,
            'offers' => count($offers) > 1 ? $offers : null,
        ]);
    }

    /** Expects: headline, image, author, publisher_name, date_published, date_modified. */
    private function article(array $d): array
    {
        if (empty($d['headline'])) {
            return [];
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => self::TYPE_ARTICLE,
            'headline' => $d['headline'],
            'image' => $d['image'] ?? null,
            'author' => !empty($d['author']) ? ['@type' => 'Person', 'name' => $d['author']] : null,
            'publisher' => !empty($d['publisher_name']) ? ['@type' => 'Organization', 'name' => $d['publisher_name']] : null,
            'datePublished' => $d['date_published'] ?? null,
            'dateModified' => $d['date_modified'] ?? $d['date_published'] ?? null,
        ]);
    }

    /** Expects: questions => [['question' => ..., 'answer' => ...], ...]. */
    private function faqPage(array $d): array
    {
        $questions = $d['questions'] ?? [];
        if (empty($questions)) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => self::TYPE_FAQ_PAGE,
            'mainEntity' => array_map(fn ($q) => [
                '@type' => 'Question',
                'name' => $q['question'] ?? '',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q['answer'] ?? ''],
            ], $questions),
        ];
    }

    /** Expects: name, description, steps => [['name' => ..., 'text' => ...], ...], total_time (ISO 8601 duration). */
    private function howTo(array $d): array
    {
        if (empty($d['name']) || empty($d['steps'])) {
            return [];
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => self::TYPE_HOW_TO,
            'name' => $d['name'],
            'description' => $d['description'] ?? null,
            'totalTime' => $d['total_time'] ?? null,
            'step' => array_map(fn ($s) => array_filter([
                '@type' => 'HowToStep',
                'name' => $s['name'] ?? null,
                'text' => $s['text'] ?? '',
            ]), $d['steps']),
        ]);
    }

    /** Expects: name, start_date, end_date, location_name, location_address, image. */
    private function event(array $d): array
    {
        if (empty($d['name']) || empty($d['start_date'])) {
            return [];
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => self::TYPE_EVENT,
            'name' => $d['name'],
            'startDate' => $d['start_date'],
            'endDate' => $d['end_date'] ?? null,
            'image' => $d['image'] ?? null,
            'location' => !empty($d['location_name']) ? array_filter([
                '@type' => 'Place',
                'name' => $d['location_name'],
                'address' => $d['location_address'] ?? null,
            ]) : null,
        ]);
    }

    /** Expects: item_reviewed, rating_value, best_rating (default 5), author, review_body. */
    private function review(array $d): array
    {
        if (empty($d['item_reviewed']) || !isset($d['rating_value'])) {
            return [];
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => self::TYPE_REVIEW,
            'itemReviewed' => ['@type' => 'Thing', 'name' => $d['item_reviewed']],
            'reviewRating' => array_filter([
                '@type' => 'Rating',
                'ratingValue' => $d['rating_value'],
                'bestRating' => $d['best_rating'] ?? 5,
            ]),
            'author' => !empty($d['author']) ? ['@type' => 'Person', 'name' => $d['author']] : null,
            'reviewBody' => $d['review_body'] ?? null,
        ]);
    }
}
