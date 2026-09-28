<?php

namespace SaaSPlatform\CoreUtils\SEO;

final class MetaData
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonical,
        public readonly string $language,
        public readonly string $direction,
        public readonly string $robots,
        public readonly string $og_image,
        public readonly string $og_type,
        public readonly string $twitter_card,
    ) {}

    public static function default(): self
    {
        return new self(
            title: 'Untitled page',
            description: 'No description is available for this page.',
            canonical: 'https://invalid.local/',
            language: 'fa',
            direction: 'rtl',
            robots: 'noindex, nofollow',
            og_image: '',
            og_type: 'website',
            twitter_card: 'summary',
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'language' => $this->language,
            'direction' => $this->direction,
            'robots' => $this->robots,
            'og_image' => $this->og_image,
            'og_type' => $this->og_type,
            'twitter_card' => $this->twitter_card,
        ];
    }
}
