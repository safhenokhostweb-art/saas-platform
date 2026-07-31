<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 18 — Part 9: registers the real, fully-built seo-advanced module
 * (packages/saas-platform/modules/seo-advanced/) in the platform catalog
 * as a Paid Marketplace module, plus the Product row that sells it —
 * completing the Assignable formula from Phase 17-I
 * (type=paid + published release + active Product).
 *
 * Deliberately a DIFFERENT slug (`seo-advanced`, hyphenated) from the
 * pre-existing `seo_advanced` (underscore) row seeded by
 * 2026_02_28_150000_seed_enterprise_modules.php — that row is an unbuilt
 * roadmap placeholder (price_irr=0, no Product, no ModuleRelease, part of
 * a batch of 30 such stubs) predating the Phase 17 Module Package
 * Contract, which requires hyphenated slugs
 * (ModuleManifestSchema::validate()). Renaming or removing that
 * pre-existing row is a Core data change outside this phase's
 * "no unnecessary Core changes" mandate — left untouched. See the Phase
 * 18 final report for this finding.
 */
return new class extends Migration
{
    public function up(): void
    {
        $moduleId = DB::table('modules')->insertGetId([
            'slug' => 'seo-advanced',
            'title' => 'سئو پیشرفته',
            'description' => 'مدیریت متا (عنوان/توضیحات/کلمات کلیدی)، Sitemap، Robots.txt، Canonical، OpenGraph، Schema.org (Organization/LocalBusiness/Website) و ارزیابی SEO — کاملاً مستقل، بدون هیچ وابستگی به سایر ماژول‌ها.',
            'short_description' => 'مدیریت کامل سئو سایت: متا، Sitemap، Schema.org و ارزیابی SEO.',
            'is_active' => true,
            'type' => 'paid',
            'version' => '1.0.0',
            'free_assignable' => false,
            'license_required' => false,
            'marketplace_visible' => true,
            'price_irr' => 490000,
            'category' => 'seo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('products')->insert([
            'sku' => 'module-seo-advanced',
            'name_fa' => 'ماژول سئو پیشرفته',
            'name_en' => 'SEO Advanced Module',
            'type' => 'module_addon',
            'price_irr' => 490000,
            'is_one_time' => true,
            'module_id' => $moduleId,
            'is_active' => true,
            'description' => 'دسترسی کامل به ماژول سئو پیشرفته برای این سایت.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('slug', 'seo-advanced')->value('id');
        if ($moduleId) {
            DB::table('products')->where('module_id', $moduleId)->where('sku', 'module-seo-advanced')->delete();
        }
        DB::table('modules')->where('slug', 'seo-advanced')->delete();
    }
};
