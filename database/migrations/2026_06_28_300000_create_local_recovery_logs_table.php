<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_recovery_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['platform', 'customer'])->index();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->string('installation_uuid', 36)->nullable()->index();
            $table->string('baseline_label')->nullable();
            $table->string('local_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->string('manifest_checksum', 64)->nullable();
            $table->enum('status', ['pending', 'verified', 'failed'])->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_recovery_logs');
    }
};
