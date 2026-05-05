<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن price_irr به modules برای ساختار قیمت‌گذاری.
 * null = رایگان (core) یا قیمت از Product.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->unsignedBigInteger('price_irr')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('price_irr');
        });
    }
};
