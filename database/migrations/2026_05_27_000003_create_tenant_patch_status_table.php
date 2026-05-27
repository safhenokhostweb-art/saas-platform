<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_patch_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->comment('tenant site_id');
            $table->string('patch_id', 50);
            $table->string('status', 30)->default('pending')->comment('pending|queued|applying|applied|failed|rolled_back|skipped');
            $table->string('channel', 30)->nullable();
            $table->string('current_security_level', 50)->nullable()->comment('e.g. SPL-18');
            $table->string('applied_version', 30)->nullable();
            $table->boolean('license_bypassed')->default(false)->comment('true when delivered despite expired license');
            $table->json('compatibility_check')->nullable();
            $table->json('rollback_snapshot')->nullable()->comment('safe state before patch applied');
            $table->text('failure_reason')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'patch_id']);
            $table->index(['site_id', 'status']);
            $table->index(['patch_id', 'status']);
            $table->index('applied_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_patch_status');
    }
};
