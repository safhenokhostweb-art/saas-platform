<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_locks', function (Blueprint $table) {
            $table->id();
            $table->string('environment', 50)->default('production');
            $table->string('deployment_uuid', 36)->unique();
            $table->string('owner', 255)->comment('hostname or process ID that holds the lock');
            $table->string('status', 30)->default('locked')->comment('locked, released, expired, force_released');
            $table->string('phase', 50)->nullable()->comment('current deployment phase');
            $table->json('metadata')->nullable();
            $table->timestamp('acquired_at');
            $table->timestamp('expires_at')->nullable()->comment('null = never expires');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['environment', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_locks');
    }
};
