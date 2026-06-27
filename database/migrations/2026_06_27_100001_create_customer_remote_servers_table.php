<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_remote_servers', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->enum('type', ['ssh', 'docker', 'cpanel', 'directadmin', 'fake'])->default('ssh');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(22);
            $table->string('username');
            $table->enum('auth_method', ['key', 'password'])->default('key');
            $table->text('private_key_encrypted')->nullable();
            $table->string('password_encrypted')->nullable();
            $table->string('key_fingerprint')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->enum('last_test_result', ['success', 'failure', 'never'])->default('never');
            $table->timestamps();

            $table->index('type');
            $table->index('last_test_result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_remote_servers');
    }
};
