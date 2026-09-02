<?php

namespace SaaSPlatform\ThemeRuntime\Security;

/**
 * Phase 3E-C — the ONE rich-HTML/URL sanitization authority for the entire
 * Theme Runtime, extracted verbatim (not reimplemented) from canonical
 * BlockRenderer's Phase 3C-hardened isValidUrl()/escapeUrl()/sanitizeHtml().
 * Stateless and public specifically so any consumer that cannot inherit from
 * BlockRenderer — the customer-site Element SDK's static
 * App\Builder\Elements\* classes chief among them — has a real, narrow,
 * intentional way to reach the hardened implementation instead of
 * maintaining an independent, inevitably-drifting copy of the same regex
 * logic (exactly the pre-Phase-3C duplication class this phase closes).
 *
 * Both canonical BlockRenderer's own protected wrapper methods and every
 * Element SDK caller delegate here — there is no second implementation of
 * this logic left anywhere in either repository.
 */
final class HtmlSanitizer
{
    /**
     * Rejects `javascript:`-scheme and otherwise-dangerous URLs so an
     * escaped-but-still-executable href/src can never reach output.
     *
     * Uses only an explicit-scheme allowlist/denylist — a URL with NO
     * scheme at all ("/services", "about.html", "?x=1") has nothing to
     * exploit and is treated as a safe relative reference, never rejected.
     */
    public static function isValidUrl(string $url): bool
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return true;
        }
        if (preg_match('#^[\s\x00-\x1f]*(javascript|data|vbscript):#i', $trimmed)) {
            return false;
        }
        if (!preg_match('#^[a-z][a-z0-9+.\-]*:#i', $trimmed)) {
            return true; // no scheme at all: relative path, fragment, or query string
        }
        return (bool) preg_match('#^(https?|mailto|tel):#i', $trimmed);
    }

    /**
     * Validates then escapes a URL for use in an href/src attribute,
     * falling back to a safe default when the URL is empty or fails
     * isValidUrl(). Every URL-bearing prop should be rendered through
     * this, not bare attribute-escaping.
     */
    public static function escapeUrl(string $url, string $fallback = '#'): string
    {
        if ($url === '' || !self::isValidUrl($url)) {
            return self::escapeAttr($fallback);
        }
        return self::escapeAttr($url);
    }

    /**
     * Phase 3C security fix (preserved verbatim by this extraction):
     * strip_tags() only removes DISALLOWED TAGS — it never touches
     * attributes on tags it keeps, so authored content like
     * `<a href="javascript:alert(1)" onmouseover="alert(1)">` previously
     * passed straight through wherever this output was later printed via
     * an unescaped Blade `{!! !!}`. None of the allowed tags legitimately
     * need any attribute except `<a href>` — every attribute is dropped,
     * and `<a>`'s href is rebuilt from scratch through the scheme-validating
     * escapeUrl() above, so neither an event handler nor a dangerous URL
     * scheme can survive.
     */
    public static function sanitizeRichHtml(string $s): string
    {
        $s = strip_tags($s, '<p><br><a><strong><em><ul><ol><li><h2><h3>');

        return preg_replace_callback(
            '/<(a|p|br|strong|em|ul|ol|li|h2|h3)\b[^>]*>/i',
            static function (array $m): string {
                $tag = strtolower($m[1]);
                if ($tag !== 'a') {
                    return "<{$tag}>";
                }

                $href = '';
                if (preg_match('/\bhref\s*=\s*"([^"]*)"/i', $m[0], $hm)
                    || preg_match('/\bhref\s*=\s*\'([^\']*)\'/i', $m[0], $hm)) {
                    $href = html_entity_decode($hm[1], ENT_QUOTES);
                }

                return '<a href="' . self::escapeUrl($href) . '">';
            },
            $s
        );
    }

    private static function escapeAttr(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
