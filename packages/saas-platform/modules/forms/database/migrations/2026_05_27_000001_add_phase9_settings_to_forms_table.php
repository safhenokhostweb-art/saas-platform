<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedSmallInteger('step_count')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['settings', 'template_id', 'step_count']);
        });
    }
};
