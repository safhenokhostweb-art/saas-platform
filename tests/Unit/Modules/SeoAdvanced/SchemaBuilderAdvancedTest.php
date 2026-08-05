<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\SeoAdvanced\Services\SchemaBuilder;
use Tests\TestCase;

/**
 * Phase 18-E — Part 5/11: the 6 new dynamic schema types
 * (Organization/LocalBusiness/WebSite already covered by
 * SeoAdvancedServicesTest's schema tests from Phase 18).
 */
class SchemaBuilderAdvancedTest extends TestCase
{
    use RefreshDatabase;

    private function builder(): SchemaBuilder
    {
        return new SchemaBuilder();
    }

    public function test_all_eight_types_are_declared_supported(): void
    {
        $this->assertCount(9, SchemaBuilder::SUPPORTED_TYPES);
        foreach (['Organization', 'LocalBusiness', 'WebSite', 'Product', 'Article', 'FAQPage', 'HowTo', 'Event', 'Review'] as $type) {
            $this->assertContains($type, SchemaBuilder::SUPPORTED_TYPES);
        }
    }

    public function test_product_schema_requires_a_name(): void
    {
        $this->assertEmpty($this->builder()->buildType('Product', ['price' => 1000]));

        $schema = $this->builder()->buildType('Product', ['name' => 'کفش ورزشی', 'price' => 500000, 'currency' => 'IRR']);

        $this->assertSame('Product', $schema['@type']);
        $this->assertSame('کفش ورزشی', $schema['name']);
        $this->assertSame(500000, $schema['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
    }

    public function test_article_schema_requires_a_headline(): void
    {
        $this->assertEmpty($this->builder()->buildType('Article', ['image' => 'x.jpg']));

        $schema = $this->builder()->buildType('Article', ['headline' => 'مقاله تست', 'author' => 'علی', 'date_published' => '2026-01-01']);

        $this->assertSame('Article', $schema['@type']);
        $this->assertSame('Person', $schema['author']['@type']);
        $this->assertSame('2026-01-01', $schema['dateModified']); // falls back to date_published
    }

    public function test_faq_page_schema_builds_multiple_questions(): void
    {
        $this->assertEmpty($this->builder()->buildType('FAQPage', ['questions' => []]));

        $schema = $this->builder()->buildType('FAQPage', ['questions' => [
            ['question' => 'س۱', 'answer' => 'ج۱'],
            ['question' => 'س۲', 'answer' => 'ج۲'],
        ]]);

        $this->assertSame('FAQPage', $schema['@type']);
        $this->assertCount(2, $schema['mainEntity']);
        $this->assertSame('س۱', $schema['mainEntity'][0]['name']);
        $this->assertSame('ج۱', $schema['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function test_how_to_schema_requires_name_and_steps(): void
    {
        $this->assertEmpty($this->builder()->buildType('HowTo', ['name' => 'راهنما']));

        $schema = $this->builder()->buildType('HowTo', [
            'name' => 'نصب افزونه', 'steps' => [['name' => 'دانلود', 'text' => 'فایل را دانلود کنید']],
        ]);

        $this->assertSame('HowTo', $schema['@type']);
        $this->assertCount(1, $schema['step']);
        $this->assertSame('HowToStep', $schema['step'][0]['@type']);
    }

    public function test_event_schema_requires_name_and_start_date(): void
    {
        $this->assertEmpty($this->builder()->buildType('Event', ['name' => 'رویداد']));

        $schema = $this->builder()->buildType('Event', [
            'name' => 'کنفرانس', 'start_date' => '2026-05-01', 'location_name' => 'تهران',
        ]);

        $this->assertSame('Event', $schema['@type']);
        $this->assertSame('Place', $schema['location']['@type']);
    }

    public function test_review_schema_requires_item_and_rating(): void
    {
        $this->assertEmpty($this->builder()->buildType('Review', ['item_reviewed' => 'محصول']));

        $schema = $this->builder()->buildType('Review', [
            'item_reviewed' => 'محصول', 'rating_value' => 4.5, 'author' => 'سارا',
        ]);

        $this->assertSame('Review', $schema['@type']);
        $this->assertSame(4.5, $schema['reviewRating']['ratingValue']);
        $this->assertSame(5, $schema['reviewRating']['bestRating']); // default
    }

    public function test_unknown_type_returns_empty_array(): void
    {
        $this->assertSame([], $this->builder()->buildType('NotARealType', ['name' => 'x']));
    }

    public function test_site_level_build_is_unaffected_by_the_new_dynamic_types(): void
    {
        // Regression guard: build(int $siteId) — the original Phase 18 site-level
        // method — must still work exactly as before after this rewrite.
        $schema = $this->builder()->build(999999);

        $this->assertSame('WebSite', $schema['@type']);
    }
}
