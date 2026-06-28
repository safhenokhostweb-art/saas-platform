<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssh_keys', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->enum('key_type', ['ed25519', 'rsa4096'])->default('ed25519');
            $table->text('public_key');
            $table->text('private_key_encrypted');
            $table->text('passphrase_encrypted')->nullable();
            $table->string('fingerprint', 120);
            $table->unsignedBigInteger('deployment_target_id')->nullable();
            $table->enum('status', ['pending', 'active', 'rotated', 'revoked'])->default('pending');
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('deployment_target_id');
            $table->index('fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_keys');
    }
};
