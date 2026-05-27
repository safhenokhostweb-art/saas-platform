<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->json('conditions')->nullable()->after('help_text');
            $table->string('section_id')->nullable()->after('conditions');
            $table->unsignedTinyInteger('column_span')->default(12)->after('section_id');
            $table->string('default_value')->nullable()->after('column_span');
            $table->string('css_class')->nullable()->after('default_value');
            $table->json('validation_rules')->nullable()->after('css_class');
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropColumn([
                'conditions', 'section_id', 'column_span',
                'default_value', 'css_class', 'validation_rules',
            ]);
        });
    }
};
