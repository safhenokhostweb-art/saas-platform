<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('license_key_prefix', 16)->index(); // اول ۸ کاراکتر برای جستجو
            $table->string('license_key_hash', 64); // SHA-256 کل کلید
            $table->string('domain_allowed')->nullable(); // یک دامنه یا چند دامنه با جداکننده
            $table->timestamp('expires_at')->nullable();
            $table->string('product_sku', 64)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_licenses');
    }
};
