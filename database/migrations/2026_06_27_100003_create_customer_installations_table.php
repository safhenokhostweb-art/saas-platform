<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_installations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable()->comment('Set on completion');
            $table->unsignedBigInteger('remote_server_id');
            $table->unsignedBigInteger('installer_user_id');
            $table->enum('install_mode', [
                'MODE_FRESH', 'MODE_RESTORE', 'MODE_CLONE', 'MODE_MOVE', 'MODE_REINSTALL',
            ])->default('MODE_FRESH');
            $table->enum('status', [
                'pending', 'running', 'complete', 'failed', 'rolling_back', 'rolled_back', 'rollback_failed',
            ])->default('pending');
            $table->string('customer_company');
            $table->string('customer_email');
            $table->string('customer_domain');
            $table->string('package_version', 20)->nullable();
            $table->string('package_sha256', 64)->nullable();
            $table->string('current_stage', 60)->nullable();
            $table->json('completed_stages')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('request_payload')->nullable()->comment('Encrypted CustomerInstallRequest');
            $table->unsignedBigInteger('source_installation_id')->nullable()->comment('For clone/move modes');
            $table->timestamps();

            $table->index('status');
            $table->index('site_id');
            $table->index('remote_server_id');
            $table->index('installer_user_id');
            $table->index('install_mode');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_installations');
    }
};
