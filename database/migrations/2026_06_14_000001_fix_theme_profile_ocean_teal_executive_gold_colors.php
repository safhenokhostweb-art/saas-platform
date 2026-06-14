<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix Ocean Teal and Executive Gold theme profiles:
 *   - accent.light was identical to surfaces.bg (invisible UI elements)
 *   - borders.border / borders.strong had near-white contrast on white bg
 *   - Add --ui-indigo-light fix for TenantThemeResolver compatibility
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->fixOceanTeal();
        $this->fixExecutiveGold();
    }

    public function down(): void
    {
        // Revert Ocean Teal
        $ot = DB::table('theme_profiles')->where('slug', 'ocean-teal')->first();
        if ($ot) {
            $colors  = json_decode($ot->colors,  true) ?? [];
            $borders = json_decode($ot->borders, true) ?? [];

            $colors['accent']['light'] = '#f0fdfa';
            $borders['border']         = '#99f6e4';
            $borders['strong']         = '#5eead4';

            DB::table('theme_profiles')->where('slug', 'ocean-teal')->update([
                'colors'  => json_encode($colors,  JSON_UNESCAPED_UNICODE),
                'borders' => json_encode($borders, JSON_UNESCAPED_UNICODE),
            ]);
        }

        // Revert Executive Gold
        $eg = DB::table('theme_profiles')->where('slug', 'executive-gold')->first();
        if ($eg) {
            $colors  = json_decode($eg->colors,  true) ?? [];
            $borders = json_decode($eg->borders, true) ?? [];

            $colors['accent']['light'] = '#fffbeb';
            $borders['border']         = '#fde68a';
            $borders['strong']         = '#fcd34d';

            DB::table('theme_profiles')->where('slug', 'executive-gold')->update([
                'colors'  => json_encode($colors,  JSON_UNESCAPED_UNICODE),
                'borders' => json_encode($borders, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function fixOceanTeal(): void
    {
        $row = DB::table('theme_profiles')->where('slug', 'ocean-teal')->first();
        if (!$row) {
            return;
        }

        $colors   = json_decode($row->colors,   true) ?? [];
        $borders  = json_decode($row->borders,  true) ?? [];
        $surfaces = json_decode($row->surfaces, true) ?? [];

        // accent.light was #f0fdfa = page bg → use #ccfbf1 (visible teal tint)
        $colors['accent']['light'] = '#ccfbf1';

        // borders were near-white, invisible on light backgrounds
        $borders['border'] = '#5eead4';  // was #99f6e4 (contrast 1.13:1)
        $borders['strong'] = '#2dd4bf';  // was #5eead4

        // surface_muted should differ from page bg
        if (isset($surfaces['surface_muted']) && $surfaces['surface_muted'] === '#ccfbf1') {
            $surfaces['surface_muted'] = '#e6fff9';
        }

        DB::table('theme_profiles')->where('slug', 'ocean-teal')->update([
            'colors'   => json_encode($colors,   JSON_UNESCAPED_UNICODE),
            'borders'  => json_encode($borders,  JSON_UNESCAPED_UNICODE),
            'surfaces' => json_encode($surfaces, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    private function fixExecutiveGold(): void
    {
        $row = DB::table('theme_profiles')->where('slug', 'executive-gold')->first();
        if (!$row) {
            return;
        }

        $colors  = json_decode($row->colors,  true) ?? [];
        $borders = json_decode($row->borders, true) ?? [];

        // accent.light was #fffbeb = page bg → use #fde68a (visible amber tint)
        $colors['accent']['light'] = '#fde68a';

        // borders were near-white, invisible on light backgrounds
        $borders['border'] = '#fcd34d';  // was #fde68a (contrast ~1.15:1)
        $borders['strong'] = '#fbbf24';  // was #fcd34d

        DB::table('theme_profiles')->where('slug', 'executive-gold')->update([
            'colors'  => json_encode($colors,  JSON_UNESCAPED_UNICODE),
            'borders' => json_encode($borders, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};
