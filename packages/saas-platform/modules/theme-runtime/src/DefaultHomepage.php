<?php

namespace SaaSPlatform\ThemeRuntime;

/** Portable starter document; chrome is never duplicated in page content. */
final class DefaultHomepage
{
    public static function document(): array
    {
        return json_decode(
            file_get_contents(__DIR__.'/../resources/documents/default-homepage.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /** Explicit documents (including deliberately empty pages) take precedence. */
    public static function withDefaults(array $data, array $globalStyles = []): array
    {
        if (array_key_exists('pages', $data) || ($data['type'] ?? 'full') !== 'full') {
            return $data;
        }

        $defaults = self::document();
        $defaults['global_styles'] = array_replace_recursive($globalStyles, $defaults['global_styles']);

        return array_replace($defaults, $data);
    }
}
