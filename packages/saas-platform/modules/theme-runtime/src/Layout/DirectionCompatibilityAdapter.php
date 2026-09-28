<?php

namespace SaaSPlatform\ThemeRuntime\Layout;

/** Projects legacy global_styles direction into the canonical envelope. */
final class DirectionCompatibilityAdapter
{
    /** @return array{direction:?string,locale:?string,source:string} */
    public static function resolve(array $document = [], array $globalStyles = [], ?string $fallbackLocale = null): array
    {
        $direction = $document['direction'] ?? null;
        $locale = $document['locale'] ?? null;
        if (is_string($direction) && LayoutContract::isSupportedDirection($direction)) {
            return ['direction' => $direction, 'locale' => is_string($locale) ? $locale : null, 'source' => 'document'];
        }
        $legacy = $globalStyles['direction'] ?? null;
        if (is_string($legacy) && LayoutContract::isSupportedDirection($legacy)) {
            return ['direction' => $legacy, 'locale' => is_string($globalStyles['locale'] ?? null) ? $globalStyles['locale'] : ($legacy === 'ltr' ? 'en' : 'fa'), 'source' => 'legacy_global_styles'];
        }
        $locale = is_string($locale) && $locale !== '' ? $locale : (is_string($globalStyles['locale'] ?? null) ? $globalStyles['locale'] : $fallbackLocale);
        return ['direction' => is_string($locale) && $locale !== '' ? LayoutContract::directionForLocale($locale) : null, 'locale' => $locale, 'source' => is_string($locale) && $locale !== '' ? 'locale' : 'host_default'];
    }
}
