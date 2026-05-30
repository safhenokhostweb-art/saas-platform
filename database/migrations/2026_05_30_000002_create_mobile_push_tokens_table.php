<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_push_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id');
            $table->string('token', 512);
            $table->string('platform', 16);              // fcm | apns
            $table->boolean('is_valid')->default(true);
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('device_id');
            $table->index(['user_id', 'tenant_id']);
            $table->index(['tenant_id', 'is_valid']);

            $table->foreign('device_id')->references('id')->on('mobile_devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_push_tokens');
    }
};
