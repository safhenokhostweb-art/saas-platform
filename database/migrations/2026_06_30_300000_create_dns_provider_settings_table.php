<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->default('arvan'); // arvan | custom
            $table->string('root_domain', 253);               // e.g. safhenokhost.ir
            $table->text('api_token_encrypted')->nullable();  // AES-256-GCM via CredentialVault
            $table->string('api_base_url', 500)->nullable();  // null = use provider default
            $table->boolean('is_active')->default(false);
            $table->boolean('cloud_proxy')->default(false);   // Arvan CDN proxy on/off for new records
            $table->unsignedSmallInteger('default_ttl')->default(120); // seconds
            $table->string('health_status', 20)->default('unchecked'); // ok | error | unchecked
            $table->text('health_message')->nullable();
            $table->timestamp('health_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_provider_settings');
    }
};
