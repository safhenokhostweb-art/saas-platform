<?php

namespace SaaSPlatform\CoreUtils\SEO;

final class MetaEngine
{
    /**
     * Resolve metadata without allowing one runtime's settings to enter
     * another runtime. Page/model data is context-neutral; settings are
     * deliberately supplied in separate platform and tenant channels.
     *
     * Precedence per field, highest first: explicit $overrides, the $source
     * (the page/model itself), $providerDefaults, the context's settings
     * channel, then the context's built-in fallback.
     *
     * $providerDefaults is where a module's SeoOverrideProviderInterface
     * contribution belongs. It sits BELOW the page on purpose: the contract
     * describes a provider as supplying the value to use when "the host has
     * no existing default for that field", so a site-wide module title must
     * never flatten the title of a page that has one of its own.
     *
     * @param object|array<string, mixed>|null $source
     * @param array<string, mixed> $overrides
     * @param array<string, mixed> $platformSettings
     * @param array<string, mixed> $tenantSettings
     * @param array<string, mixed> $providerDefaults
     */
    public function resolve(
        MetaContext $context,
        object|array|null $source = null,
        array $overrides = [],
        array $platformSettings = [],
        array $tenantSettings = [],
        array $providerDefaults = [],
    ): MetaData {
        $settings = $this->settingsFor($context, $platformSettings, $tenantSettings);
        $fallback = $this->fallbackFor($context);
        $origin = $this->resolveOrigin($context, $settings);

        $title = $this->firstText(
            $this->value($overrides, ['title', 'meta_title']),
            $this->sourceValue($source, ['meta_title', 'title']),
            $this->value($providerDefaults, ['title', 'meta_title']),
            $this->value($settings, ['seo_default_title', 'meta_title', 'title', 'site_name', 'platform_name']),
            $fallback->title,
        );

        $description = $this->firstText(
            $this->value($overrides, ['description', 'meta_description']),
            $this->sourceValue($source, ['meta_description', 'description', 'excerpt']),
            $this->value($providerDefaults, ['description', 'meta_description']),
            $this->value($settings, ['seo_default_description', 'meta_description', 'description']),
            $fallback->description,
        );

        $language = $this->firstLanguage(
            $this->value($overrides, ['language', 'locale']),
            $this->sourceValue($source, ['language', 'locale']),
            $this->value($providerDefaults, ['language', 'locale']),
            $this->value($settings, ['language', 'locale']),
            $fallback->language,
        );

        $direction = $this->firstDirection(
            $this->value($overrides, ['direction', 'dir']),
            $this->sourceValue($source, ['direction', 'dir']),
            $this->value($providerDefaults, ['direction', 'dir']),
            $this->value($settings, ['direction', 'dir']),
            $this->directionForLanguage($language),
        );

        return new MetaData(
            title: $title,
            description: $description,
            canonical: $this->resolveCanonical($origin, [
                $this->value($overrides, ['canonical', 'canonical_url']),
                $this->sourceValue($source, ['canonical_url', 'canonical']),
                $this->value($providerDefaults, ['canonical', 'canonical_url']),
                $this->value($settings, ['canonical', 'canonical_url']),
            ]),
            language: $language,
            direction: $direction,
            robots: $this->firstText(
                $this->value($overrides, ['robots']),
                $this->sourceValue($source, ['robots']),
                $this->value($providerDefaults, ['robots', 'seo_robots']),
                $this->value($settings, ['robots', 'seo_robots']),
                $context->defaultRobots(),
            ),
            og_image: $this->firstUrlOrPath(
                $this->value($overrides, ['og_image', 'meta_image', 'image']),
                $this->sourceValue($source, ['og_image', 'meta_image', 'featured_image', 'image']),
                $this->value($providerDefaults, ['og_image', 'ogImage', 'meta_image', 'image']),
                $this->value($settings, ['og_image', 'seo_og_image']),
            ),
            og_type: $this->firstText(
                $this->value($overrides, ['og_type']),
                $this->sourceValue($source, ['og_type']),
                $this->value($providerDefaults, ['og_type']),
                $this->value($settings, ['og_type']),
                $fallback->og_type,
            ),
            twitter_card: $this->firstText(
                $this->value($overrides, ['twitter_card']),
                $this->sourceValue($source, ['twitter_card']),
                // camelCase aliases: SeoOverrideProviderInterface declares
                // `ogImage` / `twitterCard`, and the seo-advanced adapter emits
                // exactly those spellings. Without the aliases a module's OG
                // image and Twitter Card type were silently dropped: the
                // adapter returned ogImage, MetaEngine only read og_image, and
                // every page rendered og_image="" and twitter_card="summary".
                $this->value($providerDefaults, ['twitter_card', 'twitterCard']),
                $this->value($settings, ['twitter_card', 'seo_twitter_card']),
                $fallback->twitter_card,
            ),
        );
    }

    /** @param array<string, mixed> $platformSettings @param array<string, mixed> $tenantSettings */
    private function settingsFor(MetaContext $context, array $platformSettings, array $tenantSettings): array
    {
        if ($context->isPlatform()) {
            return $platformSettings;
        }

        if ($context->isTenant()) {
            return $tenantSettings;
        }

        return [];
    }

    private function fallbackFor(MetaContext $context): MetaData
    {
        [$title, $description, $canonical] = match ($context) {
            MetaContext::PLATFORM_ADMIN => ['Platform administration', 'Platform administration area.', 'https://platform.invalid/'],
            MetaContext::PLATFORM_PUBLIC => ['Platform', 'Platform public page.', 'https://platform.invalid/'],
            MetaContext::TENANT_ADMIN => ['Site administration', 'Site administration area.', 'https://tenant.invalid/'],
            MetaContext::TENANT_PUBLIC, MetaContext::CANVAS_PUBLIC => ['Site', 'Site public page.', 'https://tenant.invalid/'],
            MetaContext::PREVIEW_EDITOR => ['Preview', 'Content preview.', 'https://preview.invalid/'],
            MetaContext::AUTH_INSTALLED => ['Authentication', 'Authentication page.', 'https://auth.invalid/'],
        };

        return new MetaData(
            title: $title,
            description: $description,
            canonical: $canonical,
            language: 'fa',
            direction: 'rtl',
            robots: $context->defaultRobots(),
            og_image: '',
            og_type: 'website',
            twitter_card: 'summary',
        );
    }

    /** @param array<string, mixed> $settings */
    private function resolveOrigin(MetaContext $context, array $settings): string
    {
        $configured = $this->value($settings, ['canonical_origin', 'origin', 'app_url', 'url']);
        $normalized = $this->normalizeAbsoluteUrl($configured);

        if ($normalized !== null) {
            return rtrim($normalized, '/') . '/';
        }

        return $this->fallbackFor($context)->canonical;
    }

    /** @param list<mixed> $candidates */
    private function resolveCanonical(string $origin, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = $this->text($candidate);
            if ($value === '') {
                continue;
            }

            if (! $this->hasScheme($value)) {
                return rtrim($origin, '/') . '/' . ltrim($value, '/');
            }

            $absolute = $this->normalizeAbsoluteUrl($value);
            if ($absolute !== null && $this->sameOrigin($origin, $absolute)) {
                return $absolute;
            }
        }

        return $origin;
    }

    private function normalizeAbsoluteUrl(mixed $candidate): ?string
    {
        $value = $this->text($candidate);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $host = parse_url($value, PHP_URL_HOST);
        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return null;
        }

        return $value;
    }

    private function sameOrigin(string $origin, string $candidate): bool
    {
        return $this->originParts($origin) === $this->originParts($candidate);
    }

    /** @return array{scheme:string,host:string,port:int|null} */
    private function originParts(string $url): array
    {
        return [
            'scheme' => strtolower((string) parse_url($url, PHP_URL_SCHEME)),
            'host' => strtolower((string) parse_url($url, PHP_URL_HOST)),
            'port' => parse_url($url, PHP_URL_PORT),
        ];
    }

    private function hasScheme(string $value): bool
    {
        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $value) === 1;
    }

    private function sourceValue(object|array|null $source, array $keys): mixed
    {
        if ($source === null) {
            return null;
        }

        $seo = $this->read($source, 'seo');
        if (is_array($seo)) {
            $value = $this->value($seo, $keys);
            if ($this->text($value) !== '') {
                return $value;
            }
        }

        foreach ($keys as $key) {
            $value = $this->read($source, $key);
            if ($this->text($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function read(object|array $source, string $key): mixed
    {
        if (is_array($source)) {
            return array_key_exists($key, $source) ? $source[$key] : null;
        }

        try {
            return $source->{$key} ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $source */
    private function value(array $source, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $this->text($source[$key]) !== '') {
                return $source[$key];
            }
        }

        return null;
    }

    private function firstText(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = $this->text($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function firstUrlOrPath(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = $this->text($candidate);
            if ($value !== '' && ! str_starts_with(strtolower($value), 'javascript:')) {
                return $value;
            }
        }

        return '';
    }

    private function firstLanguage(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = str_replace('_', '-', strtolower($this->text($candidate)));
            if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $value) === 1) {
                return $value;
            }
        }

        return 'fa';
    }

    private function firstDirection(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = strtolower($this->text($candidate));
            if (in_array($value, ['ltr', 'rtl'], true)) {
                return $value;
            }
        }

        return 'rtl';
    }

    private function directionForLanguage(string $language): string
    {
        $primary = explode('-', $language, 2)[0];

        return in_array($primary, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';
    }

    private function text(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');
    }
}
