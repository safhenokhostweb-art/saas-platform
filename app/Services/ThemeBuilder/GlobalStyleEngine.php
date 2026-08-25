<?php
namespace App\Services\ThemeBuilder;

use App\Services\DesignSystemService;

/**
 * Merges Theme Builder global_styles with platform design tokens.
 * Outputs CSS variables and optional custom CSS for front-end.
 */
class GlobalStyleEngine
{
    public function __construct(
        protected DesignSystemService $designSystem
    ) {}

    /**
     * Generate :root CSS variables from theme global_styles + platform theme (site override).
     */
    public function getCssVariables(array $globalStyles, ?int $siteId = null): array
    {
        $platform = $this->designSystem->getCssVariables($siteId);
        // Theme Builder authoring (MarketplaceThemeSeedCommand::styles(), the AI
        // generator's global_styles schema, and every theme in production today)
        // stores colors as flat keys (primary_color, secondary_color, ...), not
        // nested under a "colors" object. Read the flat keys first and fall back
        // to the nested shape for forward compatibility, so a theme's own colors
        // are actually applied instead of silently falling back to the platform
        // default on every theme.
        $colors = $globalStyles['colors'] ?? [];
        $typography = $globalStyles['typography'] ?? [];
        $spacing = $globalStyles['spacing'] ?? [];
        $borderRadius = $globalStyles['border_radius'] ?? [];
        $shadows = $globalStyles['shadows'] ?? [];

        $vars = array_merge($platform, [
            '--tb-primary' => $globalStyles['primary_color'] ?? $colors['primary'] ?? $platform['--ds-primary'] ?? '#4f46e5',
            '--tb-secondary' => $globalStyles['secondary_color'] ?? $colors['secondary'] ?? $platform['--ds-secondary'] ?? '#6366f1',
            '--tb-accent' => $globalStyles['accent_color'] ?? $colors['accent'] ?? '#818cf8',
            '--tb-background' => $globalStyles['background_color'] ?? $colors['background'] ?? '#ffffff',
            '--tb-surface' => $colors['surface'] ?? '#f9fafb',
            '--tb-text' => $globalStyles['text_color'] ?? $colors['text'] ?? '#111827',
            '--tb-text-muted' => $colors['text_muted'] ?? '#6b7280',
            '--tb-font-heading' => $globalStyles['font_family'] ?? $typography['font_heading'] ?? 'Vazirmatn, Tahoma, sans-serif',
            '--tb-font-body' => $globalStyles['font_family'] ?? $typography['font_body'] ?? 'Vazirmatn, Tahoma, sans-serif',
            '--tb-scale' => $typography['scale'] ?? '1.25',
            '--tb-radius-sm' => $borderRadius['sm'] ?? '0.25rem',
            '--tb-radius-md' => $borderRadius['md'] ?? '0.5rem',
            '--tb-radius-lg' => $borderRadius['lg'] ?? '0.75rem',
            '--tb-shadow-sm' => $shadows['sm'] ?? '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            '--tb-shadow-md' => $shadows['md'] ?? '0 4px 6px -1px rgb(0 0 0 / 0.1)',
            '--tb-shadow-lg' => $shadows['lg'] ?? '0 10px 15px -3px rgb(0 0 0 / 0.1)',
        ]);

        if (!empty($spacing['unit'])) {
            $vars['--tb-spacing-unit'] = $spacing['unit'] ?? 'rem';
        }
        if (isset($spacing['scale'])) {
            $vars['--tb-spacing-scale'] = (string) $spacing['scale'];
        }

        return $vars;
    }

    /**
     * Render a full <style> block for :root and optional dark mode.
     */
    public function renderCssBlock(array $globalStyles, ?int $siteId = null): string
    {
        $vars = $this->getCssVariables($globalStyles, $siteId);
        $lines = array_map(fn ($k, $v) => "  {$k}: {$v};", array_keys($vars), $vars);
        $css = ":root {\n" . implode("\n", $lines) . "\n}\n";

        $dark = $globalStyles['dark_mode'] ?? [];
        if (!empty($dark['enabled'])) {
            $css .= "\n[data-theme=\"dark\"] {\n";
            $darkVars = [
                '--tb-primary' => $dark['primary'] ?? $vars['--tb-primary'],
                '--tb-background' => $dark['background'] ?? '#111827',
                '--tb-surface' => $dark['surface'] ?? '#1f2937',
                '--tb-text' => $dark['text'] ?? '#f9fafb',
            ];
            foreach ($darkVars as $k => $v) {
                $css .= "  {$k}: {$v};\n";
            }
            $css .= "}\n";
        }

        $custom = $globalStyles['custom_css'] ?? '';
        if (trim($custom) !== '') {
            $css .= "\n/* Theme Builder custom CSS */\n" . $custom . "\n";
        }

        return $css;
    }
}
