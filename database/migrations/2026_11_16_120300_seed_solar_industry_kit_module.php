<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-087: registers the licence key `solar_industry_kit`, the entitlement
 * the theme-runtime access policy requires for the solar-industry asset
 * family (the solar header, footer and page templates).
 *
 * Like ADR-085's `solar_calculator` it is a key, not a product: no price,
 * `marketplace_visible` false, `license_required` true, so it reaches a
 * site only when the platform owner assigns it to that site's licence.
 * The family also requires the site's business vertical `solar_energy`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('modules')) {
            return;
        }

        $row = [
            'title' => 'کیت قالب صنعت برق و انرژی خورشیدی',
            'description' => 'هدر، فوتر و قالب‌های صفحهٔ اختصاصی صنعت برق و انرژی خورشیدی در ویرایشگر Canvas. کلید لایسنس اختصاصی؛ فقط با اختصاص در لایسنس سایتی با حوزهٔ کسب‌وکار «صنعت برق و انرژی خورشیدی» فعال می‌شود.',
            'is_active' => true,
            'updated_at' => now(),
        ];
        $optional = [
            'short_description' => 'قالب‌های آماده اختصاصی صنعت برق و انرژی خورشیدی.',
            'type' => 'paid',
            'version' => '1.0.0',
            'free_assignable' => false,
            'license_required' => true,
            'marketplace_visible' => false,
            'price_irr' => null,
        ];
        foreach ($optional as $column => $value) {
            if (Schema::hasColumn('modules', $column)) {
                $row[$column] = $value;
            }
        }

        if (DB::table('modules')->where('slug', 'solar_industry_kit')->exists()) {
            DB::table('modules')->where('slug', 'solar_industry_kit')->update($row);
            return;
        }

        DB::table('modules')->insert($row + ['slug' => 'solar_industry_kit', 'created_at' => now()]);
    }

    public function down(): void
    {
        // Kept: a site licence may already reference the key.
    }
};
