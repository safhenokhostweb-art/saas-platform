<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_maintenance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->index()->comment('null = platform-wide');
            $table->string('scope', 30)->default('full')->comment('full, module, partial');
            $table->string('module', 100)->nullable()->comment('affected module slug if scope=module');
            $table->string('message', 500)->nullable()->comment('user-facing maintenance message');
            $table->boolean('allow_admin_bypass')->default(true);
            $table->boolean('allow_health_bypass')->default(true);
            $table->boolean('allow_deployment_bypass')->default(true);
            $table->string('initiated_by', 255)->nullable()->comment('username or process');
            $table->string('deployment_uuid', 36)->nullable()->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable()->comment('null = still active');
            $table->timestamps();

            $table->index(['site_id', 'ended_at']);
            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_maintenance');
    }
};
