<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_resource_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            // Storage
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->unsignedBigInteger('db_bytes')->default(0);
            $table->unsignedBigInteger('backup_bytes')->default(0);
            $table->unsignedBigInteger('archive_bytes')->default(0);
            $table->unsignedInteger('file_count')->default(0);

            // Traffic (rolling monthly)
            $table->unsignedBigInteger('bandwidth_bytes_monthly')->default(0);
            $table->unsignedInteger('request_count_monthly')->default(0);

            // Capacity weights (from plan, 1–100)
            $table->unsignedTinyInteger('cpu_weight')->default(10);
            $table->unsignedTinyInteger('ram_weight')->default(10);

            // Alert state (prevent repeated notifications)
            $table->boolean('alerted_80')->default(false);
            $table->boolean('alerted_95')->default(false);
            $table->timestamp('calculated_at')->nullable();

            $table->timestamps();

            $table->unique('site_id');
            $table->index('storage_bytes');
            $table->index('calculated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_resource_usage');
    }
};
