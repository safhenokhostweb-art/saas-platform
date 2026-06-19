<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_resource_limits', function (Blueprint $table) {
            $table->id();
            // null site_id = plan-level default; set site_id for per-site override
            $table->unsignedBigInteger('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->string('plan_name', 50)->nullable(); // e.g. 'basic', 'pro', 'enterprise'

            // Storage limits (bytes)
            $table->unsignedBigInteger('storage_limit_bytes')->default(1073741824);   // 1 GB
            $table->unsignedBigInteger('bandwidth_limit_bytes')->default(10737418240); // 10 GB/month

            // Retention
            $table->unsignedSmallInteger('archive_retention_days')->default(365);
            $table->unsignedSmallInteger('backup_retention_days')->default(30);

            // Capacity weights
            $table->unsignedTinyInteger('cpu_weight')->default(10);
            $table->unsignedTinyInteger('ram_weight')->default(10);

            // Overage fees (Rials, 0 = unlimited / not billed)
            $table->unsignedBigInteger('storage_overage_fee_per_gb')->default(0);
            $table->unsignedBigInteger('bandwidth_overage_fee_per_gb')->default(0);
            $table->unsignedBigInteger('archive_retention_fee')->default(0);
            $table->unsignedBigInteger('restore_fee')->default(0);

            $table->timestamps();

            // One row per site (override) or per plan name (default)
            $table->unique('site_id');
            $table->index('plan_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_resource_limits');
    }
};
