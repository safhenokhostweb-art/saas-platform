<?php

namespace SaaSPlatform\ThemeRuntime\Layout;

/**
 * Phase 2A — direction projection: the single boundary where a canonical
 * document's direction/locale becomes presentation attributes
 * (dir/lang HTML attributes, editor preview context).
 *
 * Rules:
 *  - An explicit document direction always wins (existing themes with an
 *    explicit direction preserve it).
 *  - No direction but a locale → LayoutContract::directionForLocale()
 *    (fa/ar → rtl, en → ltr).
 *  - Neither (legacy themes) → null for both attributes, meaning "no
 *    opinion": hosts keep their current behavior (the platform's RTL
 *    default) — never a blind default flip.
 */
final class DirectionProjector
{
    /**
     * @param array $document canonical envelope (or partial)
     * @return array{dir:?string, lang:?string} presentation attributes;
     *         null values mean "inherit the host's current behavior"
     */
    public static function attributes(array $document): array
    {
        $direction = $document['direction'] ?? null;
        if (is_string($direction) && LayoutContract::isSupportedDirection($direction)) {
            return ['dir' => $direction, 'lang' => is_string($document['locale'] ?? null) ? $document['locale'] : null];
        }

        $locale = $document['locale'] ?? null;
        if (is_string($locale) && $locale !== '') {
            return ['dir' => LayoutContract::directionForLocale($locale), 'lang' => $locale];
        }

        return ['dir' => null, 'lang' => null];
    }

    /**
     * Convenience for hosts that DO render a fallback default (the
     * platform's RTL-first reality) when the document expresses no
     * direction. Use attributes() when legacy behavior must be preserved
     * untouched; use this only where a direction attribute is mandatory.
     */
    public static function attributesWithDefault(array $document): array
    {
        $attrs = self::attributes($document);
        $attrs['dir'] = $attrs['dir'] ?? LayoutContract::DEFAULT_DIRECTION;
        $attrs['lang'] = $attrs['lang'] ?? 'fa';

        return $attrs;
    }
}
