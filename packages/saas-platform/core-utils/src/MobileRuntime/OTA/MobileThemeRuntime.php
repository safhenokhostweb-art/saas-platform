<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\OTA;

use Illuminate\Support\Facades\Cache;

class MobileThemeRuntime
{
    private const PREFIX = 'mobile:theme:';
    private const TTL    = 3600;

    private array $defaultTheme = [
        'primary'          => '#6366F1',
        'secondary'        => '#8B5CF6',
        'background'       => '#FFFFFF',
        'surface'          => '#F9FAFB',
        'text_primary'     => '#111827',
        'text_secondary'   => '#6B7280',
        'border'           => '#E5E7EB',
        'error'            => '#EF4444',
        'warning'          => '#F59E0B',
        'success'          => '#10B981',
        'info'             => '#3B82F6',
        'border_radius_sm' => '4px',
        'border_radius_md' => '8px',
        'border_radius_lg' => '16px',
        'font_size_sm'     => '12px',
        'font_size_md'     => '14px',
        'font_size_lg'     => '16px',
        'font_size_xl'     => '20px',
        'spacing_1'        => '4px',
        'spacing_2'        => '8px',
        'spacing_3'        => '12px',
        'spacing_4'        => '16px',
        'spacing_5'        => '20px',
        'spacing_6'        => '24px',
        'spacing_7'        => '28px',
        'spacing_8'        => '32px',
    ];

    public function getTheme(string $tenantId): array
    {
        $tenantOverrides = Cache::get(self::PREFIX . $tenantId, []);
        return $this->mergeTokens($this->getDefaultTheme(), $tenantOverrides);
    }

    public function getDefaultTheme(): array
    {
        return $this->defaultTheme;
    }

    public function setTenantTheme(string $tenantId, array $tokens): void
    {
        Cache::put(self::PREFIX . $tenantId, $tokens, self::TTL);
    }

    public function resetToDefault(string $tenantId): void
    {
        Cache::forget(self::PREFIX . $tenantId);
    }

    public function mergeTokens(array $base, array $override): array
    {
        return array_merge($base, array_intersect_key($override, array_flip(array_keys($base))));
    }

    public function getColorPalette(string $tenantId): array
    {
        $theme = $this->getTheme($tenantId);
        return array_filter($theme, fn ($v, $k) => in_array($k, ['primary', 'secondary', 'background', 'surface', 'text_primary', 'text_secondary', 'border', 'error', 'warning', 'success', 'info'], true), ARRAY_FILTER_USE_BOTH);
    }

    public function getTypography(string $tenantId): array
    {
        $theme = $this->getTheme($tenantId);
        return array_filter($theme, fn ($k) => str_starts_with($k, 'font_'), ARRAY_FILTER_USE_KEY);
    }

    public function getSpacing(string $tenantId): array
    {
        $theme = $this->getTheme($tenantId);
        return array_filter($theme, fn ($k) => str_starts_with($k, 'spacing_'), ARRAY_FILTER_USE_KEY);
    }
}
