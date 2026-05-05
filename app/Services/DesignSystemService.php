<?php

namespace App\Services;

use App\Helpers\PlatformSettings;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

/**
 * سرویس مرکزی طراحی: رنگ، تایپوگرافی، فونت.
 * پشتیبانی از overrideهای tenant-specific.
 */
class DesignSystemService
{
    public function __construct()
    {
    }

    /**
     * تم (رنگ‌ها) با اعمال overrideهای tenant در صورت وجود.
     */
    public function resolveTheme(?int $siteId = null): array
    {
        $base = PlatformSettings::allTheme();
        if ($siteId && Schema::hasTable('site_settings')) {
            $overrides = SiteSetting::allForSite($siteId);
            foreach (SiteSetting::DESIGN_KEYS as $key) {
                if (isset($overrides[$key]) && (string) $overrides[$key] !== '') {
                    $base[$key] = $overrides[$key];
                }
            }
        }
        return $base;
    }

    /**
     * فونت و تایپوگرافی با اعمال overrideهای tenant در صورت وجود.
     */
    public function resolveFont(?int $siteId = null): array
    {
        $base = PlatformSettings::allFont();
        if ($siteId && Schema::hasTable('site_settings')) {
            $overrides = SiteSetting::allForSite($siteId);
            $fontKeys = array_keys(PlatformSettings::FONT_KEYS);
            foreach ($fontKeys as $key) {
                if (isset($overrides[$key]) && (string) $overrides[$key] !== '') {
                    $base[$key] = $overrides[$key];
                }
            }
        }
        return $base;
    }

    /**
     * آرایهٔ متغیرهای CSS برای :root (بدون hard-code).
     *
     * @return array<string, string>
     */
    public function getCssVariables(?int $siteId = null): array
    {
        return $this->getCssVariablesFromThemeFont(
            $this->resolveTheme($siteId),
            $this->resolveFont($siteId)
        );
    }

    /**
     * ساخت متغیرهای CSS از آرایهٔ theme و font (برای استفاده با دادهٔ API).
     *
     * @return array<string, string>
     */
    public function getCssVariablesFromThemeFont(array $theme, array $font): array
    {
        $mapping = config('design.theme_key_to_palette', []);
        $cssTokens = config('design.css_tokens', []);
        $vars = [];

        foreach ($mapping as $themeKey => $paletteKey) {
            $value = $theme[$themeKey] ?? $this->defaultForPaletteKey($paletteKey);
            $cssVar = $cssTokens[$paletteKey] ?? '--ds-' . str_replace('_', '-', $paletteKey);
            $vars[$cssVar] = $value;
        }

        $vars['--ds-font-body'] = $font['font_body'] ?? config('design.fonts.body', 'Vazirmatn, Tahoma, sans-serif');
        $vars['--ds-font-title'] = $font['font_title'] ?? config('design.fonts.title', 'Vazirmatn, Tahoma, sans-serif');
        $vars['--ds-font-size-base'] = $this->fontSizeValue($font['font_size_base'] ?? config('design.typography.size_base', '16'));
        $vars['--ds-font-size-title'] = $font['font_size_title'] ?? config('design.typography.size_title_ratio', '1.25');

        $aliases = config('design.legacy_aliases', []);
        foreach ($aliases as $legacy => $dsVar) {
            if (isset($vars[$dsVar])) {
                $vars[$legacy] = $vars[$dsVar];
            }
        }

        return $vars;
    }

    /**
     * لیست URLهای فونت سفارشی برای لود.
     *
     * @return array<string>
     */
    public function getFontImports(?int $siteId = null): array
    {
        return $this->getFontImportsFromFont($this->resolveFont($siteId));
    }

    /**
     * استخراج URLهای فونت از آرایهٔ font.
     *
     * @return array<string>
     */
    public function getFontImportsFromFont(array $font): array
    {
        $urls = [];
        $slots = config('design.fonts.custom_slots', 5);
        for ($i = 1; $i <= $slots; $i++) {
            $url = $font['font_custom_' . $i . '_url'] ?? '';
            if ($url !== '') {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    /**
     * خروجی بلوک CSS برای :root.
     */
    public function renderCssVariables(?int $siteId = null): string
    {
        $vars = $this->getCssVariables($siteId);
        $lines = array_map(fn ($k, $v) => "    {$k}: {$v};", array_keys($vars), $vars);
        return implode("\n", $lines);
    }

    /**
     * پالت‌های موجود برای انتخاب.
     */
    public function getAvailablePalettes(): array
    {
        return array_keys(config('design.palettes', ['default' => []]));
    }

    /**
     * مقادیر یک پالت.
     */
    public function getPalette(string $name): array
    {
        $palettes = config('design.palettes', []);
        return $palettes[$name] ?? $palettes['default'] ?? [];
    }

    /**
     * مقیاس تایپوگرافی.
     */
    public function getTypographyScale(): array
    {
        return config('design.typography', []);
    }

    protected function defaultForPaletteKey(string $key): string
    {
        $palette = config('design.palettes.default', []);
        return $palette[$key] ?? '#4f46e5';
    }

    protected function fontSizeValue(string $val): string
    {
        $val = trim($val);
        if ($val === '') {
            return '16px';
        }
        if (str_ends_with($val, 'px') || str_ends_with($val, 'rem')) {
            return $val;
        }
        return $val . 'px';
    }
}
