<?php

namespace SaaSPlatform\ThemeRuntime\Support;

/**
 * The one list of social networks and messengers the theme runtime knows
 * (ADR-076). The registry builds every `network` select from options(), and
 * the renderer resolves icons, names and URL detection from the same rows,
 * so the editor and the page cannot disagree about which networks exist.
 *
 * Iranian messengers (Eitaa, Bale, Rubika, Soroush Plus, Gap) have no Lucide
 * glyph. They use simplified marks drawn for this project in Lucide's stroke
 * style (resources/icons/social-marks.json); they are recognisable shapes,
 * not the official logos. Every other network uses its catalog icon.
 *
 * `brand` is the fill used by the opt-in brand-colour style; the five
 * Iranian messenger colours are the owner's (2026-11-02). Each keeps a
 * white glyph at 3:1 contrast or better, the WCAG 2.1 AA requirement for
 * icons (SC 1.4.11 non-text contrast).
 */
final class SocialNetworks
{
    /** network => [icon (catalog name or mark:<key>), Persian name, URL pattern, brand fill] */
    private const NETWORKS = [
        'instagram' => ['instagram', 'اینستاگرام', '~(//|\.)(instagram\.com|instagr\.am)/~i', '#C13584'],
        'telegram' => ['send', 'تلگرام', '~(//|\.)(t\.me|telegram\.me|telegram\.org)/~i', '#2481CC'],
        'whatsapp' => ['message-circle', 'واتساپ', '~(//|\.)(wa\.me|whatsapp\.com)/~i', '#128C7E'],
        'eitaa' => ['mark:eitaa', 'ایتا', '~(//|\.)eitaa\.com/~i', '#EA5A24'],
        'bale' => ['mark:bale', 'بله', '~(//|\.)ble\.ir/~i', '#00A693'],
        'rubika' => ['mark:rubika', 'روبیکا', '~(//|\.)rubika\.ir/~i', '#9C27B0'],
        'soroush' => ['mark:soroush', 'سروش پلاس', '~(//|\.)(splus\.ir|sapp\.ir)/~i', '#1D72B8'],
        'gap' => ['mark:gap', 'گپ', '~(//|\.)gap\.im/~i', '#9B51E0'],
        'aparat' => ['circle-play', 'آپارات', '~(//|\.)aparat\.com/~i', '#D6114F'],
        'linkedin' => ['linkedin', 'لینکدین', '~(//|\.)linkedin\.com/~i', '#0A66C2'],
        'x' => ['twitter', 'ایکس (توییتر)', '~(//|\.)(twitter|x)\.com/~i', '#000000'],
        'youtube' => ['youtube', 'یوتیوب', '~(//|\.)(youtube\.com|youtu\.be)/~i', '#E00000'],
        'facebook' => ['facebook', 'فیسبوک', '~(//|\.)(facebook\.com|fb\.com)/~i', '#1877F2'],
        'github' => ['github', 'گیت‌هاب', '~(//|\.)github\.com/~i', '#181717'],
        'email' => ['mail', 'ایمیل', '~^mailto:~i', ''],
        'phone' => ['phone', 'تلفن', '~^tel:~i', ''],
    ];

    /** Networks that open in the visitor's own app, never a new tab. */
    private const LOCAL_SCHEMES = ['email', 'phone'];

    /** @var array<string,array{label:string,body:string}>|null */
    private static ?array $marks = null;

    public static function marksPath(): string
    {
        return __DIR__ . '/../../resources/icons/social-marks.json';
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::NETWORKS);
    }

    public static function has(string $network): bool
    {
        return isset(self::NETWORKS[$network]);
    }

    /** The `network` select options: automatic detection first, then every network. */
    public static function options(): array
    {
        $options = ['auto' => 'تشخیص خودکار از آدرس'];
        foreach (self::NETWORKS as $key => [, $label]) {
            $options[$key] = $label;
        }
        return $options;
    }

    public static function label(string $network): string
    {
        return self::NETWORKS[$network][1] ?? '';
    }

    public static function brandColor(string $network): string
    {
        return self::NETWORKS[$network][3] ?? '';
    }

    public static function opensExternally(string $network): bool
    {
        return !in_array($network, self::LOCAL_SCHEMES, true);
    }

    /** The network a URL points at, or null when none matches. */
    public static function detect(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        foreach (self::NETWORKS as $network => [, , $pattern]) {
            if (preg_match($pattern, $url)) {
                return $network;
            }
        }
        return null;
    }

    /** Decorative inline SVG for a network, '' for an unknown one. */
    public static function svg(string $network): string
    {
        $icon = self::NETWORKS[$network][0] ?? null;
        if ($icon === null) {
            return '';
        }
        if (!str_starts_with($icon, 'mark:')) {
            return IconLibrary::svg($icon);
        }
        $key = substr($icon, 5);
        $body = self::marks()[$key]['body'] ?? '';
        if ($body === '') {
            return '';
        }
        $class = IconLibrary::CSS_CLASS . ' ' . IconLibrary::CSS_CLASS . '-mark-' . $key;

        return '<svg class="' . $class . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">'
            . $body
            . '</svg>';
    }

    /** @return array<string,array{label:string,body:string}> */
    public static function marks(): array
    {
        if (self::$marks === null) {
            $decoded = json_decode((string) @file_get_contents(self::marksPath()), true);
            self::$marks = is_array($decoded['marks'] ?? null) ? $decoded['marks'] : [];
        }
        return self::$marks;
    }
}
