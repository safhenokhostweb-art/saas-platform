<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            if (!Schema::hasColumn('form_fields', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_required');
            }
            if (!Schema::hasColumn('form_fields', 'placeholder')) {
                $table->string('placeholder')->nullable()->after('label');
            }
            if (!Schema::hasColumn('form_fields', 'help_text')) {
                $table->string('help_text')->nullable()->after('placeholder');
            }
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            if (Schema::hasColumn('form_fields', 'is_active')) $table->dropColumn('is_active');
            if (Schema::hasColumn('form_fields', 'placeholder')) $table->dropColumn('placeholder');
            if (Schema::hasColumn('form_fields', 'help_text')) $table->dropColumn('help_text');
        });
    }
};
