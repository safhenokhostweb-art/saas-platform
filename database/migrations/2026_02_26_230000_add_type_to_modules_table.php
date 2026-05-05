<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن ستون type به modules: core, paid, internal.
 * - core: شامل قالب پایه، قابل اختصاص به سایت
 * - paid: افزودنی، خریداری‌شده
 * - internal: فقط پلتفرم، هرگز به سایت مشتری ارسال نمی‌شود
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('type', 32)->default('core')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
