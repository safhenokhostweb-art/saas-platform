<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13-O9a — dedicated encrypted storage for per-installation
 * credentials (starting with GENERATE_ENVIRONMENT's admin_password),
 * closing the gap where CustomerInstallation.metadata held it as
 * plaintext JSON — readable directly from the live database and from
 * every daily platform backup (local + Arvan offsite).
 *
 * Values are encrypted with CredentialVault (INSTALLER_MASTER_KEY), the
 * same mechanism already used for CustomerRemoteServer/DnsProviderSetting/
 * SshKey credentials — never with APP_KEY.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('installation_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained('customer_installations')->cascadeOnDelete();
            $table->string('type');
            $table->text('encrypted_value');
            $table->timestamps();

            $table->index(['installation_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_credentials');
    }
};
