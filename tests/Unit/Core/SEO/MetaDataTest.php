<?php

namespace Tests\Unit\Core\SEO;

use Error;
use PHPUnit\Framework\TestCase;
use SaaSPlatform\CoreUtils\SEO\MetaData;

class MetaDataTest extends TestCase
{
    public function test_default_returns_a_safe_non_empty_baseline(): void
    {
        $meta = MetaData::default();

        self::assertNotSame('', $meta->title);
        self::assertNotSame('', $meta->description);
        self::assertNotSame('', $meta->canonical);
        self::assertNotSame('', $meta->language);
        self::assertContains($meta->direction, ['ltr', 'rtl']);
        self::assertSame('noindex, nofollow', $meta->robots);
        self::assertSame('website', $meta->og_type);
        self::assertSame('summary', $meta->twitter_card);
    }

    public function test_constructor_preserves_all_metadata_fields(): void
    {
        $meta = new MetaData(
            title: 'Title',
            description: 'Description',
            canonical: 'https://example.test/page',
            language: 'en',
            direction: 'ltr',
            robots: 'index, follow',
            og_image: 'https://example.test/image.jpg',
            og_type: 'article',
            twitter_card: 'summary_large_image',
        );

        self::assertSame([
            'title' => 'Title',
            'description' => 'Description',
            'canonical' => 'https://example.test/page',
            'language' => 'en',
            'direction' => 'ltr',
            'robots' => 'index, follow',
            'og_image' => 'https://example.test/image.jpg',
            'og_type' => 'article',
            'twitter_card' => 'summary_large_image',
        ], $meta->toArray());
    }

    public function test_properties_are_immutable(): void
    {
        $meta = MetaData::default();

        $this->expectException(Error::class);
        $meta->title = 'Changed';
    }
}
