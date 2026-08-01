<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 19 — Part 8: registers the real, fully-built crm module
 * (packages/saas-platform/modules/crm/) in the platform catalog as a
 * Paid Marketplace module, plus the Product row that sells it —
 * completing the Assignable formula from Phase 17-I (type=paid +
 * published release + active Product). Same precedent as
 * 2026_07_29_110000_seed_seo_advanced_marketplace_module.php.
 *
 * Deliberately a DIFFERENT slug (`crm-suite`) from the pre-existing
 * `crm` row (id=46, price_irr=0, no Product, no ModuleRelease,
 * created 2026-07-15) — that row is an unbuilt roadmap placeholder from
 * an earlier batch seed, predating this real module. Renaming or
 * removing that row is a Core data change outside this phase's "no
 * unnecessary Core changes" mandate — left untouched, matching the exact
 * reasoning documented in the SEO Advanced seed migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $moduleId = DB::table('modules')->insertGetId([
            'slug' => 'crm-suite',
            'title' => 'مدیریت ارتباط با مشتری (CRM)',
            'description' => 'مدیریت کامل مشتریان، مخاطبین، سرنخ‌ها، معاملات و خط لوله فروش — شرکت، مخاطب، سرنخ، معامله، فعالیت، وظیفه، یادداشت، برچسب، پیگیری کلمات کلیدی و گزارش پیش‌بینی فروش. کاملاً مستقل، Multi-Tenant، بدون وابستگی به سایر ماژول‌ها.',
            'short_description' => 'مدیریت کامل مشتریان، سرنخ‌ها و خط لوله فروش.',
            'is_active' => true,
            'type' => 'paid',
            'version' => '1.0.0',
            'free_assignable' => false,
            'license_required' => false,
            'marketplace_visible' => true,
            'price_irr' => 990000,
            'category' => 'crm',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('products')->insert([
            'sku' => 'module-crm-suite',
            'name_fa' => 'ماژول مدیریت ارتباط با مشتری',
            'name_en' => 'CRM Suite Module',
            'type' => 'module_addon',
            'price_irr' => 990000,
            'is_one_time' => true,
            'module_id' => $moduleId,
            'is_active' => true,
            'description' => 'دسترسی کامل به ماژول CRM برای این سایت.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('slug', 'crm-suite')->value('id');
        if ($moduleId) {
            DB::table('products')->where('module_id', $moduleId)->where('sku', 'module-crm-suite')->delete();
        }
        DB::table('modules')->where('slug', 'crm-suite')->delete();
    }
};
