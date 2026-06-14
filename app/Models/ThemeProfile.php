<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Enterprise Theme Profile — canonical color system for the Theme Engine.
 * Stores structured color groups that map to --ui-* / --ds-* CSS custom properties.
 */
class ThemeProfile extends Model
{
    protected $fillable = [
        'name', 'slug', 'description',
        'is_default', 'is_system', 'is_active',
        'preset_key',
        'colors', 'surfaces', 'typography', 'borders', 'sidebar', 'header',
    ];

    protected $casts = [
        'is_default'  => 'boolean',
        'is_system'   => 'boolean',
        'is_active'   => 'boolean',
        'colors'      => 'array',
        'surfaces'    => 'array',
        'typography'  => 'array',
        'borders'     => 'array',
        'sidebar'     => 'array',
        'header'      => 'array',
    ];

    public function tenantOverrides(): HasMany
    {
        return $this->hasMany(TenantThemeOverride::class);
    }

    // ── Queries ──────────────────────────────────────────────────────────────

    public static function activePlatform(): ?self
    {
        return self::where('is_active', true)->first();
    }

    public static function defaultPreset(): ?self
    {
        return self::where('is_default', true)->first()
            ?? self::where('preset_key', 'enterprise-blue')->first()
            ?? self::orderBy('id')->first();
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    // ── Token Resolution ─────────────────────────────────────────────────────

    /**
     * Get the full set of CSS variable overrides for this profile.
     * Maps semantic color groups → --ui-* and --ds-* custom properties.
     */
    public function toCssVars(): array
    {
        $c  = $this->colors      ?? [];
        $s  = $this->surfaces    ?? [];
        $t  = $this->typography  ?? [];
        $b  = $this->borders     ?? [];
        $sb = $this->sidebar     ?? [];
        $h  = $this->header      ?? [];

        $primary   = $c['primary']   ?? [];
        $secondary = $c['secondary'] ?? [];
        $accent    = $c['accent']    ?? [];
        $success   = $c['success']   ?? [];
        $warning   = $c['warning']   ?? [];
        $danger    = $c['danger']    ?? [];
        $info      = $c['info']      ?? [];

        $vars = [];

        // ── Primary palette ──────────────────────────────────────────────
        foreach (['50','100','200','300','400','500','600','700','800','900'] as $shade) {
            if (!empty($primary[$shade])) {
                $vars["--ui-primary-{$shade}"] = $primary[$shade];
            }
        }

        // ── Accent / Indigo ──────────────────────────────────────────────
        if (!empty($accent['base']))  $vars['--ui-indigo']       = $accent['base'];
        if (!empty($accent['hover'])) $vars['--ui-indigo-hover']  = $accent['hover'];
        if (!empty($accent['light'])) $vars['--ui-indigo-light']  = $accent['light'];
        if (!empty($accent['ring']))  $vars['--ui-indigo-ring']   = $accent['ring'];

        // Derive interactive state colors from accent hex (rgba values)
        if (!empty($accent['base']) && preg_match('/^#([0-9a-f]{6})$/i', $accent['base'], $m)) {
            [$r, $g, $b] = array_map('hexdec', str_split($m[1], 2));
            $vars['--ui-hover-overlay']   = "rgba($r,$g,$b,0.04)";
            $vars['--ui-hover-bg']        = "rgba($r,$g,$b,0.06)";
            $vars['--ui-active-overlay']  = "rgba($r,$g,$b,0.08)";
            $vars['--ui-active-bg']       = "rgba($r,$g,$b,0.10)";
            $vars['--ui-selected-bg']     = "rgba($r,$g,$b,0.08)";
            if (empty($accent['ring'])) {
                $vars['--ui-indigo-ring'] = "rgb($r $g $b / 0.25)";
            }
            $vars['--ui-focus-ring']      = "0 0 0 3px rgb($r $g $b / 0.25)";
        }

        // ── Secondary / Purple ───────────────────────────────────────────
        if (!empty($secondary['base']))  $vars['--ui-purple']       = $secondary['base'];
        if (!empty($secondary['hover'])) $vars['--ui-purple-hover']  = $secondary['hover'];
        if (!empty($secondary['light'])) $vars['--ui-purple-light']  = $secondary['light'];

        // ── Status: Success ──────────────────────────────────────────────
        if (!empty($success['base']))   $vars['--ui-success']        = $success['base'];
        if (!empty($success['hover']))  $vars['--ui-success-hover']  = $success['hover'];
        if (!empty($success['bg']))     $vars['--ui-success-bg']     = $success['bg'];
        if (!empty($success['text']))   $vars['--ui-success-text']   = $success['text'];
        if (!empty($success['border'])) $vars['--ui-success-border'] = $success['border'];
        if (!empty($success['light']))  $vars['--ui-success-light']  = $success['light'];

        // ── Status: Warning ──────────────────────────────────────────────
        if (!empty($warning['base']))   $vars['--ui-warning']        = $warning['base'];
        if (!empty($warning['hover']))  $vars['--ui-warning-hover']  = $warning['hover'];
        if (!empty($warning['bg']))     $vars['--ui-warning-bg']     = $warning['bg'];
        if (!empty($warning['text']))   $vars['--ui-warning-text']   = $warning['text'];
        if (!empty($warning['border'])) $vars['--ui-warning-border'] = $warning['border'];
        if (!empty($warning['light']))  $vars['--ui-warning-light']  = $warning['light'];

        // ── Status: Danger ───────────────────────────────────────────────
        if (!empty($danger['base']))    $vars['--ui-danger']         = $danger['base'];
        if (!empty($danger['hover']))   $vars['--ui-danger-hover']   = $danger['hover'];
        if (!empty($danger['bg']))      $vars['--ui-danger-bg']      = $danger['bg'];
        if (!empty($danger['text']))    $vars['--ui-danger-text']    = $danger['text'];
        if (!empty($danger['border']))  $vars['--ui-danger-border']  = $danger['border'];
        if (!empty($danger['light']))   $vars['--ui-danger-light']   = $danger['light'];

        // ── Status: Info ─────────────────────────────────────────────────
        if (!empty($info['base']))      $vars['--ui-info']           = $info['base'];
        if (!empty($info['hover']))     $vars['--ui-info-hover']     = $info['hover'];
        if (!empty($info['bg']))        $vars['--ui-info-bg']        = $info['bg'];
        if (!empty($info['text']))      $vars['--ui-info-text']      = $info['text'];
        if (!empty($info['border']))    $vars['--ui-info-border']    = $info['border'];
        if (!empty($info['light']))     $vars['--ui-info-light']     = $info['light'];

        // ── Surfaces ─────────────────────────────────────────────────────
        if (!empty($s['bg']))           $vars['--ui-bg']             = $s['bg'];
        if (!empty($s['surface']))      $vars['--ui-surface']        = $s['surface'];
        if (!empty($s['surface_soft'])) $vars['--ui-surface-soft']   = $s['surface_soft'];
        if (!empty($s['surface_muted'])) $vars['--ui-surface-muted'] = $s['surface_muted'];

        // ── Sidebar ──────────────────────────────────────────────────────
        if (!empty($sb['bg']))          $vars['--ui-sidebar-bg']     = $sb['bg'];
        if (!empty($sb['bg']))          $vars['--platform-sidebar-bg-top'] = $sb['bg'];
        if (!empty($sb['bg_bottom']))   $vars['--platform-sidebar-bg-bottom'] = $sb['bg_bottom'];
        if (!empty($sb['text']))        $vars['--ui-sidebar-text']   = $sb['text'];
        if (!empty($sb['text']))        $vars['--platform-sidebar-text'] = $sb['text'];
        if (!empty($sb['muted']))       $vars['--platform-sidebar-muted'] = $sb['muted'];
        if (!empty($sb['accent']))      $vars['--ui-sidebar-item-active'] = $sb['accent'];
        if (!empty($sb['accent']))      $vars['--platform-sidebar-accent'] = $sb['accent'];
        if (!empty($sb['border']))      $vars['--ui-sidebar-border'] = $sb['border'];
        if (!empty($sb['border']))      $vars['--platform-sidebar-border'] = $sb['border'];

        // ── Typography ───────────────────────────────────────────────────
        if (!empty($t['text_primary']))   $vars['--ui-text-primary']   = $t['text_primary'];
        if (!empty($t['text_secondary'])) $vars['--ui-text-secondary'] = $t['text_secondary'];
        if (!empty($t['text_muted']))     $vars['--ui-text-muted']     = $t['text_muted'];
        if (!empty($t['link']))           $vars['--ui-link-color']     = $t['link'];
        if (!empty($t['link_hover']))     $vars['--ui-link-hover']     = $t['link_hover'];

        // ── Borders ──────────────────────────────────────────────────────
        if (!empty($b['border']))  $vars['--ui-border']        = $b['border'];
        if (!empty($b['divider'])) $vars['--ui-border-soft']   = $b['divider'];
        if (!empty($b['strong']))  $vars['--ui-border-strong'] = $b['strong'];

        // ── Header ───────────────────────────────────────────────────────
        if (!empty($h['bg']))          $vars['--ds-menu-bg']          = $h['bg'];
        if (!empty($h['text']))        $vars['--ds-menu-text']        = $h['text'];
        if (!empty($h['link_active'])) $vars['--ds-menu-link-active'] = $h['link_active'];

        // ── Backward compat: --ds-* ─────────────────────────────────────
        if (!empty($accent['base'])) {
            $vars['--ds-primary']              = $accent['base'];
            $vars['--ds-link-color']           = $accent['base'];
            $vars['--ds-button-primary-bg']    = $accent['base'];
        }
        if (!empty($secondary['base'])) {
            $vars['--ds-secondary'] = $secondary['base'];
        }
        if (!empty($accent['hover'])) {
            $vars['--ds-link-hover']        = $accent['hover'];
        }
        if (!empty($t['text_primary'])) {
            $vars['--ds-title-color']       = $t['text_primary'];
        }
        $vars['--ds-button-primary-text'] = '#ffffff';

        // ── Interactive state colors derived from accent ─────────────────
        if (!empty($accent['base'])) {
            $vars['--ui-focus-ring-color'] = $accent['base'];
            $vars['--ui-selected-border']  = $accent['base'];
        }

        return $vars;
    }

    /**
     * Render CSS :root block string for inline injection.
     */
    public function renderCssBlock(): string
    {
        $vars = $this->toCssVars();
        $lines = [];
        foreach ($vars as $key => $value) {
            $lines[] = "  {$key}: {$value};";
        }
        return implode("\n", $lines);
    }
}
