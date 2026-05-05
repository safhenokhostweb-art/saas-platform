<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add distribution mode fields: free_assignable, license_required.
 * Modes: FREE ASSIGNABLE | PAID (marketplace) | LICENSE-ONLY | DISABLED.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->boolean('free_assignable')->default(false)->after('is_active');
            $table->boolean('license_required')->default(false)->after('free_assignable');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['free_assignable', 'license_required']);
        });
    }
};
