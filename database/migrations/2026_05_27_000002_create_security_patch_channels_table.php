<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_patch_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('stable|security|emergency|beta|enterprise|legacy-support');
            $table->string('trust_level', 30)->default('verified')->comment('core|trusted|sandboxed|unverified');
            $table->boolean('requires_signature')->default(true);
            $table->boolean('allows_rollback')->default(true);
            $table->boolean('license_gated')->default(false)->comment('security channels are NEVER license-gated');
            $table->integer('rollout_percentage')->default(100)->comment('staged rollout percentage');
            $table->integer('priority')->default(50)->comment('lower = higher priority');
            $table->json('eligible_tenant_tiers')->nullable()->comment('null = all tiers');
            $table->json('configuration')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'priority']);
            $table->index('trust_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_patch_channels');
    }
};
