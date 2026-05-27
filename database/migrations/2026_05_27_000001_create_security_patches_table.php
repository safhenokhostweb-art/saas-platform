<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_patches', function (Blueprint $table) {
            $table->id();
            $table->string('patch_id', 50)->unique()->comment('e.g. SP-2026-05-27');
            $table->string('channel', 30)->default('security')->comment('stable|security|emergency|beta|enterprise|legacy-support');
            $table->string('severity', 20)->default('medium')->comment('critical|high|medium|low');
            $table->string('platform_version', 30)->nullable()->comment('target platform version constraint');
            $table->string('security_patch_level', 50)->nullable()->comment('e.g. SPL-18');
            $table->json('affected_modules')->nullable()->comment('module IDs impacted by this patch');
            $table->json('compatibility_matrix')->nullable()->comment('runtime compatibility requirements');
            $table->string('publisher_id', 100)->nullable();
            $table->string('signature', 512)->nullable()->comment('SHA-256 signed manifest hash');
            $table->boolean('license_independent')->default(true)->comment('bypass commercial licensing for delivery');
            $table->boolean('mandatory')->default(false)->comment('forced delivery regardless of tenant preference');
            $table->string('status', 30)->default('pending')->comment('pending|staged|deploying|deployed|rolled_back|failed');
            $table->text('description')->nullable();
            $table->json('mitigation_steps')->nullable();
            $table->json('rollback_plan')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('deadline_at')->nullable()->comment('mandatory patches have a delivery deadline');
            $table->timestamps();

            $table->index(['channel', 'status']);
            $table->index(['severity', 'mandatory']);
            $table->index('available_at');
            $table->index('deadline_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_patches');
    }
};
