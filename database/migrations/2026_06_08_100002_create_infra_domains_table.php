<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infra_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('type')->default('tenant'); // tenant, platform, cdn, api, mail
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('server_id')->nullable()->constrained('infra_servers')->nullOnDelete();
            $table->string('registrar')->nullable(); // NIC.ir, Namecheap, etc.
            $table->date('registered_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('ssl_enabled')->default(false);
            $table->string('ssl_provider')->nullable(); // Let's Encrypt, ZeroSSL, etc.
            $table->date('ssl_expires_at')->nullable();
            $table->string('ssl_status')->default('unknown'); // valid, expiring_soon, expired, unknown
            $table->string('dns_provider')->nullable(); // Cloudflare, ArvanCloud, etc.
            $table->text('dns_notes')->nullable();
            $table->string('status')->default('active'); // active, inactive, parked, transferred
            $table->boolean('redirects_to_https')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('status');
            $table->index('expires_at');
            $table->index('ssl_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infra_domains');
    }
};
