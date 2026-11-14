<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the license key `solar_calculator` (customer-site module
 * app/Modules/SolarCalculator) in the platform catalog.
 *
 * It is a site-specific module, not a product: `marketplace_visible`
 * false keeps it out of every tenant's module store, there is no price
 * and no Product row, and `license_required` means it reaches a site only
 * when the platform owner assigns it to that site's license. A site
 * without the key gets 403 on every calculator route and never sees the
 * canvas widget.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('modules')) {
            return;
        }

        $row = [
            'title' => 'ماشین‌حساب خورشیدی',
            'description' => 'ماشین‌حساب ظرفیت و هزینهٔ سیستم خورشیدی با موتور فرمول قابل‌ویرایش، نرخ ارز، صدور پیش‌فاکتور و ثبت سرنخ در CRM. ماژول اختصاصی سایت؛ فقط با اختصاص در لایسنس فعال می‌شود.',
            'is_active' => true,
            'updated_at' => now(),
        ];
        $optional = [
            'short_description' => 'ماشین‌حساب خورشیدی اختصاصی با پیش‌فاکتور و ثبت سرنخ.',
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

        if (DB::table('modules')->where('slug', 'solar_calculator')->exists()) {
            DB::table('modules')->where('slug', 'solar_calculator')->update($row);
            return;
        }

        DB::table('modules')->insert($row + ['slug' => 'solar_calculator', 'created_at' => now()]);
    }

    public function down(): void
    {
        // Kept: a site license may already reference the key.
    }
};
