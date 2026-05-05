<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * برای هر رکورد site_module_purchases یک لایسنس یک‌بار مصرف فعال ایجاد می‌کند.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('module_licenses')) {
            return;
        }

        $purchases = DB::table('site_module_purchases')->get();
        foreach ($purchases as $p) {
            $exists = DB::table('module_licenses')
                ->where('site_id', $p->site_id)
                ->where('module_id', $p->module_id)
                ->where('status', 'active')
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('module_licenses')->insert([
                'site_id' => $p->site_id,
                'module_id' => $p->module_id,
                'license_type' => 'one_time',
                'purchased_at' => $p->purchased_at ?? now(),
                'expires_at' => null,
                'status' => 'active',
                'support_eligibility' => true,
                'invoice_id' => $p->invoice_id,
                'invoice_item_id' => $p->invoice_item_id,
                'module_purchase_id' => $p->id,
                'granted_by' => $p->granted_by,
                'revoked_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // optional: delete licenses that have module_purchase_id set (backfilled)
        DB::table('module_licenses')->whereNotNull('module_purchase_id')->delete();
    }
};
