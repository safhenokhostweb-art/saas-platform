<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infra_platform_health_logs', function (Blueprint $table) {
            $table->id();
            $table->string('database_status', 20)->default('unknown');
            $table->string('redis_status', 20)->default('unknown');
            $table->string('queue_status', 20)->default('unknown');
            $table->unsignedInteger('queue_size')->nullable();
            $table->unsignedInteger('failed_jobs')->nullable();
            $table->string('reverb_status', 20)->default('unknown');
            $table->string('sms_provider_status', 20)->default('unknown');
            $table->string('sms_provider_name', 60)->nullable();
            $table->string('ai_provider_status', 20)->default('unknown');
            $table->string('ai_provider_name', 60)->nullable();
            $table->string('storage_status', 20)->default('unknown');
            $table->tinyInteger('disk_used_percent')->unsigned()->nullable();
            $table->decimal('disk_free_gb', 10, 2)->nullable();
            $table->tinyInteger('memory_used_percent')->unsigned()->nullable();
            $table->decimal('cpu_load_1m', 5, 2)->nullable();
            $table->string('overall_status', 20)->default('unknown');
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index('checked_at');
            $table->index('overall_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infra_platform_health_logs');
    }
};
