<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNIVERSAL_DEPLOYMENT_ENGINE — DeploymentTarget table.
 *
 * This table replaces the conceptual "server" with a "deployment target"
 * that can represent any hosting type: VPS, shared hosting, Docker, etc.
 * The connection_driver field determines which deployment driver is loaded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_targets', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('name');
            $table->enum('type', [
                'vps',
                'shared_hosting',
                'cpanel',
                'directadmin',
                'docker',
                'managed_cloud',
                'other',
            ])->default('vps');

            // Driver selection — determines which deployment driver is loaded
            $table->enum('connection_driver', [
                'ssh',
                'cpanel',
                'directadmin',
                'sftp',
                'docker',
                'fake',
            ])->default('ssh');

            // Authentication method — valid values depend on connection_driver
            $table->enum('auth_method', [
                'ssh_key',
                'ssh_password',
                'cpanel_api_token',
                'cpanel_password',
                'directadmin_api_token',
                'directadmin_password',
                'sftp_password',
            ])->default('ssh_key');

            // Connection details
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(22);
            $table->string('username');

            // Target paths and domain
            $table->string('path')->nullable();     // web root (e.g. /var/www/customer-site)
            $table->string('domain')->nullable();   // primary domain on this target

            // Lifecycle
            $table->enum('status', ['active', 'inactive', 'pending', 'error'])->default('active');

            // Encrypted credentials (CredentialVault AES-256-GCM)
            $table->text('private_key_encrypted')->nullable();
            $table->string('password_encrypted')->nullable();
            $table->text('api_token_encrypted')->nullable();

            // SSH host key pinning
            $table->string('key_fingerprint')->nullable();

            // Capability override — JSON; null = auto-derived from connection_driver
            $table->json('capabilities')->nullable();

            // Health tracking
            $table->timestamp('last_tested_at')->nullable();
            $table->enum('last_test_result', ['success', 'failure', 'never'])->default('never');

            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('connection_driver');
            $table->index('type');
            $table->index('status');
            $table->index('last_test_result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_targets');
    }
};
