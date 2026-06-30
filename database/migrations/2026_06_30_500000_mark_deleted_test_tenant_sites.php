<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // These test tenant domains were manually cleaned from customer_installations
        // but their sites rows were never updated, so lifecycle_status remained NULL.
        // Mark them explicitly as deleted so they are excluded from all reports.
        DB::table('sites')
            ->whereIn('domain', ['tenent.safhenokhost.ir', 'tenent1.safhenokhost.ir'])
            ->update([
                'lifecycle_status' => 'deleted',
                'is_active'        => false,
            ]);
    }

    public function down(): void
    {
        DB::table('sites')
            ->whereIn('domain', ['tenent.safhenokhost.ir', 'tenent1.safhenokhost.ir'])
            ->update([
                'lifecycle_status' => null,
                'is_active'        => true,
            ]);
    }
};
