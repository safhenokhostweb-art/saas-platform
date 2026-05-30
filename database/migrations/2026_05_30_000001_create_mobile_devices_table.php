<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id');
            $table->string('platform', 16);              // ios | android
            $table->string('os_version', 32);
            $table->string('app_version', 32);
            $table->string('device_model', 128)->nullable();
            $table->string('push_token', 512)->nullable();
            $table->string('push_platform', 16)->nullable(); // fcm | apns
            $table->string('trust_level', 20)->default('provisional');
            $table->json('device_info')->nullable();
            $table->json('capabilities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tenant_id']);
            $table->index(['tenant_id', 'is_active']);
            $table->index('trust_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_devices');
    }
};
