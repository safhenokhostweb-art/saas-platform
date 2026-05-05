<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * مقداردهی نوع ماژول‌های موجود.
 * blog, shop, forms, advertisements = core
 * module_labels = internal (فقط پلتفرم)
 * بقیه = core
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasColumn('modules', 'type')) {
            return;
        }

        $internal = ['module_labels'];
        $core = ['blog', 'shop', 'forms', 'advertisements', 'advanced_search', 'currency_reform', 'bank_gateway',
            'coupon', 'reviews', 'referral', 'subscription', 'whatsapp', 'sms', 'courses', 'invoice',
            'business_card', 'social_links', 'notifications'];

        foreach ($internal as $slug) {
            DB::table('modules')->where('slug', $slug)->update(['type' => 'internal']);
        }

        foreach ($core as $slug) {
            DB::table('modules')->where('slug', $slug)->update(['type' => 'core']);
        }

        DB::table('modules')->whereNotIn('type', ['internal', 'core'])->update(['type' => 'core']);
    }

    public function down(): void
    {
        // no-op
    }
};
