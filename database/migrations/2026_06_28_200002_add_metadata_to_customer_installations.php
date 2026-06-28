<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('post_install_validation');
        });
    }

    public function down(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
