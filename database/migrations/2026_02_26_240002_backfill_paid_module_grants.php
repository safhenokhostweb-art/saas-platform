<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * برای سایت‌هایی که قبلاً ماژول PAID فعال داشتند ولی رکورد site_module_purchases ندارند،
 * یک grant (اعطای دستی) ایجاد می‌کند تا رفتار فعلی حفظ شود.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('site_module_purchases')) {
            return;
        }

        $paidModuleIds = DB::table('modules')
            ->where('type', \App\Models\Module::TYPE_PAID)
            ->pluck('id');

        if ($paidModuleIds->isEmpty()) {
            return;
        }

        $existing = DB::table('site_module_purchases')
            ->select('site_id', 'module_id')
            ->get()
            ->map(fn ($r) => "{$r->site_id}:{$r->module_id}")
            ->flip();

        $toInsert = DB::table('site_modules')
            ->join('modules', 'site_modules.module_id', '=', 'modules.id')
            ->where('site_modules.is_active', true)
            ->whereIn('modules.id', $paidModuleIds)
            ->select('site_modules.site_id', 'site_modules.module_id')
            ->get()
            ->filter(fn ($r) => !$existing->has("{$r->site_id}:{$r->module_id}"));

        $now = now();
        foreach ($toInsert as $row) {
            DB::table('site_module_purchases')->insert([
                'site_id' => $row->site_id,
                'module_id' => $row->module_id,
                'invoice_id' => null,
                'invoice_item_id' => null,
                'amount_irr' => 0,
                'granted_by' => null,
                'purchased_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // rollback غیردقیق است؛ grantهای backfill از grantهای دستی قابل تشخیص نیستند.
    }
};
