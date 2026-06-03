<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // کاربر نهایی 09906210511 به اشتباه is_admin=true شده بود — اصلاح
        DB::table('users')
            ->where('mobile', '09906210511')
            ->update(['is_admin' => false, 'is_site_admin' => false]);

        // ادمین سایت مشتری 09927530317 باید is_site_admin=true باشد
        // site_id از جدول sites بر اساس domain سایت مشتری پیدا می‌شود
        $siteId = DB::table('sites')
            ->whereIn('domain', ['customer-site.test', 'customer-site.local'])
            ->orWhere('domain', 'like', '%customer-site%')
            ->value('id');

        DB::table('users')
            ->where('mobile', '09927530317')
            ->update(array_filter([
                'is_admin'      => false,
                'is_site_admin' => true,
                'site_id'       => $siteId ?: null,
            ], fn($v) => $v !== null || $v === false));
    }

    public function down(): void
    {
        // برگشت به حالت قبل (فقط برای محیط dev)
        DB::table('users')->where('mobile', '09906210511')->update(['is_admin' => true]);
        DB::table('users')->where('mobile', '09927530317')->update(['is_site_admin' => false, 'site_id' => null]);
    }
};
