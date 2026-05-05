<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ماژول‌های فعلی که قیمت ندارند و core/paid هستند → free_assignable=true تا در لیست اختصاص دستی بمانند.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('modules', 'free_assignable')) {
            return;
        }
        DB::table('modules')
            ->whereIn('type', ['core', 'paid'])
            ->where(function ($q) {
                $q->whereNull('price_irr')->orWhere('price_irr', 0);
            })
            ->update(['free_assignable' => true]);
    }

    public function down(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('modules', 'free_assignable')) {
            return;
        }
        DB::table('modules')->update(['free_assignable' => false]);
    }
};
