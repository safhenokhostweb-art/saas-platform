<?php

namespace App\Services\ThemeEngine;

use App\Models\ThemeProfile;

/**
 * Renders a ThemeProfile's tokens as a CSS custom property override block.
 * This block is injected after design-tokens.css in <head>, so the cascade
 * naturally overrides the static defaults without touching design-tokens.css.
 *
 * Also renders mobile/desktop JSON payload for Flutter and Electron clients.
 */
class ThemeTokenRenderer
{
    public function __construct(private ThemeProfileManager $manager) {}

    // ── CSS Rendering ────────────────────────────────────────────────────────

    /**
     * Render a full <style> block for a resolved ThemeProfile.
     *
     * Emits TWO blocks:
     *  1. :root { ... }  — all tokens (low specificity, covers global vars)
     *  2. #admin-sidebar { ... }  — sidebar-scoped vars at same or higher specificity
     *     than platform-admin-sidebar-theme.blade.php which scopes to #admin-sidebar.
     *
     * Without block 2, the scoped vars from platform-admin-sidebar-theme would win
     * because #admin-sidebar has higher specificity than :root.
     */
    public function renderCssBlock(?ThemeProfile $profile): string
    {
        if (!$profile) {
            return '';
        }
        $vars = $profile->toCssVars();
        if (empty($vars)) {
            return '';
        }

        // Block 1: :root — all vars
        $rootLines = [];
        foreach ($vars as $key => $value) {
            $rootLines[] = "  {$key}: {$value};";
        }

        // Block 2: #admin-sidebar — override sidebar-scoped vars
        // platform-admin-sidebar-theme.blade.php scopes these to #admin-sidebar,
        // so we must match or exceed that specificity.
        $sidebarKeys = [
            '--platform-sidebar-bg-top', '--platform-sidebar-bg-bottom',
            '--platform-sidebar-text',   '--platform-sidebar-muted',
            '--platform-sidebar-accent', '--platform-sidebar-border',
        ];
        $sidebarLines = [];
        foreach ($sidebarKeys as $key) {
            if (isset($vars[$key])) {
                $sidebarLines[] = "  {$key}: {$vars[$key]};";
            }
        }
        // Recompute hover/active from the new accent value
        $accent = $vars['--platform-sidebar-accent'] ?? null;
        if ($accent && !empty($sidebarLines)) {
            $sidebarLines[] = "  --platform-sidebar-hover: color-mix(in srgb, {$accent} 20%, transparent);";
            $sidebarLines[] = "  --platform-sidebar-active: color-mix(in srgb, {$accent} 32%, transparent);";
        }

        $name = htmlspecialchars($profile->name, ENT_QUOTES, 'UTF-8');
        $out  = "<style id=\"theme-engine-tokens\" data-preset=\"{$profile->preset_key}\" data-theme=\"{$name}\">\n";
        $out .= ":root {\n" . implode("\n", $rootLines) . "\n}\n";
        if (!empty($sidebarLines)) {
            $out .= "#admin-sidebar {\n" . implode("\n", $sidebarLines) . "\n}\n";
        }
        $out .= "</style>";

        return $out;
    }

    /**
     * Render CSS for a specific site (resolves tenant override first).
     */
    public function renderForSite(?int $siteId): string
    {
        $profile = $this->manager->resolve($siteId);
        return $this->renderCssBlock($profile);
    }

    /**
     * Render CSS for the platform (no tenant override).
     */
    public function renderPlatform(): string
    {
        $profile = $this->manager->resolvePlatform();
        return $this->renderCssBlock($profile);
    }

    // ── Mobile / Desktop JSON Payload ────────────────────────────────────────

    /**
     * Returns the active theme as a structured JSON payload for Flutter/Electron.
     * Maps CSS vars to semantic Flutter-friendly keys.
     */
    public function mobilePayload(?int $siteId = null): array
    {
        $profile = $this->manager->resolve($siteId);
        if (!$profile) {
            return $this->fallbackMobilePayload();
        }

        $c  = $profile->colors      ?? [];
        $s  = $profile->surfaces    ?? [];
        $t  = $profile->typography  ?? [];
        $b  = $profile->borders     ?? [];
        $sb = $profile->sidebar     ?? [];
        $h  = $profile->header      ?? [];

        return [
            'theme_id'    => $profile->id,
            'theme_name'  => $profile->name,
            'preset_key'  => $profile->preset_key,
            'colors' => [
                'primary'          => $c['primary']['500'] ?? '#3b82f6',
                'primary_dark'     => $c['primary']['700'] ?? '#1d4ed8',
                'primary_light'    => $c['primary']['100'] ?? '#dbeafe',
                'accent'           => ($c['accent']['base'] ?? '#4f46e5'),
                'accent_hover'     => ($c['accent']['hover'] ?? '#4338ca'),
                'secondary'        => ($c['secondary']['base'] ?? '#a855f7'),
                'success'          => ($c['success']['base'] ?? '#16a34a'),
                'warning'          => ($c['warning']['base'] ?? '#d97706'),
                'danger'           => ($c['danger']['base'] ?? '#dc2626'),
                'info'             => ($c['info']['base'] ?? '#2563eb'),
            ],
            'surfaces' => [
                'background'       => $s['bg'] ?? '#f1f5f9',
                'surface'          => $s['surface'] ?? '#ffffff',
                'card'             => $s['card'] ?? '#ffffff',
                'surface_soft'     => $s['surface_soft'] ?? '#f8fafc',
            ],
            'typography' => [
                'text_primary'     => $t['text_primary'] ?? '#0f172a',
                'text_secondary'   => $t['text_secondary'] ?? '#334155',
                'text_muted'       => $t['text_muted'] ?? '#64748b',
                'link'             => $t['link'] ?? '#4f46e5',
            ],
            'sidebar' => [
                'background'       => $sb['bg'] ?? '#1e293b',
                'background_end'   => $sb['bg_bottom'] ?? '#0f172a',
                'text'             => $sb['text'] ?? '#f1f5f9',
                'muted'            => $sb['muted'] ?? '#94a3b8',
                'accent'           => $sb['accent'] ?? '#60a5fa',
                'border'           => $sb['border'] ?? '#334155',
            ],
            'header' => [
                'background'       => $h['bg'] ?? '#ffffff',
                'text'             => $h['text'] ?? '#374151',
                'link_active'      => $h['link_active'] ?? '#4f46e5',
            ],
            'borders' => [
                'default'          => $b['border'] ?? '#e2e8f0',
                'soft'             => $b['divider'] ?? '#f1f5f9',
                'strong'           => $b['strong'] ?? '#cbd5e1',
            ],
        ];
    }

    private function fallbackMobilePayload(): array
    {
        return [
            'theme_id'    => null,
            'theme_name'  => 'Enterprise Blue',
            'preset_key'  => 'enterprise-blue',
            'colors' => [
                'primary'       => '#3b82f6',
                'primary_dark'  => '#1d4ed8',
                'primary_light' => '#dbeafe',
                'accent'        => '#4f46e5',
                'accent_hover'  => '#4338ca',
                'secondary'     => '#a855f7',
                'success'       => '#16a34a',
                'warning'       => '#d97706',
                'danger'        => '#dc2626',
                'info'          => '#2563eb',
            ],
            'surfaces' => [
                'background'   => '#f1f5f9',
                'surface'      => '#ffffff',
                'card'         => '#ffffff',
                'surface_soft' => '#f8fafc',
            ],
            'typography' => [
                'text_primary'   => '#0f172a',
                'text_secondary' => '#334155',
                'text_muted'     => '#64748b',
                'link'           => '#4f46e5',
            ],
            'sidebar' => [
                'background'     => '#1e293b',
                'background_end' => '#0f172a',
                'text'           => '#f1f5f9',
                'muted'          => '#94a3b8',
                'accent'         => '#60a5fa',
                'border'         => '#334155',
            ],
            'header' => [
                'background'  => '#ffffff',
                'text'        => '#374151',
                'link_active' => '#4f46e5',
            ],
            'borders' => [
                'default' => '#e2e8f0',
                'soft'    => '#f1f5f9',
                'strong'  => '#cbd5e1',
            ],
        ];
    }
}
