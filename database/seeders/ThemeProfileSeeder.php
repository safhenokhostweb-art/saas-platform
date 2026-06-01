<?php

namespace Database\Seeders;

use App\Models\ThemeProfile;
use App\Services\ThemeEngine\ThemePresetRegistry;
use Illuminate\Database\Seeder;

/**
 * Seeds the 8 enterprise theme presets.
 * Safe to re-run: uses updateOrCreate on slug.
 * The Enterprise Blue preset is activated as the platform default.
 */
class ThemeProfileSeeder extends Seeder
{
    public function run(): void
    {
        $presets = ThemePresetRegistry::all();

        foreach ($presets as $preset) {
            ThemeProfile::updateOrCreate(
                ['slug' => $preset['slug']],
                array_merge($preset, ['is_active' => false])
            );
        }

        // Activate Enterprise Blue as the platform default
        ThemeProfile::where('is_active', true)->update(['is_active' => false]);
        ThemeProfile::where('preset_key', 'enterprise-blue')
            ->update(['is_active' => true, 'is_default' => true]);

        $this->command?->info('✓ 8 enterprise theme presets seeded. Enterprise Blue is active.');
    }
}
