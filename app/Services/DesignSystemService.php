<?php
namespace App\Services;

use App\Helpers\PlatformSettings;
use App\Models\SiteSetting;
use App\Services\ThemeEngine\ThemeProfileManager;
use Illuminate\Support\Facades\Schema;

/**
 * سرویس مرکزی طراحی: رنگ، تایپوگرافی، فونت.
 * Phase UI-THEME.1: اولویت با ThemeProfile (Enterprise Theme Engine).
 * Fallback: PlatformSettings / SiteSetting (behavior unchanged from Phase 10.x).
 */
class DesignSystemService
{
    public function __construct(private ?ThemeProfileManager $themeManager = null)
    {
    }

    /**
     * آرایهٔ متغیرهای CSS برای :root — ThemeProfile اولویت دارد.
     * اگر ThemeProfile فعال باشد، vars آن جایگزین PlatformSettings می‌شود.
     *
     * @return array<string, string>
     */
    public function getCssVariables(?int $siteId = null): array
    {
        // Phase UI-THEME.1: prefer ThemeProfile when available
        if ($this->themeManager && $this->tableExists('theme_profiles')) {
            try {
                $profile = $this->themeManager->resolve($siteId);
                if ($profile) {
                    $vars = $profile->toCssVars();
                    // Merge font vars on top (fonts are not in ThemeProfile)
                    $font = $this->resolveFont($siteId);
                    $vars['--ds-font-body']        = $font['font_body'] ?? config('design.fonts.body', 'Vazirmatn, Tahoma, sans-serif');
                    $vars['--ds-font-title']       = $font['font_title'] ?? config('design.fonts.title', 'Vazirmatn, Tahoma, sans-serif');
                    $vars['--ds-font-size-base']   = $this->fontSizeValue($font['font_size_base'] ?? '16');
                    $vars['--ds-font-size-title']  = $font['font_size_title'] ?? '1.25';
                    return $vars;
                }
            } catch (\Throwable) {
                // ThemeProfile table not migrated yet — fall through to legacy path
            }
        }

        return $this->getCssVariablesFromThemeFont(
            $this->resolveTheme($siteId),
            $this->resolveFont($siteId)
        );
    }

    /**
     * تم (رنگ‌ها) با اعمال overrideهای tenant در صورت وجود.
     */
    public function resolveTheme(?int $siteId = null): array
    {
        $base = PlatformSettings::allTheme();
        if ($siteId && $this->tableExists('site_settings')) {
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
        if ($siteId && $this->tableExists('site_settings')) {
            $overrides = SiteSetting::allForSite($siteId);
            $fontKeys  = array_keys(PlatformSettings::FONT_KEYS);
            foreach ($fontKeys as $key) {
                if (isset($overrides[$key]) && (string) $overrides[$key] !== '') {
                    $base[$key] = $overrides[$key];
                }
            }
        }
        return $base;
    }

    /**
     * ساخت متغیرهای CSS از آرایهٔ theme و font (legacy path / API usage).
     *
     * @return array<string, string>
     */
    public function getCssVariablesFromThemeFont(array $theme, array $font): array
    {
        $mapping   = config('design.theme_key_to_palette', []);
        $cssTokens = config('design.css_tokens', []);
        $vars      = [];

        foreach ($mapping as $themeKey => $paletteKey) {
            $value  = $theme[$themeKey] ?? $this->defaultForPaletteKey($paletteKey);
            $cssVar = $cssTokens[$paletteKey] ?? '--ds-' . str_replace('_', '-', $paletteKey);
            $vars[$cssVar] = $value;
        }

        $vars['--ds-font-body']        = $font['font_body']        ?? config('design.fonts.body', 'Vazirmatn, Tahoma, sans-serif');
        $vars['--ds-font-title']       = $font['font_title']       ?? config('design.fonts.title', 'Vazirmatn, Tahoma, sans-serif');
        $vars['--ds-font-size-base']   = $this->fontSizeValue($font['font_size_base'] ?? config('design.typography.size_base', '16'));
        $vars['--ds-font-size-title']  = $font['font_size_title']  ?? config('design.typography.size_title_ratio', '1.25');

        foreach (config('design.legacy_aliases', []) as $legacy => $dsVar) {
            if (isset($vars[$dsVar])) {
                $vars[$legacy] = $vars[$dsVar];
            }
        }

        return $vars;
    }

    /** @return array<string> */
    public function getFontImports(?int $siteId = null): array
    {
        return $this->getFontImportsFromFont($this->resolveFont($siteId));
    }

    /** @return array<string> */
    public function getFontImportsFromFont(array $font): array
    {
        $urls  = [];
        $slots = config('design.fonts.custom_slots', 5);
        for ($i = 1; $i <= $slots; $i++) {
            $url = $font['font_custom_' . $i . '_url'] ?? '';
            if ($url !== '') {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    public function renderCssVariables(?int $siteId = null): string
    {
        $vars  = $this->getCssVariables($siteId);
        $lines = array_map(fn ($k, $v) => "    {$k}: {$v};", array_keys($vars), $vars);
        return implode("\n", $lines);
    }

    public function getAvailablePalettes(): array
    {
        return array_keys(config('design.palettes', ['default' => []]));
    }

    public function getPalette(string $name): array
    {
        $palettes = config('design.palettes', []);
        return $palettes[$name] ?? $palettes['default'] ?? [];
    }

    public function getTypographyScale(): array
    {
        return config('design.typography', []);
    }

    protected function defaultForPaletteKey(string $key): string
    {
        return config('design.palettes.default.' . $key, '#4f46e5');
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

    private function tableExists(string $table): bool
    {
        static $cache = [];
        return $cache[$table] ??= Schema::hasTable($table);
    }
}
