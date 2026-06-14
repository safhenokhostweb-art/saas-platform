<?php

namespace App\Services\ThemeEngine;

/**
 * Enterprise Theme Preset Registry.
 * Defines 8 professionally curated presets — each provides complete token values
 * for all color groups. No hardcoded hex values outside this registry.
 */
class ThemePresetRegistry
{
    /**
     * Returns all 8 enterprise presets as associative arrays.
     * Keys match the ThemeProfile fillable fields.
     */
    public static function all(): array
    {
        return [
            static::enterpriseBlue(),
            static::modernIndigo(),
            static::emeraldPro(),
            static::royalPurple(),
            static::midnightDark(),
            static::graphite(),
            static::oceanTeal(),
            static::executiveGold(),
        ];
    }

    public static function get(string $key): ?array
    {
        return collect(static::all())
            ->firstWhere('preset_key', $key);
    }

    // ═══════════════════════════════════════════════════════════════
    //  1. Enterprise Blue (DEFAULT)
    // ═══════════════════════════════════════════════════════════════

    public static function enterpriseBlue(): array
    {
        return [
            'name'        => 'Enterprise Blue',
            'slug'        => 'enterprise-blue',
            'description' => 'Professional corporate blue — the platform default. Clean, trustworthy, and accessible.',
            'preset_key'  => 'enterprise-blue',
            'is_default'  => true,
            'is_system'   => true,
            'colors' => [
                'primary' => [
                    '50' => '#eff6ff', '100' => '#dbeafe', '200' => '#bfdbfe',
                    '300' => '#93c5fd', '400' => '#60a5fa', '500' => '#3b82f6',
                    '600' => '#2563eb', '700' => '#1d4ed8', '800' => '#1e40af', '900' => '#1e3a8a',
                ],
                'secondary' => ['base' => '#a855f7', 'hover' => '#9333ea', 'light' => '#faf5ff'],
                'accent'    => ['base' => '#4f46e5', 'hover' => '#4338ca', 'light' => '#eef2ff', 'ring' => 'rgb(99 102 241 / 0.25)'],
                'success'   => ['base' => '#16a34a', 'hover' => '#15803d', 'bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#6ee7b7', 'light' => '#f0fdf4'],
                'warning'   => ['base' => '#d97706', 'hover' => '#b45309', 'bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fcd34d', 'light' => '#fffbeb'],
                'danger'    => ['base' => '#dc2626', 'hover' => '#b91c1c', 'bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fca5a5', 'light' => '#fef2f2'],
                'info'      => ['base' => '#2563eb', 'hover' => '#1d4ed8', 'bg' => '#eff6ff', 'text' => '#1e40af', 'border' => '#93c5fd', 'light' => '#f0f7ff'],
            ],
            'surfaces' => [
                'bg'           => '#f1f5f9',
                'surface'      => '#ffffff',
                'surface_soft' => '#f8fafc',
                'surface_muted'=> '#f1f5f9',
                'card'         => '#ffffff',
            ],
            'typography' => [
                'text_primary'   => '#0f172a',
                'text_secondary' => '#334155',
                'text_muted'     => '#64748b',
                'link'           => '#4f46e5',
                'link_hover'     => '#4338ca',
            ],
            'borders' => ['border' => '#e2e8f0', 'divider' => '#f1f5f9', 'strong' => '#cbd5e1'],
            'sidebar' => [
                'bg'        => '#1e293b',
                'bg_bottom' => '#0f172a',
                'text'      => '#f1f5f9',
                'muted'     => '#94a3b8',
                'accent'    => '#60a5fa',
                'border'    => '#334155',
            ],
            'header' => ['bg' => '#ffffff', 'text' => '#374151', 'link_active' => '#4f46e5'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  2. Modern Indigo
    // ═══════════════════════════════════════════════════════════════

    public static function modernIndigo(): array
    {
        return [
            'name'        => 'Modern Indigo',
            'slug'        => 'modern-indigo',
            'description' => 'Deep indigo palette — bold, modern, and high-contrast for tech-forward brands.',
            'preset_key'  => 'modern-indigo',
            'is_default'  => false,
            'is_system'   => true,
            'colors' => [
                'primary' => [
                    '50' => '#eef2ff', '100' => '#e0e7ff', '200' => '#c7d2fe',
                    '300' => '#a5b4fc', '400' => '#818cf8', '500' => '#6366f1',
                    '600' => '#4f46e5', '700' => '#4338ca', '800' => '#3730a3', '900' => '#312e81',
                ],
                'secondary' => ['base' => '#7c3aed', 'hover' => '#6d28d9', 'light' => '#f5f3ff'],
                'accent'    => ['base' => '#6366f1', 'hover' => '#4f46e5', 'light' => '#eef2ff', 'ring' => 'rgb(99 102 241 / 0.30)'],
                'success'   => ['base' => '#16a34a', 'hover' => '#15803d', 'bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#6ee7b7', 'light' => '#f0fdf4'],
                'warning'   => ['base' => '#d97706', 'hover' => '#b45309', 'bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fcd34d', 'light' => '#fffbeb'],
                'danger'    => ['base' => '#dc2626', 'hover' => '#b91c1c', 'bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fca5a5', 'light' => '#fef2f2'],
                'info'      => ['base' => '#4f46e5', 'hover' => '#4338ca', 'bg' => '#eef2ff', 'text' => '#3730a3', 'border' => '#a5b4fc', 'light' => '#f0f0ff'],
            ],
            'surfaces' => [
                'bg' => '#f5f4ff', 'surface' => '#ffffff', 'surface_soft' => '#faf9ff',
                'surface_muted' => '#f0eeff', 'card' => '#ffffff',
            ],
            'typography' => [
                'text_primary' => '#1e1b4b', 'text_secondary' => '#3730a3',
                'text_muted' => '#6366f1', 'link' => '#4f46e5', 'link_hover' => '#4338ca',
            ],
            'borders' => ['border' => '#e0e7ff', 'divider' => '#f0eeff', 'strong' => '#c7d2fe'],
            'sidebar' => [
                'bg' => '#312e81', 'bg_bottom' => '#1e1b4b',
                'text' => '#e0e7ff', 'muted' => '#a5b4fc', 'accent' => '#818cf8', 'border' => '#4338ca',
            ],
            'header' => ['bg' => '#f8f7ff', 'text' => '#1e1b4b', 'link_active' => '#4f46e5'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  3. Emerald Pro
    // ═══════════════════════════════════════════════════════════════

    public static function emeraldPro(): array
    {
        return [
            'name'        => 'Emerald Pro',
            'slug'        => 'emerald-pro',
            'description' => 'Fresh emerald green — energetic, growth-focused, ideal for fintech and health platforms.',
            'preset_key'  => 'emerald-pro',
            'is_default'  => false,
            'is_system'   => true,
            'colors' => [
                'primary' => [
                    '50' => '#f0fdf4', '100' => '#dcfce7', '200' => '#bbf7d0',
                    '300' => '#86efac', '400' => '#4ade80', '500' => '#22c55e',
                    '600' => '#16a34a', '700' => '#15803d', '800' => '#166534', '900' => '#14532d',
                ],
                'secondary' => ['base' => '#0891b2', 'hover' => '#0e7490', 'light' => '#ecfeff'],
                'accent'    => ['base' => '#059669', 'hover' => '#047857', 'light' => '#ecfdf5', 'ring' => 'rgb(5 150 105 / 0.25)'],
                'success'   => ['base' => '#16a34a', 'hover' => '#15803d', 'bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#6ee7b7', 'light' => '#f0fdf4'],
                'warning'   => ['base' => '#d97706', 'hover' => '#b45309', 'bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fcd34d', 'light' => '#fffbeb'],
                'danger'    => ['base' => '#dc2626', 'hover' => '#b91c1c', 'bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fca5a5', 'light' => '#fef2f2'],
                'info'      => ['base' => '#0891b2', 'hover' => '#0e7490', 'bg' => '#ecfeff', 'text' => '#164e63', 'border' => '#67e8f9', 'light' => '#f0fdff'],
            ],
            'surfaces' => [
                'bg' => '#f0fdf4', 'surface' => '#ffffff', 'surface_soft' => '#f7fef9',
                'surface_muted' => '#ecfdf5', 'card' => '#ffffff',
            ],
            'typography' => [
                'text_primary' => '#052e16', 'text_secondary' => '#14532d',
                'text_muted' => '#166534', 'link' => '#059669', 'link_hover' => '#047857',
            ],
            'borders' => ['border' => '#bbf7d0', 'divider' => '#dcfce7', 'strong' => '#86efac'],
            'sidebar' => [
                'bg' => '#065f46', 'bg_bottom' => '#022c22',
                'text' => '#d1fae5', 'muted' => '#6ee7b7', 'accent' => '#34d399', 'border' => '#065f46',
            ],
            'header' => ['bg' => '#f0fdf4', 'text' => '#052e16', 'link_active' => '#059669'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  4. Royal Purple
    // ═══════════════════════════════════════════════════════════════

    public static function royalPurple(): array
    {
        return [
            'name'        => 'Royal Purple',
            'slug'        => 'royal-purple',
            'description' => 'Majestic purple — premium, creative, ideal for design agencies and luxury SaaS.',
            'preset_key'  => 'royal-purple',
            'is_default'  => false,
            'is_system'   => true,
            'colors' => [
                'primary' => [
                    '50' => '#faf5ff', '100' => '#f3e8ff', '200' => '#e9d5ff',
                    '300' => '#d8b4fe', '400' => '#c084fc', '500' => '#a855f7',
                    '600' => '#9333ea', '700' => '#7c3aed', '800' => '#6b21a8', '900' => '#581c87',
                ],
                'secondary' => ['base' => '#db2777', 'hover' => '#be185d', 'light' => '#fdf2f8'],
                'accent'    => ['base' => '#7c3aed', 'hover' => '#6d28d9', 'light' => '#f5f3ff', 'ring' => 'rgb(124 58 237 / 0.25)'],
                'success'   => ['base' => '#16a34a', 'hover' => '#15803d', 'bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#6ee7b7', 'light' => '#f0fdf4'],
                'warning'   => ['base' => '#d97706', 'hover' => '#b45309', 'bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fcd34d', 'light' => '#fffbeb'],
                'danger'    => ['base' => '#dc2626', 'hover' => '#b91c1c', 'bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fca5a5', 'light' => '#fef2f2'],
                'info'      => ['base' => '#7c3aed', 'hover' => '#6d28d9', 'bg' => '#f5f3ff', 'text' => '#4c1d95', 'border' => '#c4b5fd', 'light' => '#faf5ff'],
            ],
            'surfaces' => [
                'bg' => '#faf5ff', 'surface' => '#ffffff', 'surface_soft' => '#fdf8ff',
                'surface_muted' => '#f5f0fd', 'card' => '#ffffff',
            ],
            'typography' => [
                'text_primary' => '#1a0536', 'text_secondary' => '#3b0764',
                'text_muted' => '#7c3aed', 'link' => '#7c3aed', 'link_hover' => '#6d28d9',
            ],
            'borders' => ['border' => '#e9d5ff', 'divider' => '#f5f0fd', 'strong' => '#d8b4fe'],
            'sidebar' => [
                'bg' => '#3b0764', 'bg_bottom' => '#1a0036',
                'text' => '#f3e8ff', 'muted' => '#c084fc', 'accent' => '#e879f9', 'border' => '#6b21a8',
            ],
            'header' => ['bg' => '#faf5ff', 'text' => '#1a0536', 'link_active' => '#7c3aed'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  5. Midnight Dark
    // ═══════════════════════════════════════════════════════════════

    public static function midnightDark(): array
    {
        return [
            'name'        => 'Midnight Dark',
            'slug'        => 'midnight-dark',
            'description' => 'Full dark mode — near-black background with soft light accents for minimal eye strain.',
            'preset_key'  => 'midnight-dark',
            'is_default'  => false,
            'is_system'   => true,
            'colors' => [
                'primary' => [
                    '50' => '#1e293b', '100' => '#1e3a5f', '200' => '#1e4076',
                    '300' => '#1e52a0', '400' => '#3b82f6', '500' => '#60a5fa',
                    '600' => '#93c5fd', '700' => '#bfdbfe', '800' => '#dbeafe', '900' => '#eff6ff',
                ],
                'secondary' => ['base' => '#a5b4fc', 'hover' => '#818cf8', 'light' => '#1e1b4b'],
                'accent'    => ['base' => '#818cf8', 'hover' => '#a5b4fc', 'light' => '#1e1b4b', 'ring' => 'rgb(129 140 248 / 0.30)'],
                'success'   => ['base' => '#34d399', 'hover' => '#10b981', 'bg' => '#022c22', 'text' => '#6ee7b7', 'border' => '#065f46', 'light' => '#022c22'],
                'warning'   => ['base' => '#fbbf24', 'hover' => '#f59e0b', 'bg' => '#1c1205', 'text' => '#fcd34d', 'border' => '#92400e', 'light' => '#1c1205'],
                'danger'    => ['base' => '#f87171', 'hover' => '#ef4444', 'bg' => '#1f0606', 'text' => '#fca5a5', 'border' => '#991b1b', 'light' => '#1f0606'],
                'info'      => ['base' => '#60a5fa', 'hover' => '#3b82f6', 'bg' => '#0a1628', 'text' => '#93c5fd', 'border' => '#1e40af', 'light' => '#0a1628'],
            ],
            'surfaces' => [
                'bg' => '#030712', 'surface' => '#111827', 'surface_soft' => '#0f172a',
                'surface_muted' => '#1f2937', 'card' => '#1f2937',
            ],
            'typography' => [
                'text_primary' => '#f9fafb', 'text_secondary' => '#e5e7eb',
                'text_muted' => '#9ca3af', 'link' => '#818cf8', 'link_hover' => '#a5b4fc',
            ],
            'borders' => ['border' => '#374151', 'divider' => '#1f2937', 'strong' => '#4b5563'],
            'sidebar' => [
                'bg' => '#111827', 'bg_bottom' => '#030712',
                'text' => '#e5e7eb', 'muted' => '#6b7280', 'accent' => '#818cf8', 'border' => '#374151',
            ],
            'header' => ['bg' => '#111827', 'text' => '#f9fafb', 'link_active' => '#818cf8'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  6. Graphite
    // ═══════════════════════════════════════════════════════════════

    public static function graphite(): array
    {
        return [
            'name'        => 'Graphite',
            'slug'        => 'graphite',
            'description' => 'Neutral gray palette — timeless, understated, and versatile for any industry.',
            'preset_key'  => 'graphite',
            'is_default'  => false,
            'is_system'   => true,
            'colors' => [
                'primary' => [
                    '50' => '#f9fafb', '100' => '#f3f4f6', '200' => '#e5e7eb',
                    '300' => '#d1d5db', '400' => '#9ca3af', '500' => '#6b7280',
                    '600' => '#4b5563', '700' => '#374151', '800' => '#1f2937', '900' => '#111827',
                ],
                'secondary' => ['base' => '#3b82f6', 'hover' => '#2563eb', 'light' => '#eff6ff'],
                'accent'    => ['base' => '#374151', 'hover' => '#1f2937', 'light' => '#f9fafb', 'ring' => 'rgb(55 65 81 / 0.25)'],
                'success'   => ['base' => '#16a34a', 'hover' => '#15803d', 'bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#6ee7b7', 'light' => '#f0fdf4'],
                'warning'   => ['base' => '#d97706', 'hover' => '#b45309', 'bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fcd34d', 'light' => '#fffbeb'],
                'danger'    => ['base' => '#dc2626', 'hover' => '#b91c1c', 'bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fca5a5', 'light' => '#fef2f2'],
                'info'      => ['base' => '#3b82f6', 'hover' => '#2563eb', 'bg' => '#eff6ff', 'text' => '#1e40af', 'border' => '#93c5fd', 'light' => '#f0f7ff'],
            ],
            'surfaces' => [
                'bg' => '#f3f4f6', 'surface' => '#ffffff', 'surface_soft' => '#f9fafb',
                'surface_muted' => '#f3f4f6', 'card' => '#ffffff',
            ],
            'typography' => [
                'text_primary' => '#111827', 'text_secondary' => '#374151',
                'text_muted' => '#6b7280', 'link' => '#374151', 'link_hover' => '#1f2937',
            ],
            'borders' => ['border' => '#e5e7eb', 'divider' => '#f3f4f6', 'strong' => '#d1d5db'],
            'sidebar' => [
                'bg' => '#1f2937', 'bg_bottom' => '#111827',
                'text' => '#f9fafb', 'muted' => '#9ca3af', 'accent' => '#9ca3af', 'border' => '#374151',
            ],
            'header' => ['bg' => '#f9fafb', 'text' => '#374151', 'link_active' => '#4b5563'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  7. Ocean Teal
    // ═══════════════════════════════════════════════════════════════

    public static function oceanTeal(): array
    {
        return [
            'name'        => 'Ocean Teal',
            'slug'        => 'ocean-teal',
            'description' => 'Refreshing teal-cyan — calm, professional, great for healthcare and productivity apps.',
            'preset_key'  => 'ocean-teal',
            'is_default'  => false,
            'is_system'   => true,
            'colors' => [
                'primary' => [
                    '50' => '#f0fdfa', '100' => '#ccfbf1', '200' => '#99f6e4',
                    '300' => '#5eead4', '400' => '#2dd4bf', '500' => '#14b8a6',
                    '600' => '#0d9488', '700' => '#0f766e', '800' => '#115e59', '900' => '#134e4a',
                ],
                'secondary' => ['base' => '#0891b2', 'hover' => '#0e7490', 'light' => '#ecfeff'],
                'accent'    => ['base' => '#0d9488', 'hover' => '#0f766e', 'light' => '#ccfbf1', 'ring' => 'rgb(13 148 136 / 0.25)'],
                'success'   => ['base' => '#16a34a', 'hover' => '#15803d', 'bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#6ee7b7', 'light' => '#f0fdf4'],
                'warning'   => ['base' => '#d97706', 'hover' => '#b45309', 'bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fcd34d', 'light' => '#fffbeb'],
                'danger'    => ['base' => '#dc2626', 'hover' => '#b91c1c', 'bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fca5a5', 'light' => '#fef2f2'],
                'info'      => ['base' => '#0891b2', 'hover' => '#0e7490', 'bg' => '#ecfeff', 'text' => '#164e63', 'border' => '#67e8f9', 'light' => '#f0fdff'],
            ],
            'surfaces' => [
                'bg' => '#f0fdfa', 'surface' => '#ffffff', 'surface_soft' => '#f7fffe',
                'surface_muted' => '#e6fff9', 'card' => '#ffffff',
            ],
            'typography' => [
                'text_primary' => '#042f2e', 'text_secondary' => '#134e4a',
                'text_muted' => '#0f766e', 'link' => '#0d9488', 'link_hover' => '#0f766e',
            ],
            'borders' => ['border' => '#5eead4', 'divider' => '#ccfbf1', 'strong' => '#2dd4bf'],
            'sidebar' => [
                'bg' => '#134e4a', 'bg_bottom' => '#042f2e',
                'text' => '#ccfbf1', 'muted' => '#5eead4', 'accent' => '#2dd4bf', 'border' => '#0f766e',
            ],
            'header' => ['bg' => '#f0fdfa', 'text' => '#042f2e', 'link_active' => '#0d9488'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  8. Executive Gold
    // ═══════════════════════════════════════════════════════════════

    public static function executiveGold(): array
    {
        return [
            'name'        => 'Executive Gold',
            'slug'        => 'executive-gold',
            'description' => 'Warm amber and gold — authoritative, premium, ideal for executive dashboards and finance.',
            'preset_key'  => 'executive-gold',
            'is_default'  => false,
            'is_system'   => true,
            'colors' => [
                'primary' => [
                    '50' => '#fffbeb', '100' => '#fef3c7', '200' => '#fde68a',
                    '300' => '#fcd34d', '400' => '#fbbf24', '500' => '#f59e0b',
                    '600' => '#d97706', '700' => '#b45309', '800' => '#92400e', '900' => '#78350f',
                ],
                'secondary' => ['base' => '#ea580c', 'hover' => '#c2410c', 'light' => '#fff7ed'],
                'accent'    => ['base' => '#b45309', 'hover' => '#92400e', 'light' => '#fde68a', 'ring' => 'rgb(180 83 9 / 0.25)'],
                'success'   => ['base' => '#16a34a', 'hover' => '#15803d', 'bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#6ee7b7', 'light' => '#f0fdf4'],
                'warning'   => ['base' => '#f59e0b', 'hover' => '#d97706', 'bg' => '#fef3c7', 'text' => '#78350f', 'border' => '#fcd34d', 'light' => '#fffbeb'],
                'danger'    => ['base' => '#dc2626', 'hover' => '#b91c1c', 'bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fca5a5', 'light' => '#fef2f2'],
                'info'      => ['base' => '#d97706', 'hover' => '#b45309', 'bg' => '#fef3c7', 'text' => '#78350f', 'border' => '#fcd34d', 'light' => '#fffbeb'],
            ],
            'surfaces' => [
                'bg' => '#fffbeb', 'surface' => '#ffffff', 'surface_soft' => '#fefce8',
                'surface_muted' => '#fef9c3', 'card' => '#ffffff',
            ],
            'typography' => [
                'text_primary' => '#1c1917', 'text_secondary' => '#44403c',
                'text_muted' => '#78716c', 'link' => '#b45309', 'link_hover' => '#92400e',
            ],
            'borders' => ['border' => '#fcd34d', 'divider' => '#fef3c7', 'strong' => '#fbbf24'],
            'sidebar' => [
                'bg' => '#292524', 'bg_bottom' => '#1c1917',
                'text' => '#fef3c7', 'muted' => '#fbbf24', 'accent' => '#fcd34d', 'border' => '#44403c',
            ],
            'header' => ['bg' => '#fffbeb', 'text' => '#1c1917', 'link_active' => '#b45309'],
        ];
    }
}
