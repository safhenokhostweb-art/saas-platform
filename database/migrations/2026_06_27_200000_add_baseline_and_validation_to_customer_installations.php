<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->timestamp('initial_baseline_at')->nullable()->after('completed_at');
            $table->string('initial_baseline_arvan_key', 500)->nullable()->after('initial_baseline_at');
            $table->string('initial_baseline_sha256', 64)->nullable()->after('initial_baseline_arvan_key');
            $table->json('post_install_validation')->nullable()->after('initial_baseline_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->dropColumn([
                'initial_baseline_at',
                'initial_baseline_arvan_key',
                'initial_baseline_sha256',
                'post_install_validation',
            ]);
        });
    }
};
